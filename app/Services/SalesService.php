<?php

namespace App\Services;

use App\Repositories\SalesRepository;
use RuntimeException;

class SalesException extends RuntimeException
{
}

class SalesService
{
    private const MAX_TOTAL_CENTS = 999999999999;

    private SalesRepository $repository;

    public function __construct(SalesRepository $repository)
    {
        $this->repository = $repository;
    }

    public function formOptions(): array
    {
        return $this->repository->formOptions();
    }

    public function validate(array $input): array
    {
        $errors = [];
        $rawCustomerId = $input['id_cliente'] ?? null;
        $customerId = is_scalar($rawCustomerId)
            ? filter_var((string) $rawCustomerId, FILTER_VALIDATE_INT)
            : false;
        if ($customerId === false || $customerId < 1) {
            $errors['id_cliente'] = 'Selecciona un cliente válido.';
        }

        $rawPaymentMethodId = $input['id_metodo_pago'] ?? null;
        $paymentMethodId = is_scalar($rawPaymentMethodId)
            ? filter_var((string) $rawPaymentMethodId, FILTER_VALIDATE_INT)
            : false;
        if ($paymentMethodId === false || $paymentMethodId < 1) {
            $errors['id_metodo_pago'] = 'Selecciona un método de pago válido.';
        }

        $productIds = $input['producto_id'] ?? null;
        $quantities = $input['cantidad'] ?? null;
        if (!is_array($productIds) || !is_array($quantities)) {
            $errors['productos'] = 'Agrega al menos un producto a la venta.';
            $productIds = [];
            $quantities = [];
        } elseif (count($productIds) !== count($quantities)) {
            $errors['productos'] = 'Cada producto debe tener una cantidad.';
        }

        if ($productIds === []) {
            $errors['productos'] = 'La venta debe incluir al menos un producto.';
        }

        $items = [];
        $seenProducts = [];
        foreach ($productIds as $index => $rawProductId) {
            $productId = is_scalar($rawProductId)
                ? filter_var((string) $rawProductId, FILTER_VALIDATE_INT)
                : false;
            $rawQuantity = $quantities[$index] ?? null;
            $quantity = is_scalar($rawQuantity)
                ? filter_var((string) $rawQuantity, FILTER_VALIDATE_INT)
                : false;

            if ($productId === false || $productId < 1) {
                $errors['productos'] = 'Selecciona un producto válido en cada línea.';
                continue;
            }
            if ($quantity === false || $quantity < 1 || $quantity > 2147483647) {
                $errors['productos'] = 'La cantidad de cada producto debe ser un entero mayor que cero.';
                continue;
            }
            if (isset($seenProducts[$productId])) {
                $errors['productos'] = 'No repitas un producto en varias líneas.';
                continue;
            }

            $seenProducts[$productId] = true;
            $items[] = ['id' => $productId, 'cantidad' => $quantity];
        }

        return [
            [
                'id_cliente' => $customerId === false ? null : $customerId,
                'id_metodo_pago' => $paymentMethodId === false ? null : $paymentMethodId,
                'items' => $items,
            ],
            $errors,
        ];
    }

