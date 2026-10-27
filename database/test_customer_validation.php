<?php

use App\Repositories\CatalogRepository;
use App\Services\CatalogService;

require __DIR__ . '/../app/Repositories/CatalogRepository.php';
require __DIR__ . '/../app/Services/CatalogService.php';

class CustomerValidationRepository extends CatalogRepository
{
    public function __construct()
    {
    }

    public function clientPhoneExists(string $phone): bool
    {
        return in_array($phone, ['+15550999', '+15550100'], true);
    }

    public function clientEmailExists(string $email): bool
    {
        return $email === 'duplicado@example.test';
    }

    public function paymentMethodNameExists(string $name): bool
    {
        return $name === 'Método duplicado';
    }
}

$service = new CatalogService(new CustomerValidationRepository());
$tests = 0;

$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

[$validValues, $validErrors] = $service->validate('clientes', [
    'nombre' => 'Cliente de prueba',
    'telefono' => '+1 555-0111',
    'correo' => 'cliente@example.test',
    'direccion' => 'Dirección ficticia de prueba',
]);
$assert($validErrors === [], 'Se rechazaron datos válidos de cliente.');
$assert($validValues['nombre'] === 'Cliente de prueba', 'No se conservó el nombre validado.');
$assert($validValues['telefono'] === '+15550111', 'No se normalizó el teléfono conservando el prefijo +.');

foreach (['José María', 'Ana López', 'María José', 'Ángel Núñez', '  José María  '] as $index => $validName) {
    [$nameValues, $nameErrors] = $service->validate('clientes', [
        'nombre' => $validName,
        'telefono' => '',
        'correo' => 'nombre-valido-' . $index . '@example.test',
        'direccion' => '',
    ]);
    $assert($nameErrors === [], 'Se rechazó un nombre válido: ' . $validName);
    $assert($nameValues['nombre'] === trim($validName), 'No se recortaron los espacios sobrantes del nombre.');
}

$contactCases = [
    [
        'correo' => 'solo-correo@example.test',
        'telefono' => '',
        'expectedPhone' => null,
        'expectedEmail' => 'solo-correo@example.test',
    ],
    [
        'correo' => '',
        'telefono' => '+1 555-0123',
        'expectedPhone' => '+15550123',
        'expectedEmail' => null,
    ],
    [
        'correo' => 'ambos@example.test',
        'telefono' => '+1 555-0124',
        'expectedPhone' => '+15550124',
        'expectedEmail' => 'ambos@example.test',
    ],
    [
        'correo' => '  solo-trim@example.test  ',
        'telefono' => '   ',
        'expectedPhone' => null,
        'expectedEmail' => 'solo-trim@example.test',
    ],
    [
        'correo' => '',
        'telefono' => '  +1 555-0125  ',
        'expectedPhone' => '+15550125',
        'expectedEmail' => null,
    ],
];
foreach ($contactCases as $contactCase) {
    [$contactValues, $contactErrors] = $service->validate('clientes', [
        'nombre' => 'Ana López',
        'telefono' => $contactCase['telefono'],
        'correo' => $contactCase['correo'],
        'direccion' => '',
    ]);
    $assert($contactErrors === [], 'Se rechazó una combinación válida de correo/teléfono.');
    $assert($contactValues['telefono'] === $contactCase['expectedPhone'], 'No se normalizó/recortó el teléfono esperado.');
    $assert($contactValues['correo'] === $contactCase['expectedEmail'], 'No se recortó/conservó el correo esperado.');
}

foreach ([['', ''], ['   ', '    ']] as [$email, $phone]) {
    [, $contactErrors] = $service->validate('clientes', [
        'nombre' => 'Ana López',
        'telefono' => $phone,
        'correo' => $email,
        'direccion' => '',
    ]);
    $assert(
        ($contactErrors['_form'] ?? null) === 'Debes proporcionar un correo electrónico o un número telefónico como mínimo',
        'Se aceptó el cliente sin correo ni teléfono o faltó el mensaje requerido.'
    );
}

foreach (['Juan123', 'Pedro@', 'Ana-López', 'María_2', '12345', 'Juan!'] as $invalidName) {
    [, $nameErrors] = $service->validate('clientes', [
        'nombre' => $invalidName,
        'telefono' => '',
        'correo' => '',
        'direccion' => '',
    ]);
    $assert(
        isset($nameErrors['nombre']) && str_contains($nameErrors['nombre'], 'solo puede contener letras y espacios'),
        'Se aceptó el nombre inválido: ' . $invalidName
    );
}

