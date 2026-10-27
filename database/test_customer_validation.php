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
        return in_array($phone, ['9511234567', '9511234568'], true);
    }

    public function clientEmailExists(string $email): bool
    {
        return $email === 'duplicado@gmail.com';
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
    'telefono' => '9511234569',
    'correo' => 'cliente@gmail.com',
    'direccion' => 'Dirección ficticia de prueba',
]);
$assert($validErrors === [], 'Se rechazaron datos válidos de cliente.');
$assert($validValues['nombre'] === 'Cliente de prueba', 'No se conservó el nombre validado.');
$assert($validValues['telefono'] === '9511234569', 'No se conservó el teléfono de 10 dígitos.');

foreach (['José María', 'Ana López', 'María José', 'Ángel Núñez', '  José María  '] as $index => $validName) {
    [$nameValues, $nameErrors] = $service->validate('clientes', [
        'nombre' => $validName,
        'telefono' => '',
        'correo' => 'nombre-valido-' . $index . '@gmail.com',
        'direccion' => '',
    ]);
    $assert($nameErrors === [], 'Se rechazó un nombre válido: ' . $validName);
    $assert($nameValues['nombre'] === trim($validName), 'No se recortaron los espacios sobrantes del nombre.');
}

$contactCases = [
    [
        'correo' => 'solo-correo@gmail.com',
        'telefono' => '',
        'expectedPhone' => null,
        'expectedEmail' => 'solo-correo@gmail.com',
    ],
    [
        'correo' => '',
        'telefono' => '9511234001',
        'expectedPhone' => '9511234001',
        'expectedEmail' => null,
    ],
    [
        'correo' => 'ambos@gmail.com',
        'telefono' => '9511234002',
        'expectedPhone' => '9511234002',
        'expectedEmail' => 'ambos@gmail.com',
    ],
    [
        'correo' => '  solo-trim@gmail.com  ',
        'telefono' => '   ',
        'expectedPhone' => null,
        'expectedEmail' => 'solo-trim@gmail.com',
    ],
    [
        'correo' => '',
        'telefono' => '9511234003',
        'expectedPhone' => '9511234003',
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
    'telefono' => '9511234010',
    'correo' => 'otro@gmail.com',
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
    'telefono' => '123+456789',
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
    'telefono' => '9511234567',
    'correo' => 'otro@gmail.com',
    'direccion' => '',
]);
$assert(isset($duplicatePhoneErrors['telefono']), 'No se rechazó un teléfono de cliente duplicado.');

[, $formattedDuplicatePhoneErrors] = $service->validate('clientes', [
    'nombre' => 'Otro cliente',
    'telefono' => '951 123 4568',
    'correo' => 'distinto@gmail.com',
    'direccion' => '',
]);
$assert(isset($formattedDuplicatePhoneErrors['telefono']), 'No se rechazó el teléfono con espacios.');

[, $duplicateEmailErrors] = $service->validate('clientes', [
    'nombre' => 'Otro cliente',
    'telefono' => '9511234011',
    'correo' => 'duplicado@gmail.com',
    'direccion' => '',
]);
$assert(isset($duplicateEmailErrors['correo']), 'No se rechazó un correo de cliente duplicado.');

foreach ([
    'cliente@hotmail.com',
    'cliente@outlook.com',
    'cliente@gmail',
    'cliente@gmail.com.mx',
    'correo-invalido',
    'cliente@GMAIL.COM',
] as $invalidEmail) {
    [, $emailErrors] = $service->validate('clientes', [
        'nombre' => 'Cliente con correo inválido',
        'telefono' => '',
        'correo' => $invalidEmail,
        'direccion' => '',
    ]);
    $assert(isset($emailErrors['correo']), 'Se aceptó el correo inválido: ' . $invalidEmail);
}