    public function register(array $values): string
    {
        $rawCustomerId = $values['id_cliente'] ?? null;
        $rawPaymentMethodId = $values['id_metodo_pago'] ?? null;
        $customerId = is_scalar($rawCustomerId) ? filter_var($rawCustomerId, FILTER_VALIDATE_INT) : false;
        $paymentMethodId = is_scalar($rawPaymentMethodId) ? filter_var($rawPaymentMethodId, FILTER_VALIDATE_INT) : false;
        if ($customerId === false || $customerId < 1 || $paymentMethodId === false || $paymentMethodId < 1) {
            throw new SalesException('Selecciona un cliente y método de pago válidos.');
        }
        if (!is_array($values['items'] ?? null) || $values['items'] === []) {
            throw new SalesException('Completa los datos de la venta e incluye al menos un producto.');
        }

        $items = [];
        $seenProducts = [];
        foreach ($values['items'] as $item) {
            if (!is_array($item)) {
                throw new SalesException('La lista de productos no tiene un formato válido.');
            }

            $rawProductId = $item['id'] ?? null;
            $rawQuantity = $item['cantidad'] ?? null;
            $productId = is_scalar($rawProductId) ? filter_var($rawProductId, FILTER_VALIDATE_INT) : false;
            $quantity = is_scalar($rawQuantity) ? filter_var($rawQuantity, FILTER_VALIDATE_INT) : false;
            if ($productId === false || $productId < 1 || $quantity === false || $quantity < 1 || $quantity > 2147483647) {
                throw new SalesException('Cada producto debe tener una cantidad entera mayor que cero.');
            }
            if (isset($seenProducts[$productId])) {
                throw new SalesException('No repitas un producto en varias líneas.');
            }
            $seenProducts[$productId] = true;
            $items[] = ['id' => $productId, 'cantidad' => $quantity];
        }
        $values = [
            'id_cliente' => $customerId,
            'id_metodo_pago' => $paymentMethodId,
            'items' => $items,
        ];

        return $this->repository->transaction(function () use ($values): string {
            $customerId = (int) $values['id_cliente'];
            $paymentMethodId = (int) $values['id_metodo_pago'];
            if (!$this->repository->referenceExists('cliente', $customerId)) {
                throw new SalesException('El cliente seleccionado ya no existe. Recarga el formulario.');
            }
            if (!$this->repository->referenceExists('metodo_pago', $paymentMethodId)) {
                throw new SalesException('El método de pago seleccionado ya no existe. Recarga el formulario.');
            }

            $productIds = array_column($values['items'], 'id');
            $products = $this->repository->lockProducts($productIds);
            if (count($products) !== count($productIds)) {
                throw new SalesException('Uno de los productos seleccionados ya no existe. Recarga el formulario.');
            }

            $items = [];
            $totalCents = 0;
            foreach ($values['items'] as $requestedItem) {
                $productId = (int) $requestedItem['id'];
                $quantity = (int) $requestedItem['cantidad'];
                $product = $products[$productId];
                $stock = (int) $product['stock'];

                if ($quantity < 1 || $quantity > $stock) {
                    throw new SalesException(
                        'Existencias insuficientes para ' . $product['nombre']
                        . '. Disponibles: ' . max(0, $stock) . '.'
                    );
                }

                $priceCents = $this->priceToCents($product['precio_venta']);
                if ($priceCents <= 0) {
                    throw new SalesException(
                        'El producto ' . $product['nombre']
                        . ' no tiene un precio de venta válido mayor que cero.'
                    );
                }
                if ($priceCents > intdiv(self::MAX_TOTAL_CENTS, $quantity)) {
                    throw new SalesException('El importe de la venta excede el límite permitido.');
                }

                $subtotalCents = $priceCents * $quantity;
                if ($totalCents > self::MAX_TOTAL_CENTS - $subtotalCents) {
                    throw new SalesException('El importe total de la venta excede el límite permitido.');
                }
                $totalCents += $subtotalCents;
                $items[] = [
                    'id' => $productId,
                    'cantidad' => $quantity,
                    'precio_unitario' => $this->formatCents($priceCents),
                    'subtotal' => $this->formatCents($subtotalCents),
                ];
            }

            $sale = $this->repository->insertSale(
                $customerId,
                $paymentMethodId,
                $this->formatCents($totalCents)
            );
            foreach ($items as $item) {
                $this->repository->insertSaleDetail($sale['id'], $item);
                if (!$this->repository->decrementStock($item['id'], $item['cantidad'])) {
                    throw new SalesException('El stock cambió durante el registro. No se guardó la venta; inténtalo nuevamente.');
                }
            }

            return $sale['folio'];
        });
    }

    private function priceToCents(?string $price): int
    {
        if ($price === null || !preg_match('/^\d+(?:\.\d{1,2})?$/D', $price)) {
            return 0;
        }

        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        $whole = ltrim($whole, '0');
        if ($whole === '') {
            $whole = '0';
        }
        if (strlen($whole) > 8) {
            return 0;
        }

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function formatCents(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
