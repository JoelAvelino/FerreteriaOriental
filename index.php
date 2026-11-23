<?php

use App\Database;
use App\Repositories\CatalogRepository;
use App\Repositories\PriceHistoryRepository;
use App\Repositories\SalesRepository;
use App\Services\CatalogService;
use App\Services\PriceHistoryService;
use App\Services\PriceProductNotFoundException;
use App\Services\PriceService;
use App\Services\PriceValidationException;
use App\Services\SalesException;
use App\Services\SalesService;

require __DIR__ . '/app/Database.php';
require __DIR__ . '/app/Repositories/CatalogRepository.php';
require __DIR__ . '/app/Repositories/PriceHistoryRepository.php';
require __DIR__ . '/app/Services/CatalogService.php';
require __DIR__ . '/app/Services/PriceHistoryService.php';
require __DIR__ . '/app/Services/PriceService.php';
require __DIR__ . '/app/Repositories/SalesRepository.php';
require __DIR__ . '/app/Services/SalesService.php';

session_start();

$config = require __DIR__ . '/config/database.php';
try {
    $database = new Database($config);
    $connection = $database->getConnection();
} catch (Throwable $exception) {
    error_log('No se pudo conectar con MySQL: ' . $exception->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Servicio no disponible</title>';
    echo '<body><h1>No se pudo conectar con la base de datos</h1>';
    echo '<p>Verifica que MySQL esté iniciado en XAMPP y que la configuración sea correcta.</p></body></html>';
    exit;
}

$catalogRepository = new CatalogRepository($connection);
$service = new CatalogService($catalogRepository);
$priceService = new PriceService($catalogRepository);

$requestedModule = $_GET['modulo'] ?? 'categorias';
$moduleKey = is_string($requestedModule) ? $requestedModule : '';
$module = $service->getModule($moduleKey);

if ($module === null) {
    http_response_code(404);
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Módulo no encontrado | Ferretería Oriental</title><link rel="stylesheet" href="assets/css/styles.css">';
    echo '<body><main><h1>Módulo no encontrado</h1><p>El módulo solicitado no existe.</p>';
    echo '<p><a href="?modulo=categorias">Volver a Categorías</a></p></main></body></html>';
    exit;
}
$message = $_SESSION['flash_message'] ?? null;
$messageType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($moduleKey === 'historial_ventas') {
    $salesService = new SalesService(new SalesRepository($connection));
    $modules = $service->modules();
    $module = $service->getModule('historial_ventas');
    $sales = [];
    $sale = null;
    $folioRequested = array_key_exists('folio', $_GET);
    $historyError = null;

    try {
        $sales = $salesService->listSales();
    } catch (Throwable $exception) {
        error_log('Error al consultar el historial de ventas: ' . $exception->getMessage());
        $historyError = 'No se pudo cargar el historial de ventas. Verifica la conexión e inténtalo nuevamente.';
    }

    if ($folioRequested) {
        $rawFolio = $_GET['folio'];
        if (!is_string($rawFolio)) {
            http_response_code(400);
            $historyError = 'El folio no es válido. Usa el formato V-000001.';
        } else {
            try {
                $sale = $salesService->findSaleByFolio($rawFolio);
                if ($sale === null) {
                    http_response_code(404);
                    $historyError = 'No se encontró una venta con ese folio.';
                }
            } catch (SalesException $exception) {
                http_response_code(400);
                $historyError = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('Error al consultar el detalle de venta: ' . $exception->getMessage());
                http_response_code(500);
                $historyError = 'No se pudo consultar el detalle de la venta. Verifica la conexión e inténtalo nuevamente.';
            }
        }
    }

    require __DIR__ . '/app/Views/sales_history.php';
    exit;
}

if ($moduleKey === 'historial_precios') {
    $historyService = new PriceHistoryService(new PriceHistoryRepository($connection));
    $filterInput = [
        'producto' => $_GET['producto'] ?? '',
        'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
        'fecha_fin' => $_GET['fecha_fin'] ?? '',
    ];
    [$filters, $filterErrors] = $historyService->validateFilters($filterInput);
    $historyRecords = [];
    $historyError = null;

    if (!$filterErrors) {
        try {
            $historyRecords = $historyService->search($filters);
        } catch (Throwable $exception) {
            error_log('Error al consultar el historial de precios: ' . $exception->getMessage());
            $historyError = 'No se pudo consultar el historial de precios. Verifica la conexión e inténtalo nuevamente.';
        }
    }

    $modules = $service->modules();
    $module = $service->getModule('historial_precios');
    require __DIR__ . '/app/Views/price_history.php';
    exit;
}

if ($moduleKey === 'ubicaciones') {
    $filterInput = [
        'busqueda' => $_GET['busqueda'] ?? '',
        'pasillo' => $_GET['pasillo'] ?? '',
    ];
    [$locationFilters, $locationErrors] = $service->validateLocationFilters($filterInput);
    $locationProducts = [];
    $locationError = null;

    if (!$locationErrors) {
        try {
            $locationProducts = $service->searchProductsForLocations($locationFilters);
        } catch (Throwable $exception) {
            error_log('Error al consultar las ubicaciones de productos: ' . $exception->getMessage());
            $locationError = 'No se pudieron consultar los productos. Verifica la conexión e inténtalo nuevamente.';
        }
    }

    $modules = $service->modules();
    $module = $service->getModule('ubicaciones');
    require __DIR__ . '/app/Views/locations.php';
    exit;
}

if ($moduleKey === 'ventas') {
    $salesService = new SalesService(new SalesRepository($connection));
    $salesModule = $service->getModule('ventas');
    $errors = [];
    $values = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
            $errors['_form'] = 'La sesión del formulario venció. Recarga la página e inténtalo de nuevo.';
        } else {
            try {
                [$validatedValues, $errors] = $salesService->validate($_POST);
                if (!$errors) {
                    $folio = $salesService->register($validatedValues);
                    $_SESSION['flash_message'] = 'Venta registrada correctamente. Folio: ' . $folio . '.';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: ?modulo=ventas', true, 303);
                    exit;
                }
            } catch (SalesException $exception) {
                $errors['_form'] = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('Error al registrar venta: ' . $exception->getMessage());
                $errors['_form'] = 'No se pudo registrar la venta. No se guardaron cambios; inténtalo nuevamente.';
            }
        }
    }

    try {
        $options = $salesService->formOptions();
    } catch (Throwable $exception) {
        error_log('Error al cargar opciones de venta: ' . $exception->getMessage());
        $options = ['clientes' => [], 'metodos_pago' => [], 'productos' => []];
        $errors['_form'] = 'No se pudieron cargar los datos de la venta. Verifica la conexión e inténtalo nuevamente.';
    }

    $modules = $service->modules();
    $module = $salesModule;
    $csrfToken = $_SESSION['csrf_token'];
    require __DIR__ . '/app/Views/sales.php';
    exit;
}

