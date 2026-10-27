<?php

use App\Database;
use App\Repositories\CatalogRepository;
use App\Repositories\SalesRepository;
use App\Services\CatalogService;
use App\Services\SalesService;

require __DIR__ . '/../app/Database.php';
require __DIR__ . '/../app/Repositories/CatalogRepository.php';
require __DIR__ . '/../app/Services/CatalogService.php';
require __DIR__ . '/../app/Repositories/SalesRepository.php';
require __DIR__ . '/../app/Services/SalesService.php';

$config = require __DIR__ . '/../config/database.php';
$connection = (new Database($config))->getConnection();
$catalog = new CatalogService(new CatalogRepository($connection));
$sales = new SalesService(new SalesRepository($connection));
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
    $categoryId = $insertCatalogRecord('categoria', 'Categoría con venta ' . $suffix);
    $brandId = $insertCatalogRecord('marca', 'Marca con venta ' . $suffix);
    $supplierStatement = $connection->prepare(
        'INSERT INTO `proveedor` (`nombre`, `contacto`) VALUES (:nombre, :contacto)'
    );
    $supplierStatement->execute([
        ':nombre' => 'Proveedor con venta ' . $suffix,
        ':contacto' => 'Contacto de prueba',
    ]);
    $supplierId = (int) $connection->lastInsertId();

    $productStatement = $connection->prepare(
        'INSERT INTO `producto` '
        . '(`nombre`, `id_categoria`, `id_marca`, `id_proveedor`, `stock`, `precio_venta`) '
        . 'VALUES (:nombre, :categoria, :marca, :proveedor, 3, 2.00)'
    );
    $productStatement->execute([
        ':nombre' => 'Producto con venta ' . $suffix,
        ':categoria' => $categoryId,
        ':marca' => $brandId,
        ':proveedor' => $supplierId,
    ]);
    $productId = (int) $connection->lastInsertId();

    $customerStatement = $connection->prepare(
        'INSERT INTO `cliente` (`nombre`, `telefono`, `correo`) VALUES (:nombre, :telefono, :correo)'
    );
    $customerStatement->execute([
        ':nombre' => 'Cliente eliminación ' . $suffix,
        ':telefono' => '+1555' . random_int(1000000, 9999999),
        ':correo' => 'elimina-' . $suffix . '@example.test',
    ]);
    $customerId = (int) $connection->lastInsertId();
    $paymentMethodId = $insertCatalogRecord('metodo_pago', 'Pago eliminación ' . $suffix);

    $validatedSale = $sales->validate([
        'id_cliente' => (string) $customerId,
        'id_metodo_pago' => (string) $paymentMethodId,
        'producto_id' => [(string) $productId],
        'cantidad' => ['1'],
    ]);
    $assert($validatedSale[1] === [], 'No se pudo preparar la venta fixture.');
    $sales->register($validatedSale[0]);

    foreach ([
        ['categorias', $categoryId, 'No se puede eliminar la categoría porque tiene productos asociados.'],
        ['proveedores', $supplierId, 'No se puede eliminar el proveedor porque tiene productos asociados.'],
        ['productos', $productId, 'No se puede eliminar el producto porque tiene ventas asociadas.'],
    ] as [$module, $id, $expectedMessage]) {
        try {
            $catalog->delete($module, $id);
            $assert(false, 'Se permitió eliminar un registro relacionado: ' . $module);
        } catch (RuntimeException $exception) {
            $assert($exception->getMessage() === $expectedMessage, 'No se explicó el motivo del bloqueo de ' . $module . '.');
        }
    }

    $freeCategoryId = $insertCatalogRecord('categoria', 'Categoría libre ' . $suffix);
    $freeSupplierStatement = $connection->prepare(
        'INSERT INTO `proveedor` (`nombre`, `contacto`) VALUES (:nombre, :contacto)'
    );
    $freeSupplierStatement->execute([
        ':nombre' => 'Proveedor libre ' . $suffix,
        ':contacto' => 'Contacto de prueba',
    ]);
    $freeSupplierId = (int) $connection->lastInsertId();
    $freeProductStatement = $connection->prepare(
        'INSERT INTO `producto` '
        . '(`nombre`, `id_categoria`, `id_marca`, `id_proveedor`, `stock`, `precio_venta`) '
        . 'VALUES (:nombre, :categoria, :marca, :proveedor, 0, 1.00)'
    );
    $freeProductStatement->execute([
        ':nombre' => 'Producto libre ' . $suffix,
        ':categoria' => $categoryId,
        ':marca' => $brandId,
        ':proveedor' => $supplierId,
    ]);
    $freeProductId = (int) $connection->lastInsertId();

    $assert($catalog->delete('productos', $freeProductId), 'No se eliminó un producto sin ventas.');
    $assert($catalog->delete('categorias', $freeCategoryId), 'No se eliminó una categoría sin productos.');
    $assert($catalog->delete('proveedores', $freeSupplierId), 'No se eliminó un proveedor sin productos.');

    $remainingProducts = $connection->prepare('SELECT COUNT(*) FROM `producto` WHERE `id` = :id');
    $remainingProducts->execute([':id' => $freeProductId]);
    $assert((int) $remainingProducts->fetchColumn() === 0, 'El producto eliminado sigue en la base.');

    echo "Pruebas de eliminación segura: {$tests} comprobaciones OK." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
