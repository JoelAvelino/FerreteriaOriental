<?php

use App\Database;
use App\Repositories\PriceHistoryRepository;
use App\Services\PriceHistoryService;

require __DIR__ . '/../app/Database.php';
require __DIR__ . '/../app/Repositories/PriceHistoryRepository.php';
require __DIR__ . '/../app/Services/PriceHistoryService.php';
require __DIR__ . '/../app/Repositories/CatalogRepository.php';
require __DIR__ . '/../app/Services/CatalogService.php';

$config = require __DIR__ . '/../config/database.php';
$connection = (new Database($config))->getConnection();
$repository = new PriceHistoryRepository($connection);
$service = new PriceHistoryService($repository);
$suffix = bin2hex(random_bytes(5));
$tests = 0;
$emptyFilters = ['producto' => '', 'fecha_inicio' => '', 'fecha_fin' => ''];
$baselineHistoryCount = count($repository->search($emptyFilters));
$baselineProductCount = (int) $connection->query('SELECT COUNT(*) FROM `producto`')->fetchColumn();

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
        [':nombre' => 'Categoría ficticia HU12 ' . $suffix]
    );
    $brandId = $insert(
        'INSERT INTO `marca` (`nombre`, `pais_origen`) VALUES (:nombre, :pais)',
        [':nombre' => 'Marca ficticia HU12 ' . $suffix, ':pais' => 'País ficticio']
    );
    $supplierId = $insert(
        'INSERT INTO `proveedor` (`nombre`, `contacto`) VALUES (:nombre, :contacto)',
        [':nombre' => 'Proveedor ficticio HU12 ' . $suffix, ':contacto' => 'Contacto ficticio']
    );
    $insertProduct = static function (string $name) use (
        $insert,
        $categoryId,
        $brandId,
        $supplierId
    ): int {
        return $insert(
            'INSERT INTO `producto` '
            . '(`nombre`, `id_categoria`, `id_marca`, `id_proveedor`) '
            . 'VALUES (:nombre, :categoria, :marca, :proveedor)',
            [
                ':nombre' => $name,
                ':categoria' => $categoryId,
                ':marca' => $brandId,
                ':proveedor' => $supplierId,
            ]
        );
    };
    $insertHistory = static function (
        int $productId,
        string $productName,
        ?string $previousPrice,
        ?string $newPrice,
        string $createdAt
    ) use ($insert): int {
        return $insert(
            'INSERT INTO `historial_precio` '
            . '(`id_producto`, `codigo_producto`, `nombre_producto`, `precio_anterior`, '
            . '`precio_nuevo`, `area_responsable`, `created_at`) '
            . 'VALUES (:producto, :codigo, :nombre, :anterior, :nuevo, :area, :fecha)',
            [
                ':producto' => $productId,
                ':codigo' => $productId,
                ':nombre' => $productName,
                ':anterior' => $previousPrice,
                ':nuevo' => $newPrice,
                ':area' => 'Administrador',
                ':fecha' => $createdAt,
            ]
        );
    };

    $alphaName = 'Producto HU12 Alpha ' . $suffix;
    $deletedName = 'Producto HU12 eliminado ' . $suffix;
    $alphaId = $insertProduct($alphaName);
    $deletedId = $insertProduct($deletedName);
    $alphaOld = $insertHistory($alphaId, $alphaName, null, '10.00', '2026-01-10 12:00:00');
    $alphaSameDate = $insertHistory($alphaId, $alphaName, '10.00', '12.00', '2026-01-10 12:00:00');
    $alphaNextDay = $insertHistory($alphaId, $alphaName, '12.00', '13.00', '2026-01-11 00:00:00');
    $deletedRecordId = $insertHistory($deletedId, $deletedName, '20.00', '21.00', '2026-01-11 23:59:59');

    $deleteProduct = $connection->prepare('DELETE FROM `producto` WHERE `id` = :id');
    $deleteProduct->execute([':id' => $deletedId]);
    $assert($deleteProduct->rowCount() === 1, 'No se pudo eliminar el producto ficticio para probar el snapshot.');

    [$filters, $errors] = $service->validateFilters($emptyFilters);
    $assert($errors === [], 'Los filtros vacíos se consideraron inválidos.');
    $allRecords = $service->search($filters);
    $assert(
        count($allRecords) === $baselineHistoryCount + 4,
        'La consulta general no devolvió todos los registros del historial.'
    );

    [$filters, $errors] = $service->validateFilters([
        'producto' => (string) $alphaId,
        'fecha_inicio' => '',
        'fecha_fin' => '',
    ]);
    $codeRecords = $errors ? [] : $service->search($filters);
    $assert(count($codeRecords) === 3, 'El filtro por código no encontró todos los cambios del producto.');
    $assert(
        (int) $codeRecords[0]['id'] === $alphaNextDay
        && (int) $codeRecords[1]['id'] === $alphaSameDate
        && (int) $codeRecords[2]['id'] === $alphaOld,
        'El historial no está ordenado por fecha e ID descendentes.'
    );

    [$filters, $errors] = $service->validateFilters([
        'producto' => 'Alpha ' . $suffix,
        'fecha_inicio' => '',
        'fecha_fin' => '',
    ]);
    $nameRecords = $errors ? [] : $service->search($filters);
    $assert(count($nameRecords) === 3, 'El filtro por nombre no encontró el snapshot del producto.');

    [$filters, $errors] = $service->validateFilters([
        'producto' => 'Alpha ' . $suffix,
        'fecha_inicio' => '2026-01-11',
        'fecha_fin' => '',
    ]);
    $startRecords = $errors ? [] : $service->search($filters);
    $assert(count($startRecords) === 1 && (int) $startRecords[0]['id'] === $alphaNextDay, 'El filtro por fecha inicial no es inclusivo.');

    [$filters, $errors] = $service->validateFilters([
        'producto' => 'Alpha ' . $suffix,
        'fecha_inicio' => '',
        'fecha_fin' => '2026-01-10',
    ]);
    $endRecords = $errors ? [] : $service->search($filters);
    $assert(count($endRecords) === 2, 'El filtro por fecha final no incluyó el día completo.');

    [$filters, $errors] = $service->validateFilters([
        'producto' => (string) $alphaId,
        'fecha_inicio' => '2026-01-10',
        'fecha_fin' => '2026-01-10',
    ]);
    $combinedRecords = $errors ? [] : $service->search($filters);
    $assert(count($combinedRecords) === 2, 'La combinación de producto y rango de fechas no funciona.');

    [$filters, $errors] = $service->validateFilters([
        'producto' => $deletedName,
        'fecha_inicio' => '',
        'fecha_fin' => '',
    ]);
    $deletedRecords = $errors ? [] : $service->search($filters);
    $deletedSnapshot = $connection->prepare(
        'SELECT `id_producto`, `codigo_producto`, `nombre_producto` '
        . 'FROM `historial_precio` WHERE `id` = :id'
    );
    $deletedSnapshot->execute([':id' => $deletedRecordId]);
    $deletedSnapshot = $deletedSnapshot->fetch();
    $assert(count($deletedRecords) === 1, 'El historial del producto eliminado no se pudo consultar por snapshot.');
    $assert(
        $deletedSnapshot['id_producto'] === null
        && (int) $deletedSnapshot['codigo_producto'] === $deletedId
        && $deletedSnapshot['nombre_producto'] === $deletedName,
        'La eliminación del producto no conservó la relación nullable y los snapshots.'
    );

    $emptySearchTerm = 'HU12 inexistente ' . $suffix;
    [$filters, $errors] = $service->validateFilters([
        'producto' => $emptySearchTerm,
        'fecha_inicio' => '',
        'fecha_fin' => '',
    ]);
    $emptyRecords = $errors ? [] : $service->search($filters);
    $assert($emptyRecords === [], 'Una búsqueda sin coincidencias devolvió cambios.');

    [$filters, $errors] = $service->validateFilters([
        'producto' => "' OR 1=1 -- " . $suffix,
        'fecha_inicio' => '',
        'fecha_fin' => '',
    ]);
    $injectionLikeRecords = $errors ? [] : $service->search($filters);
    $assert($injectionLikeRecords === [], 'La búsqueda con caracteres SQL no se trató como un valor literal.');

    [, $invalidDateErrors] = $service->validateFilters([
        'producto' => '',
        'fecha_inicio' => '2026-02-30',
        'fecha_fin' => '',
    ]);
    $assert(isset($invalidDateErrors['fecha_inicio']), 'Se aceptó una fecha inexistente.');
    [, $invalidFormatErrors] = $service->validateFilters([
        'producto' => '',
        'fecha_inicio' => '10/01/2026',
        'fecha_fin' => '',
    ]);
    $assert(isset($invalidFormatErrors['fecha_inicio']), 'Se aceptó un formato de fecha inválido.');
    [, $invertedRangeErrors] = $service->validateFilters([
        'producto' => '',
        'fecha_inicio' => '2026-01-12',
        'fecha_fin' => '2026-01-11',
    ]);
    $assert(isset($invertedRangeErrors['fecha_fin']), 'Se aceptó un rango de fechas invertido.');
    [, $invalidTypeErrors] = $service->validateFilters([
        'producto' => ['no válido'],
        'fecha_inicio' => '',
        'fecha_fin' => '',
    ]);
    $assert(isset($invalidTypeErrors['producto']), 'Se aceptó un filtro de producto con tipo inválido.');

    $nullHistory = $codeRecords[2];
    ob_start();
    $modules = (new App\Services\CatalogService(new App\Repositories\CatalogRepository($connection)))->modules();
    $message = null;
    $messageType = 'success';
    $historyRecords = [$nullHistory];
    $filterErrors = [];
    $historyError = null;
    $filters = ['producto' => '', 'fecha_inicio' => '', 'fecha_fin' => ''];
    require __DIR__ . '/../app/Views/price_history.php';
    $nullPriceHtml = ob_get_clean();
    $assert(str_contains($nullPriceHtml, 'Sin precio anterior'), 'La vista no indicó el precio anterior NULL.');
    $assert(
        str_contains($nullPriceHtml, 'name="modulo" value="historial_precios"'),
        'El formulario GET de HU12 no conserva el identificador de módulo.'
    );

    ob_start();
    $historyRecords = [];
    $filters = ['producto' => $emptySearchTerm, 'fecha_inicio' => '', 'fecha_fin' => ''];
    require __DIR__ . '/../app/Views/price_history.php';
    $emptyHistoryHtml = ob_get_clean();
    $assert(
        str_contains($emptyHistoryHtml, 'No se encontraron cambios de precio'),
        'La vista no explicó que el historial filtrado está vacío.'
    );

    $historyCountDuringReads = (int) $connection->query('SELECT COUNT(*) FROM `historial_precio`')->fetchColumn();
    $assert(
        $historyCountDuringReads === $baselineHistoryCount + 4,
        'Las consultas de historial modificaron los registros.'
    );

} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}

$finalHistoryCount = (int) $connection->query('SELECT COUNT(*) FROM `historial_precio`')->fetchColumn();
$finalProductCount = (int) $connection->query('SELECT COUNT(*) FROM `producto`')->fetchColumn();
$assert(
    $finalHistoryCount === $baselineHistoryCount && $finalProductCount === $baselineProductCount,
    'La prueba dejó filas de historial o productos ficticios persistentes.'
);

echo "Pruebas de historial de precios: {$tests} comprobaciones OK." . PHP_EOL;