if ($moduleKey === 'precios') {
    $errors = [];
    $searchResults = [];
    $selectedProduct = null;
    $searchInput = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ($_POST['busqueda'] ?? '')
        : ($_GET['busqueda'] ?? '');
    $searchTerm = '';

    if (!is_string($searchInput)) {
        $errors['busqueda'] = 'El texto de búsqueda no tiene un formato válido.';
    } else {
        $searchTerm = trim($searchInput);
    }

    $rawProductId = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ($_POST['id_producto'] ?? null)
        : ($_GET['id'] ?? null);
    $selectedProductId = null;
    if ($rawProductId !== null && $rawProductId !== '') {
        $selectedProductId = is_string($rawProductId)
            ? filter_var($rawProductId, FILTER_VALIDATE_INT)
            : false;
        if ($selectedProductId === false || $selectedProductId < 1) {
            $selectedProductId = null;
            $errors['_form'] = 'Selecciona un producto válido.';
        }
    }

    $newPrice = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ($_POST['precio_nuevo'] ?? '')
        : '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
            $errors['_form'] = 'La sesión del formulario venció. Recarga la página e inténtalo de nuevo.';
        } elseif (($_POST['_accion'] ?? '') !== 'actualizar_precio') {
            $errors['_form'] = 'La operación solicitada no es válida.';
        } elseif ($selectedProductId === null) {
            $errors['_form'] = 'Selecciona un producto válido.';
        } else {
            try {
                $priceService->updatePrice((int) $selectedProductId, $newPrice);
                $_SESSION['flash_message'] = 'El precio del producto se actualizó correctamente.';
                $_SESSION['flash_type'] = 'success';
                $redirectParameters = ['modulo' => 'precios', 'id' => $selectedProductId];
                if ($searchTerm !== '') {
                    $redirectParameters['busqueda'] = $searchTerm;
                }
                header('Location: ?' . http_build_query($redirectParameters), true, 303);
                exit;
            } catch (PriceValidationException $exception) {
                $errors['precio_nuevo'] = $exception->getMessage();
            } catch (PriceProductNotFoundException $exception) {
                $errors['_form'] = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('Error al actualizar el precio del producto: ' . $exception->getMessage());
                $errors['_form'] = 'No se pudo actualizar el precio. No se guardaron cambios; inténtalo nuevamente.';
            }
        }
    }

    if ($selectedProductId !== null) {
        try {
            $selectedProduct = $service->findProduct((int) $selectedProductId);
            if ($selectedProduct === null) {
                http_response_code(404);
                $errors['_form'] = 'El producto solicitado ya no existe. Vuelve a buscarlo.';
            }
        } catch (Throwable $exception) {
            error_log('Error al cargar producto para actualizar su precio: ' . $exception->getMessage());
            http_response_code(500);
            $errors['_form'] = 'No se pudo cargar el producto. Verifica la conexión e inténtalo nuevamente.';
        }
    }

    if ($searchTerm !== '' && !isset($errors['busqueda'])) {
        try {
            $searchResults = $priceService->searchProducts($searchTerm);
        } catch (PriceValidationException $exception) {
            $errors['busqueda'] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Error al buscar productos para actualizar precios: ' . $exception->getMessage());
            $errors['_form'] = 'No se pudieron consultar los productos. Verifica la conexión e inténtalo nuevamente.';
        }
    }

    $modules = $service->modules();
    $module = $service->getModule('precios');
    $csrfToken = $_SESSION['csrf_token'];
    require __DIR__ . '/app/Views/prices.php';
    exit;
}

