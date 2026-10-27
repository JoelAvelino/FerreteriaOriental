<?php

use App\Database;
use App\Repositories\SalesRepository;
use App\Services\SalesException;
use App\Services\SalesService;

require __DIR__ . '/../app/Database.php';
require __DIR__ . '/../app/Repositories/SalesRepository.php';
require __DIR__ . '/../app/Services/SalesService.php';
require __DIR__ . '/../app/Repositories/CatalogRepository.php';
require __DIR__ . '/../app/Services/CatalogService.php';

$config = require __DIR__ . '/../config/database.php';
$connection = (new Database($config))->getConnection();
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
    $category = $connection->prepare('INSERT INTO `categoria` (`nombre`) VALUES (:nombre)');
    $category->execute([':nombre' => 'Categoría ficticia historial ' . $suffix]);
    $categoryId = (int) $connection->lastInsertId();

    $brand = $connection->prepare(
        'INSERT INTO `marca` (`nombre`, `pais_origen`) VALUES (:nombre, :pais)'
    );
    $brand->execute([
        ':nombre' => 'Marca ficticia historial ' . $suffix,
        ':pais' => 'País ficticio',
    ]);
    $brandId = (int) $connection->lastInsertId();

    $supplier = $connection->prepare(
        'INSERT INTO `proveedor` (`nombre`, `contacto`) VALUES (:nombre, :contacto)'
    );
    $supplier->execute([
        ':nombre' => 'Proveedor ficticio historial ' . $suffix,
        ':contacto' => 'Contacto ficticio',
    ]);
    $supplierId = (int) $connection->lastInsertId();

    $product = $connection->prepare(
        'INSERT INTO `producto` '
        . '(`nombre`, `id_categoria`, `id_marca`, `id_proveedor`, `stock`, `precio_venta`) '
        . 'VALUES (:nombre, :categoria, :marca, :proveedor, :stock, :precio)'
    );
    $product->execute([
        ':nombre' => 'Producto ficticio historial ' . $suffix,
        ':categoria' => $categoryId,
        ':marca' => $brandId,
        ':proveedor' => $supplierId,
        ':stock' => 5,
        ':precio' => '12.50',
    ]);
    $productId = (int) $connection->lastInsertId();

    $customer = $connection->prepare(
        'INSERT INTO `cliente` (`nombre`, `correo`) VALUES (:nombre, :correo)'
    );
    $customer->execute([
        ':nombre' => 'Cliente ficticio historial ' . $suffix,
        ':correo' => 'historial-' . $suffix . '@example.test',
    ]);
    $customerId = (int) $connection->lastInsertId();

    $paymentMethod = $connection->prepare('INSERT INTO `metodo_pago` (`nombre`) VALUES (:nombre)');
    $paymentMethod->execute([':nombre' => 'Pago ficticio historial ' . $suffix]);
    $paymentMethodId = (int) $connection->lastInsertId();

    $repository = new SalesRepository($connection);
    $service = new SalesService($repository);
    [$values, $errors] = $service->validate([
        'id_cliente' => (string) $customerId,
        'id_metodo_pago' => (string) $paymentMethodId,
        'producto_id' => [(string) $productId],
        'cantidad' => ['2'],
    ]);
    $assert($errors === [], 'No se pudo preparar la venta ficticia para el historial.');
    $folio = $service->register($values);

    $updatePrice = $connection->prepare('UPDATE `producto` SET `precio_venta` = :precio WHERE `id` = :id');
    $updatePrice->execute([':precio' => '99.99', ':id' => $productId]);

    $salesBeforeReads = (int) $connection->query('SELECT COUNT(*) FROM `venta`')->fetchColumn();
    $detailsBeforeReads = (int) $connection->query('SELECT COUNT(*) FROM `detalle_venta`')->fetchColumn();
    $stockBeforeReads = $connection->prepare('SELECT `stock` FROM `producto` WHERE `id` = :id');
    $stockBeforeReads->execute([':id' => $productId]);
    $stockBeforeReads = (int) $stockBeforeReads->fetchColumn();

    $history = $service->listSales();
    $historyRecord = null;
    foreach ($history as $record) {
        if ($record['folio'] === $folio) {
            $historyRecord = $record;
            break;
        }
    }
    $assert($historyRecord !== null, 'La venta ficticia no aparece en el historial.');
    $assert($historyRecord['cliente'] === 'Cliente ficticio historial ' . $suffix, 'El historial no muestra el cliente de la venta.');
    $assert($historyRecord['total'] === '25.00', 'El historial no muestra el total correcto.');

    $sale = $service->findSaleByFolio($folio);
    $assert($sale !== null, 'No se encontró la venta ficticia por folio.');
    $assert($sale['folio'] === $folio, 'El detalle no conserva el folio consultado.');
    $assert($sale['cliente'] === 'Cliente ficticio historial ' . $suffix, 'El detalle no muestra el cliente relacionado.');
    $assert($sale['metodo_pago'] === 'Pago ficticio historial ' . $suffix, 'El detalle no muestra el método de pago.');
    $assert(count($sale['detalles']) === 1, 'El detalle no contiene el producto esperado.');
    $assert($sale['detalles'][0]['producto'] === 'Producto ficticio historial ' . $suffix, 'El detalle no muestra el producto esperado.');
    $assert((int) $sale['detalles'][0]['cantidad'] === 2, 'El detalle no muestra la cantidad correcta.');
    $assert($sale['detalles'][0]['precio_unitario'] === '12.50', 'El detalle no conserva el precio histórico tras cambiar el precio actual.');
    $assert($sale['detalles'][0]['subtotal'] === '25.00', 'El detalle no muestra el subtotal correcto.');
    $assert($sale['total'] === '25.00', 'El detalle no muestra el total persistido.');

    try {
        $service->findSaleByFolio('folio-invalido');
        $assert(false, 'Se aceptó un folio con formato inválido.');
    } catch (SalesException $exception) {
        $assert(str_contains($exception->getMessage(), 'no es válido'), 'No se explicó que el formato del folio es inválido.');
    }
    $assert($service->findSaleByFolio('V-999999999999') === null, 'Se encontró una venta ficticia con un folio inexistente.');

    ob_start();
    $modules = (new App\Services\CatalogService(new App\Repositories\CatalogRepository($connection)))->modules();
    $module = ['title' => 'Historial de ventas'];
    $sales = [];
    $sale = null;
    $folioRequested = false;
    $historyError = null;
    require __DIR__ . '/../app/Views/sales_history.php';
    $emptyHistoryHtml = ob_get_clean();
    $assert(
        str_contains($emptyHistoryHtml, 'Aún no hay ventas registradas.'),
        'El historial vacío no muestra un mensaje apropiado.'
    );

    ob_start();
    $sale = $service->findSaleByFolio($folio);
    $sales = [$historyRecord];
    $folioRequested = true;
    require __DIR__ . '/../app/Views/sales_history.php';
    $detailHtml = ob_get_clean();
    $assert(str_contains($detailHtml, '$12.50'), 'La vista no presenta el precio histórico.');
    $assert(str_contains($detailHtml, '$25.00'), 'La vista no presenta el subtotal y total.');
    $assert(str_contains($detailHtml, 'Pago ficticio historial ' . $suffix), 'La vista no presenta el método de pago.');

    $assert(
        (int) $connection->query('SELECT COUNT(*) FROM `venta`')->fetchColumn() === $salesBeforeReads,
        'La consulta de historial/detalle modificó el número de ventas.'
    );
    $assert(
        (int) $connection->query('SELECT COUNT(*) FROM `detalle_venta`')->fetchColumn() === $detailsBeforeReads,
        'La consulta de historial/detalle modificó el número de detalles.'
    );
    $stockAfterReads = $connection->prepare('SELECT `stock` FROM `producto` WHERE `id` = :id');
    $stockAfterReads->execute([':id' => $productId]);
    $assert((int) $stockAfterReads->fetchColumn() === $stockBeforeReads, 'La consulta de historial/detalle modificó las existencias.');

    echo "Pruebas de historial de ventas: {$tests} comprobaciones OK." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