foreach (['951123456', '95112345678', '95112AB567', '951 123 4567', ' 9511234567', '9511234567 ', '+529511234567'] as $invalidPhone) {
    [, $phoneErrors] = $service->validate('clientes', [
        'nombre' => 'Cliente con teléfono inválido',
        'telefono' => $invalidPhone,
        'correo' => 'valido@gmail.com',
        'direccion' => '',
    ]);
    $assert(isset($phoneErrors['telefono']), 'Se aceptó el teléfono inválido: ' . $invalidPhone);
}

[, $invalidEmailWithValidPhoneErrors] = $service->validate('clientes', [
    'nombre' => 'Cliente con contacto parcial inválido',
    'telefono' => '9511234020',
    'correo' => 'cliente@hotmail.com',
    'direccion' => '',
]);
$assert(isset($invalidEmailWithValidPhoneErrors['correo']), 'Se aceptó correo inválido junto con teléfono válido.');

[, $invalidPhoneWithValidEmailErrors] = $service->validate('clientes', [
    'nombre' => 'Cliente con contacto parcial inválido',
    'telefono' => '9511234021 ',
    'correo' => 'valido@gmail.com',
    'direccion' => '',
]);
$assert(isset($invalidPhoneWithValidEmailErrors['telefono']), 'Se aceptó teléfono inválido junto con correo válido.');

[$validSupplierValues, $validSupplierErrors] = $service->validate('proveedores', [
    'nombre' => 'Proveedor de prueba',
    'contacto' => 'Contacto de prueba',
    'telefono' => '9511234022',
    'correo' => 'proveedor@gmail.com',
]);
$assert($validSupplierErrors === [], 'Se rechazaron datos válidos de proveedor.');
$assert($validSupplierValues['telefono'] === '9511234022', 'No se conservó el teléfono válido del proveedor.');

foreach (['proveedor@hotmail.com', 'proveedor@gmail', 'proveedor@gmail.com.mx', 'correo-invalido'] as $invalidEmail) {
    [, $supplierEmailErrors] = $service->validate('proveedores', [
        'nombre' => 'Proveedor con correo inválido',
        'contacto' => 'Contacto de prueba',
        'telefono' => '9511234024',
        'correo' => $invalidEmail,
    ]);
    $assert(isset($supplierEmailErrors['correo']), 'Se aceptó el correo inválido del proveedor: ' . $invalidEmail);
}

foreach (['951123456', '95112345678', '95112AB567', '951 123 4567', '+529511234567'] as $invalidPhone) {
    [, $supplierPhoneErrors] = $service->validate('proveedores', [
        'nombre' => 'Proveedor con teléfono inválido',
        'contacto' => 'Contacto de prueba',
        'telefono' => $invalidPhone,
        'correo' => 'valido@gmail.com',
    ]);
    $assert(isset($supplierPhoneErrors['telefono']), 'Se aceptó el teléfono inválido del proveedor: ' . $invalidPhone);
}

foreach ([
    ['telefono' => '', 'correo' => 'proveedor@gmail.com', 'field' => 'telefono'],
    ['telefono' => '9511234023', 'correo' => '', 'field' => 'correo'],
    ['telefono' => '   ', 'correo' => 'proveedor@gmail.com', 'field' => 'telefono'],
    ['telefono' => '9511234023', 'correo' => '   ', 'field' => 'correo'],
    ['telefono' => '951 123 4023', 'correo' => 'proveedor@gmail.com', 'field' => 'telefono'],
    ['telefono' => '9511234023', 'correo' => 'proveedor@outlook.com', 'field' => 'correo'],
] as $supplierCase) {
    [, $supplierErrors] = $service->validate('proveedores', [
        'nombre' => 'Proveedor de prueba',
        'contacto' => 'Contacto de prueba',
        'telefono' => $supplierCase['telefono'],
        'correo' => $supplierCase['correo'],
    ]);
    $assert(
        isset($supplierErrors[$supplierCase['field']]),
        'No se rechazó el dato inválido del proveedor: ' . $supplierCase['field']
    );
}

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