$errors = [];
$values = [];
$requestedAction = $_GET['accion'] ?? 'listar';
$productAction = $moduleKey === 'productos' && is_string($requestedAction) ? $requestedAction : 'crear';
$editingProductId = null;
$productFormError = null;

if ($moduleKey === 'productos' && $productAction === 'editar' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $rawId = $_GET['id'] ?? null;
    $requestedId = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
    try {
        $product = $requestedId === false ? null : $service->findProduct((int) $requestedId);
    } catch (Throwable $exception) {
        error_log('Error al cargar producto para edición: ' . $exception->getMessage());
        http_response_code(500);
        $product = null;
        $productFormError = 'No se pudo cargar el producto para edición. Verifica la conexión e inténtalo nuevamente.';
    }
    if ($product === null && $productFormError === null) {
        http_response_code(404);
        $productFormError = 'El producto solicitado no existe.';
    } elseif ($product !== null) {
        $editingProductId = (int) $product['id'];
        $values = $product;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && http_response_code() !== 404) {
    $values = $_POST;
    $operation = $_POST['_accion'] ?? 'crear';
    if (!is_string($operation)) {
        $operation = '';
    }
    if ($moduleKey === 'productos' && $operation === 'actualizar') {
        $productAction = 'editar';
        $rawId = $_POST['_id'] ?? null;
        $editingProductId = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
        if ($editingProductId === false || $editingProductId < 1) {
            $editingProductId = null;
            $productFormError = 'No se identificó el producto que se intenta actualizar.';
        }
    } elseif ($moduleKey === 'productos') {
        $productAction = 'crear';
    }

    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $errors['_form'] = 'La sesión del formulario venció. Recarga la página e inténtalo de nuevo.';
    } elseif ($operation === 'eliminar') {
        $rawId = $_POST['_id'] ?? null;
        $recordId = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
        if ($recordId === false || $recordId < 1) {
            $errors['_form'] = 'No se identificó un registro válido para eliminar.';
        } else {
            try {
                if (!$service->delete($moduleKey, (int) $recordId)) {
                    $errors['_form'] = 'El registro ya no existe o ya fue eliminado.';
                } else {
                    $_SESSION['flash_message'] = 'Registro eliminado correctamente.';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: ?modulo=' . rawurlencode($moduleKey) . ($moduleKey === 'productos' ? '#catalogo' : ''), true, 303);
                    exit;
                }
            } catch (\PDOException $exception) {
                error_log('Error al eliminar registro de ' . $moduleKey . ': ' . $exception->getMessage());
                $errors['_form'] = 'No se pudo eliminar el registro. Verifica sus relaciones e inténtalo nuevamente.';
            } catch (RuntimeException $exception) {
                $errors['_form'] = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('Error al eliminar registro de ' . $moduleKey . ': ' . $exception->getMessage());
                $errors['_form'] = 'No se pudo eliminar el registro. Inténtalo nuevamente.';
            }
        }
    } elseif ($productFormError !== null) {
        $errors['_form'] = $productFormError;
    } else {
        try {
            [$validatedValues, $errors] = $service->validate($moduleKey, $_POST);
        } catch (Throwable $exception) {
            error_log('Error al validar ' . $moduleKey . ': ' . $exception->getMessage());
            $errors = ['_form' => 'No se pudieron validar los datos. Verifica la conexión e inténtalo nuevamente.'];
        }

        if (!$errors) {
            try {
                if ($moduleKey === 'productos' && $operation === 'actualizar') {
                    if (!$service->updateProduct((int) $editingProductId, $validatedValues)) {
                        $errors['_form'] = 'El producto ya no existe y no se pudo actualizar.';
                    } else {
                        $_SESSION['flash_message'] = 'Producto actualizado correctamente.';
                    }
                } elseif ($moduleKey === 'productos' && $operation !== 'crear') {
                    $errors['_form'] = 'La operación solicitada no es válida.';
                } else {
                    $service->create($moduleKey, $validatedValues);
                    $_SESSION['flash_message'] = $module['success_message'] ?? $module['title'] . ' guardado correctamente.';
                }

                if ($errors) {
                    throw new RuntimeException($errors['_form']);
                }

                $_SESSION['flash_type'] = 'success';
                header('Location: ?modulo=' . rawurlencode($moduleKey) . ($moduleKey === 'productos' ? '#catalogo' : ''), true, 303);
                exit;
            } catch (RuntimeException $exception) {
                if (!$errors) {
                    $errors['_form'] = $exception->getMessage();
                }
            } catch (Throwable $exception) {
                error_log('Error al guardar ' . $moduleKey . ': ' . $exception->getMessage());
                $errors['_form'] = 'No se pudo guardar el registro. Verifica la conexión e inténtalo nuevamente.';
            }
        }
    }
}

