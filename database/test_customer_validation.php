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
