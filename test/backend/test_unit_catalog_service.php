<?php

declare(strict_types=1);

use App\Repositories\CatalogRepository;
use App\Services\CatalogService;

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        $file = __DIR__ . '/../../app/' . $relative;
        if (file_exists($file)) {
            require_once $file;
        }
    });
}

assert(true);
ini_set('zend.assertions', '1');
ini_set('assert.exception', '1');

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FALLÓ: $message");
    }
}

function assertThrows(string $exceptionClass, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $exceptionClass) {
            return;
        }
        throw new RuntimeException("FALLÓ: se esperaba $exceptionClass pero llegó " . $e::class . " ($message)");
    }
    throw new RuntimeException("FALLÓ (se esperaba $exceptionClass): $message");
}

final class InMemoryCatalogRepository extends CatalogRepository
{
    public array $existingRefs = [
        'id_categoria' => [1 => true],
        'id_marca' => [1 => true],
        'id_proveedor' => [1 => true],
    ];
    public array $phones = [];
    public array $emails = [];
    public array $paymentMethods = [];
    public array $created = [];
    public array $deleted = [];
    public ?PDOException $nextDeleteException = null;

    public function __construct()
    {
        // Evita abrir conexión real a BD en pruebas unitarias
    }

    public function list(string $table, array $columns): array
    {
        return [];
    }

    public function referenceOptions(string $field): array
    {
        return [];
    }

    public function referenceExists(string $field, int $id): bool
    {
        return isset($this->existingRefs[$field][$id]);
    }

    public function searchProducts(array $filters): array
    {
        return [];
    }

    public function findProduct(int $id): ?array
    {
        return null;
    }

    public function clientPhoneExists(string $phone): bool
    {
        return isset($this->phones[$phone]);
    }

    public function clientEmailExists(string $email): bool
    {
        return isset($this->emails[$email]);
    }

    public function paymentMethodNameExists(string $name): bool
    {
        return isset($this->paymentMethods[mb_strtolower($name)]);
    }

    public function create(string $table, array $values): void
    {
        $this->created[] = ['table' => $table, 'values' => $values];
    }

    public function updateProduct(int $id, array $values): bool
    {
        return true;
    }

    public function delete(string $table, int $id): bool
    {
        if ($this->nextDeleteException !== null) {
            $ex = $this->nextDeleteException;
            $this->nextDeleteException = null;
            throw $ex;
        }
        $this->deleted[] = ['table' => $table, 'id' => $id];
        return true;
    }
}

$repo = new InMemoryCatalogRepository();
$service = new CatalogService($repo);

// 1. Módulos existentes e inexistentes
assertTrue($service->getModule('categorias') !== null, 'Debe encontrar el módulo categorias');
assertTrue($service->getModule('no_existe') === null, 'Debe regresar null en módulo inexistente');
assertThrows(
    InvalidArgumentException::class,
    static fn () => $service->list('no_existe'),
    'Listar un módulo inexistente debe lanzar InvalidArgumentException'
);

// 2. Validación de cliente: exige teléfono o correo y normaliza teléfono
[, $noContactErrors] = $service->validate('clientes', [
    'nombre' => 'Ana López',
    'telefono' => '',
    'correo' => '',
    'direccion' => 'Centro',
]);
assertTrue(isset($noContactErrors['_form']), 'Cliente sin teléfono ni correo debe marcar error _form');

[$clientValues, $clientErrors] = $service->validate('clientes', [
    'nombre' => 'Ana López',
    'telefono' => '+52 (951) 123-4567',
    'correo' => '',
    'direccion' => 'Centro',
]);
assertTrue($clientErrors === [], 'Cliente con teléfono válido no debe tener errores');
assertTrue($clientValues['telefono'] === '+529511234567', 'Debe normalizar el teléfono conservando + inicial');

// 3. Validación de método de pago duplicado
$repo->paymentMethods['efectivo'] = true;
[, $payErrors] = $service->validate('metodos_pago', [
    'nombre' => 'Efectivo',
    'descripcion' => 'Pago en caja',
]);
assertTrue(isset($payErrors['nombre']), 'Debe rechazar nombre duplicado en metodos_pago');

// 4. Validación de filtros de producto con referencia inexistente
[, $filterErrors] = $service->validateProductFilters([
    'busqueda' => '  taladro  ',
    'id_categoria' => '999',
]);
assertTrue(isset($filterErrors['id_categoria']), 'Debe rechazar id_categoria inexistente en filtros');

// 5. Eliminación exitosa y restricción por llave foránea (SQLSTATE 23000 / 1451)
$deletedOk = $service->delete('categorias', 1);
assertTrue($deletedOk === true && count($repo->deleted) === 1, 'Debe eliminar categoría con id válido');

$fkException = new PDOException('Cannot delete or update a parent row', 23000);
$fkException->errorInfo = ['23000', 1451, 'a foreign key constraint fails'];
$repo->nextDeleteException = $fkException;

assertThrows(
    RuntimeException::class,
    static fn () => $service->delete('categorias', 1),
    'Eliminar categoría con productos asociados debe lanzar RuntimeException'
);

echo "OK: Todas las pruebas unitarias de CatalogService (backend) pasaron correctamente.\n";