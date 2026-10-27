<?php

use App\Database;
use App\Repositories\SalesRepository;
use App\Services\SalesException;
use App\Services\SalesService;

require __DIR__ . '/../app/Database.php';
require __DIR__ . '/../app/Repositories/SalesRepository.php';
require __DIR__ . '/../app/Services/SalesService.php';

class FailingSalesRepository extends SalesRepository
{
    private int $detailAttempts = 0;

    public function insertSaleDetail(int $saleId, array $item): void
    {
        $this->detailAttempts++;
        if ($this->detailAttempts === 2) {
            throw new RuntimeException('Fallo forzado para verificar rollback.');
        }

        parent::insertSaleDetail($saleId, $item);
    }
}

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

$insertCatalogRecord = static function (string $table, string $name) use ($connection): int {
    $statement = $connection->prepare("INSERT INTO `{$table}` (`nombre`) VALUES (:nombre)");
    $statement->execute([':nombre' => $name]);

    return (int) $connection->lastInsertId();
};

$connection->beginTransaction();

try {
    $categoryId = $insertCatalogRecord('categoria', 'Categoría ficticia venta ' . $suffix);
    $brandId = $insertCatalogRecord('marca', 'Marca ficticia venta ' . $suffix);
    $supplierStatement = $connection->prepare(
        'INSERT INTO `proveedor` (`nombre`, `contacto`) VALUES (:nombre, :contacto)'
    );
    $supplierStatement->execute([
        ':nombre' => 'Proveedor ficticio venta ' . $suffix,
        ':contacto' => 'Contacto de prueba',
    ]);
    $supplierId = (int) $connection->lastInsertId();

    $insertProduct = static function (string $name, int $stock, ?string $price) use (
        $connection,
        $categoryId,
        $brandId,
        $supplierId
    ): int {
        $statement = $connection->prepare(
            'INSERT INTO `producto` '
            . '(`nombre`, `id_categoria`, `id_marca`, `id_proveedor`, `stock`, `precio_venta`) '
            . 'VALUES (:nombre, :categoria, :marca, :proveedor, :stock, :precio)'
        );
        $statement->execute([
            ':nombre' => $name,
            ':categoria' => $categoryId,
            ':marca' => $brandId,
            ':proveedor' => $supplierId,
            ':stock' => $stock,
            ':precio' => $price,
        ]);

        return (int) $connection->lastInsertId();
    };

    $productOneId = $insertProduct('Producto ficticio A ' . $suffix, 5, '12.50');
    $productTwoId = $insertProduct('Producto ficticio B ' . $suffix, 4, '3.25');
    $zeroPriceProductId = $insertProduct('Producto ficticio precio cero ' . $suffix, 10, '0.00');
    $nullPriceProductId = $insertProduct('Producto ficticio sin precio ' . $suffix, 10, null);

    $customerStatement = $connection->prepare(
        'INSERT INTO `cliente` (`nombre`, `telefono`, `correo`) VALUES (:nombre, :telefono, :correo)'
    );
    $customerStatement->execute([
        ':nombre' => 'Cliente ficticio ventas ' . $suffix,
        ':telefono' => '+1555' . random_int(1000000, 9999999),
        ':correo' => 'venta-' . $suffix . '@example.test',
    ]);
    $customerId = (int) $connection->lastInsertId();
    $paymentMethodId = $insertCatalogRecord('metodo_pago', 'Pago ficticio venta ' . $suffix);

    $repository = new SalesRepository($connection);
    $service = new SalesService($repository);
    $options = $service->formOptions();
    $assert(count($options['clientes']) >= 1, 'No se cargó el selector de clientes.');
    $assert(count($options['metodos_pago']) >= 1, 'No se cargó el selector de métodos de pago.');
    $assert(count($options['productos']) >= 4, 'No se cargó el selector de productos.');

    [, $emptyErrors] = $service->validate([
        'id_cliente' => (string) $customerId,
        'id_metodo_pago' => (string) $paymentMethodId,
        'producto_id' => [''],
        'cantidad' => ['1'],
    ]);
    $assert(isset($emptyErrors['productos']), 'Se aceptó una venta sin productos.');

    [, $quantityErrors] = $service->validate([
        'id_cliente' => (string) $customerId,
        'id_metodo_pago' => (string) $paymentMethodId,
        'producto_id' => [(string) $productOneId],
        'cantidad' => ['0'],
    ]);
    $assert(isset($quantityErrors['productos']), 'Se aceptó una cantidad igual a cero.');

    [, $duplicateErrors] = $service->validate([
        'id_cliente' => (string) $customerId,
        'id_metodo_pago' => (string) $paymentMethodId,
        'producto_id' => [(string) $productOneId, (string) $productOneId],
        'cantidad' => ['1', '1'],
    ]);
    $assert(isset($duplicateErrors['productos']), 'Se aceptó el mismo producto en varias líneas.');

    [$values, $errors] = $service->validate([
        'id_cliente' => (string) $customerId,
        'id_metodo_pago' => (string) $paymentMethodId,
        'producto_id' => [(string) $productOneId, (string) $productTwoId],
        'cantidad' => ['2', '1'],
    ]);
    $assert($errors === [], 'Se rechazó una venta válida con varios productos.');

    $folio = $service->register($values);
    $assert((bool) preg_match('/^V-\d{6,}$/', $folio), 'El folio no usa el prefijo y secuencia esperados.');

    $saleStatement = $connection->prepare(
        'SELECT `id`, `id_cliente`, `id_metodo_pago`, `total` FROM `venta` WHERE `folio` = :folio'
    );
    $saleStatement->execute([':folio' => $folio]);
    $sale = $saleStatement->fetch();
    $assert($sale !== false, 'La cabecera de venta no se persistió.');
    $assert((int) $sale['id_cliente'] === $customerId, 'La venta no conservó el cliente seleccionado.');
    $assert((int) $sale['id_metodo_pago'] === $paymentMethodId, 'La venta no conservó el método de pago.');
    $assert($sale['total'] === '28.25', 'El total de venta no se calculó correctamente en el servidor.');

    $detailStatement = $connection->prepare(
        'SELECT `id_producto`, `cantidad`, `precio_unitario`, `subtotal` '
        . 'FROM `detalle_venta` WHERE `id_venta` = :venta ORDER BY `id_producto`'
    );
    $detailStatement->execute([':venta' => $sale['id']]);
    $details = $detailStatement->fetchAll();
    $assert(count($details) === 2, 'No se persistieron las líneas de detalle esperadas.');
    $assert($details[0]['precio_unitario'] === '12.50' && $details[0]['subtotal'] === '25.00', 'No se conservó el precio histórico y subtotal.');
    $assert($details[1]['precio_unitario'] === '3.25' && $details[1]['subtotal'] === '3.25', 'El segundo detalle tiene importes incorrectos.');

    $stockStatement = $connection->prepare('SELECT `stock` FROM `producto` WHERE `id` = :id');
    $stockStatement->execute([':id' => $productOneId]);
    $assert((int) $stockStatement->fetchColumn() === 3, 'El stock del primer producto no se descontó correctamente.');
    $stockStatement->execute([':id' => $productTwoId]);
    $assert((int) $stockStatement->fetchColumn() === 3, 'El stock del segundo producto no se descontó correctamente.');

    $saleCountBeforeFailure = (int) $connection->query('SELECT COUNT(*) FROM `venta`')->fetchColumn();
    $stockStatement->execute([':id' => $productOneId]);
    $stockOneBeforeFailure = (int) $stockStatement->fetchColumn();
    $stockStatement->execute([':id' => $productTwoId]);
    $stockTwoBeforeFailure = (int) $stockStatement->fetchColumn();

    try {
        $failingService = new SalesService(new FailingSalesRepository($connection));
        $failingService->register($values);
        $assert(false, 'No se provocó el fallo esperado para probar rollback.');
    } catch (RuntimeException $exception) {
        $assert($exception->getMessage() === 'Fallo forzado para verificar rollback.', 'El rollback no conservó el error de prueba esperado.');
    }
    $assert(
        (int) $connection->query('SELECT COUNT(*) FROM `venta`')->fetchColumn() === $saleCountBeforeFailure,
        'El rollback dejó una cabecera de venta parcial.'
    );
    $stockStatement->execute([':id' => $productOneId]);
    $assert((int) $stockStatement->fetchColumn() === $stockOneBeforeFailure, 'El rollback no restauró el stock del primer producto.');
    $stockStatement->execute([':id' => $productTwoId]);
    $assert((int) $stockStatement->fetchColumn() === $stockTwoBeforeFailure, 'El rollback no conservó el stock del segundo producto.');

    try {
        $insufficientValues = $values;
        $insufficientValues['items'] = [['id' => $productOneId, 'cantidad' => 99]];
        $service->register($insufficientValues);
        $assert(false, 'Se aceptó una venta con existencias insuficientes.');
    } catch (SalesException $exception) {
        $assert(str_contains($exception->getMessage(), 'Existencias insuficientes'), 'No se informó el stock insuficiente.');
    }

    foreach ([$zeroPriceProductId, $nullPriceProductId] as $invalidPriceProductId) {
        try {
            $invalidPriceValues = $values;
            $invalidPriceValues['items'] = [['id' => $invalidPriceProductId, 'cantidad' => 1]];
            $service->register($invalidPriceValues);
            $assert(false, 'Se aceptó un precio de venta cero o vacío.');
        } catch (SalesException $exception) {
            $assert(str_contains($exception->getMessage(), 'precio de venta válido'), 'No se informó el precio no válido.');
        }
    }

    echo "Pruebas de ventas: {$tests} comprobaciones OK." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
