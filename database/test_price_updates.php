<?php

use App\Database;
use App\Repositories\CatalogRepository;
use App\Repositories\SalesRepository;
use App\Services\CatalogService;
use App\Services\PriceService;
use App\Services\PriceValidationException;
use App\Services\SalesService;

require __DIR__ . '/../app/Database.php';
require __DIR__ . '/../app/Repositories/CatalogRepository.php';
require __DIR__ . '/../app/Services/CatalogService.php';
require __DIR__ . '/../app/Services/PriceService.php';
require __DIR__ . '/../app/Repositories/SalesRepository.php';
require __DIR__ . '/../app/Services/SalesService.php';

class FailingPriceAuditRepository extends CatalogRepository
{
    protected function insertPriceHistory(
        int $productId,
        string $productName,
        ?string $previousPrice,
        ?string $newPrice
    ): void {
        throw new RuntimeException('Fallo forzado al insertar el historial.');
    }
}

$config = require __DIR__ . '/../config/database.php';
$connection = (new Database($config))->getConnection();
$repository = new CatalogRepository($connection);
$catalogService = new CatalogService($repository);
$priceService = new PriceService($repository);
$suffix = bin2hex(random_bytes(5));
$tests = 0;

$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$connection->beginTransaction();

try {
    $insert = static function (string $sql, array $parameters = []) use ($connection): int {
        $statement = $connection->prepare($sql);
        $statement->execute($parameters);

        return (int) $connection->lastInsertId();
    };

    $categoryId = $insert(
        'INSERT INTO `categoria` (`nombre`) VALUES (:nombre)',
        [':nombre' => 'Categoría ficticia HU11 ' . $suffix]
    );
    $brandId = $insert(
        'INSERT INTO `marca` (`nombre`, `pais_origen`) VALUES (:nombre, :pais)',
        [':nombre' => 'Marca ficticia HU11 ' . $suffix, ':pais' => 'País ficticio']
    );
    $supplierId = $insert(
        'INSERT INTO `proveedor` (`nombre`, `contacto`) VALUES (:nombre, :contacto)',
        [':nombre' => 'Proveedor ficticio HU11 ' . $suffix, ':contacto' => 'Contacto de prueba']
    );

    $productInput = [
        'nombre' => 'Producto ficticio HU11 ' . $suffix,
        'descripcion' => 'Fixture transaccional',
        'precio_compra' => '5.00',
        'precio_venta' => '10.00',
        'stock' => '5',
        'id_categoria' => $categoryId,
        'id_marca' => $brandId,
        'id_proveedor' => $supplierId,
    ];
    [$productValues, $productErrors] = $catalogService->validate('productos', $productInput);
    $assert($productErrors === [], 'No se validó el producto ficticio.');
    $catalogService->create('productos', $productValues);
    $productQuery = $connection->prepare('SELECT `id` FROM `producto` WHERE `nombre` = :nombre');
    $productQuery->execute([':nombre' => $productInput['nombre']]);
    $productId = (int) $productQuery->fetchColumn();
    $assert($productId > 0, 'No se creó el producto ficticio.');

    $historyForProduct = static function () use ($connection, $productId): array {
        $statement = $connection->prepare(
            'SELECT `codigo_producto`, `nombre_producto`, `precio_anterior`, `precio_nuevo`, `area_responsable` '
            . 'FROM `historial_precio` WHERE `id_producto` = :id ORDER BY `id`'
        );
        $statement->execute([':id' => $productId]);

        return $statement->fetchAll();
    };
    $assert(count($priceService->searchProducts((string) $productId)) === 1, 'La búsqueda por ID no encontró el producto.');
    $assert(count($priceService->searchProducts('ficticio HU11 ' . $suffix)) === 1, 'La búsqueda por nombre no encontró el producto.');
    $assert($priceService->searchProducts('producto inexistente HU11') === [], 'La búsqueda inexistente no devolvió resultados vacíos.');

    foreach (['', 'letras', '0', '-1', '1.001', '100000000.00'] as $invalidPrice) {
        $rejected = false;
        try {
            $priceService->updatePrice($productId, $invalidPrice);
        } catch (PriceValidationException $exception) {
            $rejected = true;
        }
        $assert($rejected, 'Se aceptó un precio HU11 inválido: ' . $invalidPrice);
    }

    $assert($priceService->validateNewPrice('1.2') === '1.20', 'No se normalizó el precio a dos decimales.');
    $assert($priceService->validateNewPrice('000000000.01') === '0.01', 'Se rechazaron ceros iniciales que no exceden DECIMAL(10,2).');
    $priceService->updatePrice($productId, '12.35');
    $history = $historyForProduct();
    $assert(count($history) === 1, 'El cambio desde HU11 no generó exactamente un registro.');
    $firstHistoryMatches = [
        (string) $history[0]['codigo_producto'] === (string) $productId,
        $history[0]['nombre_producto'] === $productInput['nombre'],
        $history[0]['precio_anterior'] === '10.00',
        $history[0]['precio_nuevo'] === '12.35',
        $history[0]['area_responsable'] === 'Administrador',
    ];
    $assert(
        !in_array(false, $firstHistoryMatches, true),
        'El primer registro histórico no guardó los valores esperados: ' . json_encode([
            'coincidencias' => $firstHistoryMatches,
            'id_esperado' => $productId,
            'fila' => $history[0],
        ])
    );

    $samePriceRejected = false;
    try {
        $priceService->updatePrice($productId, '12.35');
    } catch (PriceValidationException $exception) {
        $samePriceRejected = true;
    }
    $assert($samePriceRejected, 'Se aceptó un precio idéntico al vigente.');
    $assert(count($historyForProduct()) === 1, 'La igualdad generó un registro histórico.');

    $updateCatalogProduct = static function (array $changes) use (
        $repository,
        $catalogService,
        $productId,
        $assert
    ): void {
        $current = $repository->findProduct($productId);
        $input = array_replace($current, $changes);
        [$values, $errors] = $catalogService->validate('productos', $input);
        $assert($errors === [], 'La validación de edición del catálogo falló: ' . json_encode($errors));
        $assert($catalogService->updateProduct($productId, $values), 'La edición del catálogo no encontró el producto.');
    };

    $updatedName = 'Producto renombrado HU11 ' . $suffix;
    $updateCatalogProduct(['nombre' => $updatedName, 'precio_venta' => '13.50']);
    $history = $historyForProduct();
    $assert(count($history) === 2, 'La edición del catálogo no generó un único evento por cambio de precio.');
    $assert(
        $history[1]['nombre_producto'] === $updatedName
        && $history[1]['precio_anterior'] === '12.35'
        && $history[1]['precio_nuevo'] === '13.50',
        'El snapshot no capturó el nombre posterior a la edición del catálogo.'
    );

    $updateCatalogProduct(['nombre' => 'Producto solo nombre HU11 ' . $suffix, 'precio_venta' => '13.5']);
    $assert(count($historyForProduct()) === 2, 'Un cambio solo de nombre o formato decimal equivalente generó historial.');

    $updateCatalogProduct(['precio_venta' => '']);
    $history = $historyForProduct();
    $assert(count($history) === 3, 'El catálogo no registró la transición de precio a NULL.');
    $assert($history[2]['precio_anterior'] === '13.50' && $history[2]['precio_nuevo'] === null, 'La transición valor a NULL se guardó incorrectamente.');

    $priceService->updatePrice($productId, '4.25');
    $history = $historyForProduct();
    $assert(count($history) === 4, 'El primer precio desde NULL no generó exactamente un evento.');
    $assert($history[3]['precio_anterior'] === null && $history[3]['precio_nuevo'] === '4.25', 'La transición NULL a valor se guardó incorrectamente.');

    $customerId = $insert(
        'INSERT INTO `cliente` (`nombre`, `telefono`, `correo`) VALUES (:nombre, :telefono, :correo)',
        [
            ':nombre' => 'Cliente ficticio HU11 ' . $suffix,
            ':telefono' => '9' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            ':correo' => 'hu11-' . $suffix . '@example.test',
        ]
    );
    $paymentMethodId = $insert(
        'INSERT INTO `metodo_pago` (`nombre`) VALUES (:nombre)',
        [':nombre' => 'Pago ficticio HU11 ' . $suffix]
    );
    $salesService = new SalesService(new SalesRepository($connection));
    [$saleValues, $saleErrors] = $salesService->validate([
        'id_cliente' => (string) $customerId,
        'id_metodo_pago' => (string) $paymentMethodId,
        'producto_id' => [(string) $productId],
        'cantidad' => ['1'],
    ]);
    $assert($saleErrors === [], 'La venta de regresión no pasó la validación.');
    $folio = $salesService->register($saleValues);
    $saleDetail = $connection->prepare(
        'SELECT d.`precio_unitario`, d.`subtotal` FROM `detalle_venta` d '
        . 'INNER JOIN `venta` v ON v.`id` = d.`id_venta` WHERE v.`folio` = :folio'
    );
    $saleDetail->execute([':folio' => $folio]);
    $detail = $saleDetail->fetch();
    $assert($detail !== false && $detail['precio_unitario'] === '4.25', 'La venta nueva no usó el precio vigente.');

    $updateCatalogProduct(['nombre' => 'Producto final HU11 ' . $suffix, 'precio_venta' => '5.00']);
    $saleDetail->execute([':folio' => $folio]);
    $assert($saleDetail->fetch()['precio_unitario'] === '4.25', 'La edición de precio alteró el precio histórico de la venta.');
    $assert(count($historyForProduct()) === 5, 'La modificación final del catálogo no generó un evento.');

    $failingService = new PriceService(new FailingPriceAuditRepository($connection));
    $rollbackVerified = false;
    try {
        $failingService->updatePrice($productId, '6.00');
    } catch (RuntimeException $exception) {
        $rollbackVerified = $exception->getMessage() === 'Fallo forzado al insertar el historial.';
    }
    $currentProduct = $repository->findProduct($productId);
    $assert($rollbackVerified, 'No se propagó el error controlado de inserción del historial.');
    $assert($currentProduct['precio_venta'] === '5.00', 'El rollback no restauró el precio vigente.');
    $assert(count($historyForProduct()) === 5, 'El rollback dejó un evento histórico parcial.');

    $missingProductRejected = false;
    try {
        $priceService->updatePrice(4294967295, '8.00');
    } catch (RuntimeException $exception) {
        $missingProductRejected = str_contains($exception->getMessage(), 'ya no existe');
    }
    $assert($missingProductRejected, 'No se rechazó un producto inexistente.');

    ob_start();
    $modules = $catalogService->modules();
    $message = null;
    $messageType = 'success';
    $errors = [];
    $searchTerm = '';
    $searchResults = [];
    $selectedProduct = null;
    $newPrice = '';
    $csrfToken = 'token-de-prueba';
    require __DIR__ . '/../app/Views/prices.php';
    $priceSearchHtml = ob_get_clean();
    $assert(
        str_contains($priceSearchHtml, 'name="modulo" value="precios"'),
        'El formulario GET de HU11 no conserva el identificador de módulo.'
    );

    echo "Pruebas de actualización de precios: {$tests} comprobaciones OK." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