$modules = $service->modules();
$csrfToken = $_SESSION['csrf_token'];
$options = [];
$prerequisiteMessage = null;
$showListing = $module['show_listing'] ?? true;
$productFilters = [
    'busqueda' => '',
    'id_categoria' => null,
    'id_marca' => null,
    'id_proveedor' => null,
];
$filterErrors = [];
$productRecords = [];

try {
    $records = $showListing && $moduleKey !== 'productos' ? $service->list($moduleKey) : [];
} catch (Throwable $exception) {
    error_log('Error al listar ' . $moduleKey . ': ' . $exception->getMessage());
    $records = [];
    $message = 'No se pudo cargar el listado. Verifica la conexión con MySQL.';
    $messageType = 'error';
}

if ($moduleKey === 'productos') {
    try {
        [$productFilters, $filterErrors] = $service->validateProductFilters($_GET);
        if (!$filterErrors) {
            $productRecords = $service->searchProducts($productFilters);
        }
    } catch (Throwable $exception) {
        error_log('Error al consultar productos: ' . $exception->getMessage());
        $message = 'No se pudo cargar el catálogo de productos. Verifica la conexión e inténtalo nuevamente.';
        $messageType = 'error';
    }
}

try {
    $options = $service->options($moduleKey);
    foreach ($module['fields'] as $name => $field) {
        if (($field['type'] ?? '') === 'select' && $field['required'] && empty($options[$name])) {
            $prerequisiteMessage = 'Registra primero al menos una categoría, una marca y un proveedor antes de crear productos.';
            break;
        }
    }
} catch (Throwable $exception) {
    error_log('Error al cargar opciones de ' . $moduleKey . ': ' . $exception->getMessage());
    $message = 'No se pudieron cargar los datos relacionados. Verifica la conexión e inténtalo nuevamente.';
    $messageType = 'error';
}

$productFormTitle = $productAction === 'editar' ? 'Modificar producto' : 'Registrar producto';

require __DIR__ . '/app/Views/catalog.php';
