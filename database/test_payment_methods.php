<?php

use App\Database;
use App\Repositories\CatalogRepository;
use App\Services\CatalogService;

require __DIR__ . '/../app/Database.php';
require __DIR__ . '/../app/Repositories/CatalogRepository.php';
require __DIR__ . '/../app/Services/CatalogService.php';

$config = require __DIR__ . '/../config/database.php';
$connection = (new Database($config))->getConnection();
$service = new CatalogService(new CatalogRepository($connection));
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
    $input = [
        'nombre' => 'Método de prueba ' . $suffix,
        'descripcion' => 'Descripción ficticia de prueba',
    ];
    [$values, $errors] = $service->validate('metodos_pago', $input);
    $assert($errors === [], 'La validación rechazó datos válidos del método de pago.');

    $service->create('metodos_pago', $values);
    $records = $service->list('metodos_pago');
    $saved = array_values(array_filter(
        $records,
        static fn (array $record): bool => $record['nombre'] === $input['nombre']
    ));
    $assert(count($saved) === 1, 'El método de pago guardado no apareció en el listado.');
    $assert($saved[0]['descripcion'] === $input['descripcion'], 'No se persistió la descripción.');

    [, $duplicateErrors] = $service->validate('metodos_pago', $input);
    $assert(isset($duplicateErrors['nombre']), 'No se rechazó el nombre de método duplicado.');

    $databaseDuplicateRejected = false;
    try {
        $service->create('metodos_pago', $values);
    } catch (RuntimeException $exception) {
        $databaseDuplicateRejected = $exception->getMessage() === 'Ya existe un registro con ese nombre.';
    }
    $assert($databaseDuplicateRejected, 'La restricción única de MySQL no rechazó el nombre duplicado.');

    [$optionalValues, $optionalErrors] = $service->validate('metodos_pago', [
        'nombre' => 'Método opcional ' . $suffix,
        'descripcion' => '',
    ]);
    $assert($optionalErrors === [] && $optionalValues['descripcion'] === null, 'No se permitió la descripción opcional.');

    echo "Pruebas de persistencia HU09: {$tests} comprobaciones OK." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