[, $sameNameErrors] = $service->validate('clientes', [
    'nombre' => 'Cliente de prueba',
    'telefono' => '+1 555-0101',
    'correo' => 'otro@example.test',
    'direccion' => '',
]);
$assert($sameNameErrors === [], 'Se rechazó un nombre repetido con contacto distinto.');

[, $missingNameErrors] = $service->validate('clientes', [
    'nombre' => ' ',
    'telefono' => '',
    'correo' => '',
    'direccion' => '',
]);
$assert(isset($missingNameErrors['nombre']), 'Se aceptó un nombre vacío.');
$assert($missingNameErrors['nombre'] === 'El nombre del cliente es obligatorio.', 'El mensaje de nombre vacío no es claro.');

[, $invalidPhoneErrors] = $service->validate('clientes', [
    'nombre' => 'Cliente de prueba',
    'telefono' => '123',
    'correo' => '',
    'direccion' => '',
]);
$assert(isset($invalidPhoneErrors['telefono']), 'Se aceptó un teléfono con formato inválido.');

[, $invalidPhonePrefixErrors] = $service->validate('clientes', [
    'nombre' => 'Cliente de prueba',
    'telefono' => '123+4567',
    'correo' => '',
    'direccion' => '',
]);
$assert(isset($invalidPhonePrefixErrors['telefono']), 'Se aceptó un prefijo + fuera del inicio del teléfono.');

[, $invalidEmailErrors] = $service->validate('clientes', [
    'nombre' => 'Cliente de prueba',
    'telefono' => '',
    'correo' => 'correo-invalido',
    'direccion' => '',
]);
$assert(isset($invalidEmailErrors['correo']), 'Se aceptó un correo con formato inválido.');

[, $invalidEmailOnlyErrors] = $service->validate('clientes', [
    'nombre' => 'Cliente de prueba',
    'telefono' => '   ',
    'correo' => 'correo-invalido',
    'direccion' => '',
]);
$assert(isset($invalidEmailOnlyErrors['correo']), 'Se aceptó un correo inválido sin teléfono.');

[, $invalidPhoneOnlyErrors] = $service->validate('clientes', [
    'nombre' => 'Cliente de prueba',
    'telefono' => '123',
    'correo' => '   ',
    'direccion' => '',
]);
$assert(isset($invalidPhoneOnlyErrors['telefono']), 'Se aceptó un teléfono inválido sin correo.');

[, $duplicatePhoneErrors] = $service->validate('clientes', [
    'nombre' => 'Otro cliente',
    'telefono' => '+1 (555) 0999',
    'correo' => 'otro@example.test',
    'direccion' => '',
]);
$assert(isset($duplicatePhoneErrors['telefono']), 'No se rechazó un teléfono de cliente duplicado.');

[, $formattedDuplicatePhoneErrors] = $service->validate('clientes', [
    'nombre' => 'Otro cliente',
    'telefono' => '+1 (555) 0100',
    'correo' => 'distinto@example.test',
    'direccion' => '',
]);
$assert(isset($formattedDuplicatePhoneErrors['telefono']), 'No se detectó el mismo teléfono con formato distinto.');

[, $duplicateEmailErrors] = $service->validate('clientes', [
    'nombre' => 'Otro cliente',
    'telefono' => '+1 555-0101',
    'correo' => 'duplicado@example.test',
    'direccion' => '',
]);
$assert(isset($duplicateEmailErrors['correo']), 'No se rechazó un correo de cliente duplicado.');

[$paymentValues, $paymentErrors] = $service->validate('metodos_pago', [
    'nombre' => 'Efectivo',
    'descripcion' => 'Método de prueba',
]);
$assert($paymentErrors === [], 'Se rechazaron datos válidos del método de pago.');
$assert($paymentValues['nombre'] === 'Efectivo', 'No se conservó el nombre del método.');

[, $missingPaymentNameErrors] = $service->validate('metodos_pago', [
    'nombre' => ' ',
    'descripcion' => '',
]);
$assert(isset($missingPaymentNameErrors['nombre']), 'Se aceptó un método de pago sin nombre.');

[, $duplicatePaymentErrors] = $service->validate('metodos_pago', [
    'nombre' => 'Método duplicado',
    'descripcion' => '',
]);
$assert(isset($duplicatePaymentErrors['nombre']), 'No se rechazó un método de pago duplicado.');

echo "Pruebas de validación HU07/HU09: {$tests} comprobaciones OK." . PHP_EOL;
