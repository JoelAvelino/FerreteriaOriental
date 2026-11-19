<?php

declare(strict_types=1);

use App\Repositories\SalesRepository;
use App\Services\SalesException;
use App\Services\SalesService;

// Autoloader sencillo para App\... si no existe vendor/autoload.php
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

function assertThrowsSalesException(callable $fn, string $message): void
{
    try {
        $fn();
    } catch (SalesException) {
        return;
    }
    throw new RuntimeException("FALLÓ (se esperaba SalesException): $message");
}

/**
 * Doble en memoria de SalesRepository para pruebas unitarias aisladas.
 */
final class InMemorySalesRepository extends SalesRepository
{
    public array $customers = [1 => ['id' => 1, 'nombre' => 'Cliente Mostrador']];
    public array $paymentMethods = [1 => ['id' => 1, 'nombre' => 'Efectivo']];
    public array $products = [
        10 => ['id' => 10, 'nombre' => 'Martillo 16oz', 'stock' => 5, 'precio_venta' => '150.50'],
        20 => ['id' => 20, 'nombre' => 'Clavo 2in kg', 'stock' => 2, 'precio_venta' => '45.00'],
    ];
    public array $insertedSales = [];
    public array $insertedDetails = [];
    public array $decrements = [];
    public ?array $saleByFolio = ['folio' => 'V-000001', 'total' => '301.00'];
    public bool $failDecrement = false;

    public function __construct()
    {
        // Evita abrir conexión real a BD en pruebas unitarias
    }

    public function formOptions(): array
    {
        return [
            'customers' => $this->customers,
            'payment_methods' => $this->paymentMethods,
            'products' => $this->products,
        ];
    }

    public function listSales(): array
    {
        return $this->insertedSales;
    }

    public function findSaleByFolio(string $folio): ?array
    {
        return $this->saleByFolio;
    }

    public function transaction(callable $callback): mixed
    {
        return $callback();
    }

    public function referenceExists(string $table, int $id): bool
    {
        return match ($table) {
            'cliente' => isset($this->customers[$id]),
            'metodo_pago' => isset($this->paymentMethods[$id]),
            default => false,
        };
    }

    public function lockProducts(array $productIds): array
    {
        $found = [];
        foreach ($productIds as $id) {
            if (isset($this->products[(int) $id])) {
                $found[(int) $id] = $this->products[(int) $id];
            }
        }
        return $found;
    }

    public function insertSale(int $customerId, int $paymentMethodId, string $total): array
    {
        $folio = sprintf('V-%06d', count($this->insertedSales) + 1);
        $sale = [
            'id' => count($this->insertedSales) + 1,
            'folio' => $folio,
            'id_cliente' => $customerId,
            'id_metodo_pago' => $paymentMethodId,
            'total' => $total,
        ];
        $this->insertedSales[] = $sale;
        return $sale;
    }

    public function insertSaleDetail(int $saleId, array $item): void
    {
        $this->insertedDetails[] = ['sale_id' => $saleId] + $item;
    }

    public function decrementStock(int $productId, int $quantity): bool
    {
        if ($this->failDecrement) {
            return false;
        }
        $this->decrements[] = ['id' => $productId, 'cantidad' => $quantity];
        $this->products[$productId]['stock'] -= $quantity;
        return true;
    }
}

$repo = new InMemorySalesRepository();
$service = new SalesService($repo);

// 1. findSaleByFolio: formato válido y formato inválido
$found = $service->findSaleByFolio('  V-000001  ');
assertTrue(is_array($found) && $found['folio'] === 'V-000001', 'Debe encontrar folio válido con espacios recortados');

assertThrowsSalesException(
    static fn () => $service->findSaleByFolio('123'),
    'Folio sin prefijo V- y 6 dígitos debe lanzar SalesException'
);
assertThrowsSalesException(
    static fn () => $service->findSaleByFolio('V-12'),
    'Folio con menos de 6 dígitos debe lanzar SalesException'
);

// 2. validate(): entrada válida
[$clean, $errors] = $service->validate([
    'id_cliente' => '1',
    'id_metodo_pago' => '1',
    'producto_id' => ['10', '20'],
    'cantidad' => ['2', '1'],
]);
assertTrue($errors === [], 'Entrada válida no debe generar errores');
assertTrue($clean['id_cliente'] === 1 && count($clean['items']) === 2, 'Debe normalizar cliente e items');

// 3. validate(): productos duplicados y cantidad inválida
[, $dupErrors] = $service->validate([
    'id_cliente' => '1',
    'id_metodo_pago' => '1',
    'producto_id' => ['10', '10'],
    'cantidad' => ['1', '1'],
]);
assertTrue(isset($dupErrors['productos']), 'Debe marcar error cuando se repite un producto');

[, $qtyErrors] = $service->validate([
    'id_cliente' => '1',
    'id_metodo_pago' => '1',
    'producto_id' => ['10'],
    'cantidad' => ['0'],
]);
assertTrue(isset($qtyErrors['productos']), 'Debe marcar error cuando la cantidad es 0');

// 4. register(): venta exitosa calcula total y descuenta stock
$folio = $service->register([
    'id_cliente' => 1,
    'id_metodo_pago' => 1,
    'items' => [
        ['id' => 10, 'cantidad' => 2], // 2 * 150.50 = 301.00
        ['id' => 20, 'cantidad' => 1], // 1 * 45.00  = 45.00 -> total 346.00
    ],
]);
assertTrue($folio === 'V-000001', 'Debe regresar el folio generado');
assertTrue($repo->insertedSales[0]['total'] === '346.00', 'Total calculado debe ser 346.00');
assertTrue($repo->products[10]['stock'] === 3, 'Stock del producto 10 debe bajar de 5 a 3');
assertTrue($repo->products[20]['stock'] === 1, 'Stock del producto 20 debe bajar de 2 a 1');

// 5. register(): stock insuficiente lanza SalesException
assertThrowsSalesException(
    static fn () => $service->register([
        'id_cliente' => 1,
        'id_metodo_pago' => 1,
        'items' => [['id' => 20, 'cantidad' => 50]],
    ]),
    'Cantidad mayor al stock disponible debe lanzar SalesException'
);

// 6. register(): cliente inexistente lanza SalesException
assertThrowsSalesException(
    static fn () => $service->register([
        'id_cliente' => 999,
        'id_metodo_pago' => 1,
        'items' => [['id' => 10, 'cantidad' => 1]],
    ]),
    'Cliente inexistente debe lanzar SalesException'
);

echo "OK: Todas las pruebas unitarias de SalesService (backend) pasaron correctamente.\n";