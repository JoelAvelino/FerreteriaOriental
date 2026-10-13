<?php

use App\Database;
use App\Repositories\CatalogRepository;
use App\Services\CatalogService;

require __DIR__ . '/app/Database.php';
require __DIR__ . '/app/Repositories/CatalogRepository.php';
require __DIR__ . '/app/Services/CatalogService.php';

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

$service = new CatalogService(new CatalogRepository($connection));

$requestedModule = $_GET['modulo'] ?? 'categorias';
$moduleKey = is_string($requestedModule) ? $requestedModule : '';
$module = $service->getModule($moduleKey);

if ($module === null) {
    http_response_code(404);
    $moduleKey = 'categorias';
    $module = $service->getModule($moduleKey);
    $message = 'El módulo solicitado no existe.';
    $messageType = 'error';
} else {
    $message = $_SESSION['flash_message'] ?? null;
    $messageType = $_SESSION['flash_type'] ?? 'success';
}
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
