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
$suffix = strtr(bin2hex(random_bytes(5)), '0123456789abcdef', 'abcdefghijklmnop');
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
        'nombre' => 'Cliente de prueba ' . $suffix,
        'telefono' => '+1 555-0100',
        'correo' => 'cliente-' . $suffix . '@example.test',
        'direccion' => 'Dirección ficticia de prueba',
    ];
    [$values, $errors] = $service->validate('clientes', $input);
    $assert($errors === [], 'La validación rechazó datos válidos de cliente.');

    $service->create('clientes', $values);
    $customers = $service->list('clientes');
    $savedCustomer = array_values(array_filter(
        $customers,
        static fn (array $customer): bool => $customer['nombre'] === $input['nombre']
    ));
    $assert(count($savedCustomer) === 1, 'El cliente guardado no apareció en el listado.');
    $assert($savedCustomer[0]['telefono'] === $values['telefono'], 'No se persistió el teléfono normalizado.');
    $assert($savedCustomer[0]['correo'] === $input['correo'], 'No se persistió el correo.');
    $assert($savedCustomer[0]['direccion'] === $input['direccion'], 'No se persistió la dirección.');

    [, $formattedPhoneErrors] = $service->validate('clientes', [
        'nombre' => 'Cliente teléfono con formato distinto ' . $suffix,
        'telefono' => '+1 (555) 0100',
        'correo' => 'telefono-distinto-' . $suffix . '@example.test',
        'direccion' => '',
    ]);
    $assert(isset($formattedPhoneErrors['telefono']), 'No se detectó el teléfono duplicado con puntuación distinta.');

    [, $emailCaseErrors] = $service->validate('clientes', [
        'nombre' => 'Cliente correo con mayúsculas ' . $suffix,
        'telefono' => '+1 555-0200',
        'correo' => strtoupper($input['correo']),
        'direccion' => '',
    ]);
    $assert(isset($emailCaseErrors['correo']), 'No se detectó el correo duplicado sin distinguir mayúsculas.');

    [, $duplicateErrors] = $service->validate('clientes', $input);
    $assert(
        isset($duplicateErrors['telefono']) && isset($duplicateErrors['correo']),
        'No se rechazaron los datos de contacto duplicados.'
    );

    $databaseEmailDuplicateRejected = false;
    try {
        $service->create('clientes', array_merge($values, [
            'nombre' => 'Cliente distinto ' . $suffix,
            'telefono' => '+1 555-0200',
        ]));
    } catch (RuntimeException $exception) {
        $databaseEmailDuplicateRejected = $exception->getMessage() === 'El correo o teléfono ya está registrado.';
    }
    $assert($databaseEmailDuplicateRejected, 'La restricción única de correo no rechazó el dato duplicado.');

    $databasePhoneDuplicateRejected = false;
    try {
        $service->create('clientes', array_merge($values, [
            'nombre' => 'Otro cliente ' . $suffix,
            'correo' => 'otro-' . $suffix . '@example.test',
        ]));
    } catch (RuntimeException $exception) {
        $databasePhoneDuplicateRejected = $exception->getMessage() === 'El correo o teléfono ya está registrado.';
    }
    $assert($databasePhoneDuplicateRejected, 'La restricción única de teléfono no rechazó el dato duplicado.');

    $sameNameInput = array_merge($input, [
        'telefono' => '+1 555-0201',
        'correo' => 'mismo-nombre-' . $suffix . '@example.test',
    ]);
    [$sameNameValues, $sameNameErrors] = $service->validate('clientes', $sameNameInput);
    $assert($sameNameErrors === [], 'Se rechazó un nombre repetido con correo y teléfono distintos.');
    $service->create('clientes', $sameNameValues);
    $sameNameCount = count(array_filter(
        $service->list('clientes'),
        static fn (array $customer): bool => $customer['nombre'] === $input['nombre']
    ));
    $assert($sameNameCount === 2, 'No se permitieron clientes con el mismo nombre y contactos diferentes.');

    [, $missingContactErrors] = $service->validate('clientes', [
        'nombre' => 'Cliente opcional ' . $suffix,
        'telefono' => '',
        'correo' => '',
        'direccion' => '',
    ]);
    $assert(
        ($missingContactErrors['_form'] ?? null) === 'Debes proporcionar un correo electrónico o un número telefónico como mínimo',
        'Se aceptó un cliente sin correo ni teléfono.'
    );

    [$emailOnlyValues, $emailOnlyErrors] = $service->validate('clientes', [
        'nombre' => 'Cliente solo correo ' . $suffix,
        'telefono' => '   ',
        'correo' => 'solo-correo-' . $suffix . '@example.test',
        'direccion' => '',
    ]);
    $assert($emailOnlyErrors === [], 'Se rechazó un cliente con solo correo.');
    $assert(
        $emailOnlyValues['telefono'] === null
            && $emailOnlyValues['correo'] === 'solo-correo-' . $suffix . '@example.test'
            && $emailOnlyValues['direccion'] === null,
        'El contacto opcional/email-only no quedó normalizado correctamente.'
    );

    echo "Pruebas de persistencia de clientes: {$tests} comprobaciones OK." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
