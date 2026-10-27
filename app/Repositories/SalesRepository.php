<?php

namespace App\Repositories;

use PDO;
use Throwable;

class SalesRepository
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function formOptions(): array
    {
        return [
            'clientes' => $this->connection
                ->query('SELECT `id`, `nombre`, `correo` FROM `cliente` ORDER BY `nombre`')
                ->fetchAll(),
            'metodos_pago' => $this->connection
                ->query('SELECT `id`, `nombre` FROM `metodo_pago` ORDER BY `nombre`')
                ->fetchAll(),
            'productos' => $this->connection
                ->query('SELECT `id`, `nombre`, `precio_venta`, `stock` FROM `producto` ORDER BY `nombre`')
                ->fetchAll(),
        ];
    }

    public function listSales(): array
    {
        $statement = $this->connection->prepare(
            'SELECT v.`folio`, v.`created_at`, c.`nombre` AS `cliente`, v.`total` '
            . 'FROM `venta` v '
            . 'INNER JOIN `cliente` c ON c.`id` = v.`id_cliente` '
            . 'ORDER BY v.`created_at` DESC, v.`id` DESC'
        );
        $statement->execute();

        return $statement->fetchAll();
    }

    public function findSaleByFolio(string $folio): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT v.`id`, v.`folio`, v.`created_at`, v.`total`, '
            . 'c.`nombre` AS `cliente`, mp.`nombre` AS `metodo_pago` '
            . 'FROM `venta` v '
            . 'INNER JOIN `cliente` c ON c.`id` = v.`id_cliente` '
            . 'INNER JOIN `metodo_pago` mp ON mp.`id` = v.`id_metodo_pago` '
            . 'WHERE v.`folio` = :folio'
        );
        $statement->execute([':folio' => $folio]);
        $sale = $statement->fetch();
        if ($sale === false) {
            return null;
        }

        $details = $this->connection->prepare(
            'SELECT p.`nombre` AS `producto`, d.`cantidad`, d.`precio_unitario`, d.`subtotal` '
            . 'FROM `detalle_venta` d '
            . 'INNER JOIN `producto` p ON p.`id` = d.`id_producto` '
            . 'WHERE d.`id_venta` = :venta ORDER BY d.`id`'
        );
        $details->execute([':venta' => $sale['id']]);
        $sale['detalles'] = $details->fetchAll();

        return $sale;
    }

    public function transaction(callable $operation)
    {
        $ownsTransaction = !$this->connection->inTransaction();
        $savepoint = 'sprint2_sale';

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        } else {
            $this->connection->exec('SAVEPOINT ' . $savepoint);
        }

        try {
            $result = $operation();
            if ($ownsTransaction) {
                $this->connection->commit();
            } else {
                $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }

            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            } elseif ($this->connection->inTransaction()) {
                $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }

            throw $exception;
        }
    }

    public function referenceExists(string $table, int $id): bool
    {
        if (!in_array($table, ['cliente', 'metodo_pago'], true)) {
            throw new \InvalidArgumentException('La referencia solicitada no existe.');
        }

        $statement = $this->connection->prepare(
            "SELECT `id` FROM `{$table}` WHERE `id` = :id FOR UPDATE"
        );
        $statement->execute([':id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    public function lockProducts(array $productIds): array
    {
        $placeholders = [];
        $parameters = [];
        foreach (array_values($productIds) as $index => $productId) {
            $placeholder = ':product_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $productId;
        }

        $statement = $this->connection->prepare(
            'SELECT `id`, `nombre`, `precio_venta`, `stock` '
            . 'FROM `producto` WHERE `id` IN (' . implode(', ', $placeholders) . ') '
            . 'ORDER BY `id` FOR UPDATE'
        );
        $statement->execute($parameters);

        $products = [];
        foreach ($statement->fetchAll() as $product) {
            $products[(int) $product['id']] = $product;
        }

        return $products;
    }

    public function insertSale(int $customerId, int $paymentMethodId, string $total): array
    {
        $temporaryFolio = 'TMP-' . bin2hex(random_bytes(12));
        $statement = $this->connection->prepare(
            'INSERT INTO `venta` (`folio`, `id_cliente`, `id_metodo_pago`, `total`) '
            . 'VALUES (:folio, :cliente, :metodo_pago, :total)'
        );
        $statement->execute([
            ':folio' => $temporaryFolio,
            ':cliente' => $customerId,
            ':metodo_pago' => $paymentMethodId,
            ':total' => $total,
        ]);

        $saleId = (int) $this->connection->lastInsertId();
        $folio = 'V-' . str_pad((string) $saleId, 6, '0', STR_PAD_LEFT);
        $update = $this->connection->prepare(
            'UPDATE `venta` SET `folio` = :folio WHERE `id` = :id'
        );
        $update->execute([':folio' => $folio, ':id' => $saleId]);

        return ['id' => $saleId, 'folio' => $folio];
    }

    public function insertSaleDetail(int $saleId, array $item): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO `detalle_venta` '
            . '(`id_venta`, `id_producto`, `cantidad`, `precio_unitario`, `subtotal`) '
            . 'VALUES (:venta, :producto, :cantidad, :precio, :subtotal)'
        );
        $statement->execute([
            ':venta' => $saleId,
            ':producto' => $item['id'],
            ':cantidad' => $item['cantidad'],
            ':precio' => $item['precio_unitario'],
            ':subtotal' => $item['subtotal'],
        ]);
    }

    public function decrementStock(int $productId, int $quantity): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE `producto` SET `stock` = `stock` - :cantidad '
            . 'WHERE `id` = :id AND `stock` >= :stock_minimo'
        );
        $statement->execute([
            ':cantidad' => $quantity,
            ':id' => $productId,
            ':stock_minimo' => $quantity,
        ]);

        return $statement->rowCount() === 1;
    }
}
