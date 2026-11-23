<?php

namespace App\Repositories;

use PDO;
use PDOException;
use RuntimeException;

class CatalogRepository
{
    private const DELETABLE_TABLES = [
        'categoria',
        'proveedor',
        'producto',
    ];

    private const REFERENCE_TABLES = [
        'id_categoria' => 'categoria',
        'id_marca' => 'marca',
        'id_proveedor' => 'proveedor',
    ];

    private const PRODUCT_UPDATE_FIELDS = [
        'nombre',
        'descripcion',
        'precio_compra',
        'precio_venta',
        'stock',
        'id_categoria',
        'id_marca',
        'id_proveedor',
    ];

    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function list(string $table, array $columns): array
    {
        $selectedColumns = array_merge(['id'], $columns);
        $sqlColumns = implode(', ', array_map(
            static fn (string $column): string => '`' . $column . '`',
            $selectedColumns
        ));

        return $this->connection
            ->query("SELECT {$sqlColumns} FROM `{$table}` ORDER BY `id` DESC")
            ->fetchAll();
    }

    public function referenceOptions(string $field): array
    {
        if (!isset(self::REFERENCE_TABLES[$field])) {
            throw new \InvalidArgumentException('La referencia solicitada no existe.');
        }

        $table = self::REFERENCE_TABLES[$field];
        $statement = $this->connection->query(
            "SELECT `id`, `nombre` FROM `{$table}` ORDER BY `nombre`"
        );

        return $statement->fetchAll();
    }

    public function referenceExists(string $field, int $id): bool
    {
        if (!isset(self::REFERENCE_TABLES[$field])) {
            throw new \InvalidArgumentException('La referencia solicitada no existe.');
        }

        $table = self::REFERENCE_TABLES[$field];
        $statement = $this->connection->prepare(
            "SELECT 1 FROM `{$table}` WHERE `id` = :id"
        );
        $statement->execute([':id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    public function clientPhoneExists(string $phone): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM `cliente` WHERE `telefono` = :telefono LIMIT 1'
        );
        $statement->execute([':telefono' => $phone]);

        return $statement->fetchColumn() !== false;
    }

    public function clientEmailExists(string $email): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM `cliente` WHERE `correo` = :correo LIMIT 1'
        );
        $statement->execute([':correo' => $email]);

        return $statement->fetchColumn() !== false;
    }

    public function paymentMethodNameExists(string $name): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM `metodo_pago` WHERE `nombre` = :nombre LIMIT 1'
        );
        $statement->execute([':nombre' => $name]);

        return $statement->fetchColumn() !== false;
    }

    public function searchProducts(array $filters): array
    {
        $conditions = [];
        $parameters = [];

        if ($filters['busqueda'] !== '') {
            $conditions[] = '(p.`nombre` LIKE :nombre OR p.`descripcion` LIKE :descripcion)';
            $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['busqueda']) . '%';
            $parameters[':nombre'] = $term;
            $parameters[':descripcion'] = $term;
        }

        foreach ([
            'id_categoria' => 'p.`id_categoria`',
            'id_marca' => 'p.`id_marca`',
            'id_proveedor' => 'p.`id_proveedor`',
        ] as $field => $column) {
            if ($filters[$field] !== null) {
                $conditions[] = "{$column} = :{$field}";
                $parameters[':' . $field] = $filters[$field];
            }
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $statement = $this->connection->prepare(
            'SELECT p.`id`, p.`nombre`, p.`descripcion`, p.`precio_compra`, p.`precio_venta`, p.`stock`, '
            . 'p.`id_categoria`, c.`nombre` AS `categoria`, '
            . 'p.`id_marca`, m.`nombre` AS `marca`, '
            . 'p.`id_proveedor`, pr.`nombre` AS `proveedor` '
            . 'FROM `producto` p '
            . 'INNER JOIN `categoria` c ON c.`id` = p.`id_categoria` '
            . 'INNER JOIN `marca` m ON m.`id` = p.`id_marca` '
            . 'INNER JOIN `proveedor` pr ON pr.`id` = p.`id_proveedor`'
            . $where
            . ' ORDER BY p.`id` DESC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function searchProductsForLocations(array $filters): array
    {
        $conditions = [];
        $parameters = [];

        if ($filters['busqueda'] !== '') {
            $escapedName = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['busqueda']);
            if ($filters['id_producto'] !== null) {
                $conditions[] = '(p.`id` = :id_producto OR p.`nombre` LIKE :nombre ESCAPE \'!\')';
                $parameters[':id_producto'] = $filters['id_producto'];
            } else {
                $conditions[] = 'p.`nombre` LIKE :nombre ESCAPE \'!\'';
            }
            $parameters[':nombre'] = '%' . $escapedName . '%';
        }

        if ($filters['pasillo'] !== '') {
            $conditions[] = 'p.`pasillo` = :pasillo';
            $parameters[':pasillo'] = $filters['pasillo'];
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $statement = $this->connection->prepare(
            'SELECT p.`id`, p.`nombre`, c.`nombre` AS `categoria`, '
            . 'p.`pasillo`, p.`estante`, p.`stock` '
            . 'FROM `producto` p '
            . 'INNER JOIN `categoria` c ON c.`id` = p.`id_categoria`'
            . $where
            . ' ORDER BY p.`id` DESC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function findProduct(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT `id`, `nombre`, `descripcion`, `precio_compra`, `precio_venta`, `stock`, '
            . '`id_categoria`, `id_marca`, `id_proveedor` '
            . 'FROM `producto` WHERE `id` = :id'
        );
        $statement->execute([':id' => $id]);
        $product = $statement->fetch();

        return $product === false ? null : $product;
    }

    public function findProductsForPriceById(int $id): array
    {
        $statement = $this->connection->prepare(
            'SELECT `id`, `nombre`, `precio_venta` FROM `producto` WHERE `id` = :id'
        );
        $statement->execute([':id' => $id]);

        return $statement->fetchAll();
    }

    public function searchProductsForPriceByName(string $name): array
    {
        $escapedName = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $name);
        $statement = $this->connection->prepare(
            'SELECT `id`, `nombre`, `precio_venta` FROM `producto` '
            . 'WHERE `nombre` LIKE :nombre ESCAPE \'!\' '
            . 'ORDER BY `id` DESC LIMIT 50'
        );
        $statement->execute([':nombre' => '%' . $escapedName . '%']);

        return $statement->fetchAll();
    }

    public function updateProduct(int $id, array $values): bool
    {
        $result = $this->saveProductChanges($id, $values);

        return $result['exists'];
    }

    public function updateProductPrice(int $id, string $price): array
    {
        return $this->saveProductChanges($id, ['precio_venta' => $price]);
    }

    protected function insertPriceHistory(
        int $productId,
        string $productName,
        ?string $previousPrice,
        ?string $newPrice
    ): void {
        $statement = $this->connection->prepare(
            'INSERT INTO `historial_precio` '
            . '(`id_producto`, `codigo_producto`, `nombre_producto`, `precio_anterior`, `precio_nuevo`, `area_responsable`) '
            . 'VALUES (:producto, :codigo, :nombre, :precio_anterior, :precio_nuevo, :area)'
        );
        $statement->execute([
            ':producto' => $productId,
            ':codigo' => $productId,
            ':nombre' => $productName,
            ':precio_anterior' => $previousPrice,
            ':precio_nuevo' => $newPrice,
            ':area' => 'Administrador',
        ]);
    }

    private function saveProductChanges(int $id, array $values): array
    {
        if ($id < 1 || !$values || array_diff(array_keys($values), self::PRODUCT_UPDATE_FIELDS)) {
            throw new \InvalidArgumentException('Los datos de actualización del producto no son válidos.');
        }

        return $this->transaction(function () use ($id, $values): array {
            $lock = $this->connection->prepare(
                'SELECT `id`, `nombre`, `precio_venta` FROM `producto` WHERE `id` = :id FOR UPDATE'
            );
            $lock->execute([':id' => $id]);
            $current = $lock->fetch();
            if ($current === false) {
                return ['exists' => false, 'price_changed' => false];
            }

            $priceChanged = false;
            $previousPrice = $current['precio_venta'];
            $newPrice = $previousPrice;
            if (array_key_exists('precio_venta', $values)) {
                $newPrice = $values['precio_venta'];
                if ($newPrice !== null && !is_string($newPrice)) {
                    throw new \InvalidArgumentException('El precio de venta no tiene un formato válido.');
                }
                $priceChanged = $this->priceToCents($previousPrice) !== $this->priceToCents($newPrice);
                if (!$priceChanged && count($values) === 1) {
                    return ['exists' => true, 'price_changed' => false];
                }
            }

            $assignments = [];
            $parameters = [':id' => $id];
            foreach ($values as $field => $value) {
                $assignments[] = '`' . $field . '` = :' . $field;
                $parameters[':' . $field] = $value;
            }
            $update = $this->connection->prepare(
                'UPDATE `producto` SET ' . implode(', ', $assignments) . ' WHERE `id` = :id'
            );
            $update->execute($parameters);

            if ($priceChanged) {
                $product = $this->connection->prepare(
                    'SELECT `nombre` FROM `producto` WHERE `id` = :id'
                );
                $product->execute([':id' => $id]);
                $productName = $product->fetchColumn();
                if (!is_string($productName)) {
                    throw new RuntimeException('No se pudo recuperar el producto actualizado para registrar el historial.');
                }

                $this->insertPriceHistory($id, $productName, $previousPrice, $newPrice);
            }

            return ['exists' => true, 'price_changed' => $priceChanged];
        });
    }

    private function transaction(callable $operation): array
    {
        $ownsTransaction = !$this->connection->inTransaction();
        $savepoint = 'catalog_price_update';

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
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            } elseif ($this->connection->inTransaction()) {
                $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }

            throw $exception;
        }
    }

    private function priceToCents(?string $price): ?int
    {
        if ($price === null) {
            return null;
        }
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $price)) {
            throw new \InvalidArgumentException('El precio de venta almacenado no tiene un formato válido.');
        }

        $negative = str_starts_with($price, '-');
        $unsignedPrice = $negative ? substr($price, 1) : $price;
        [$whole, $fraction] = array_pad(explode('.', $unsignedPrice, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 8) {
            throw new \InvalidArgumentException('El precio de venta excede la precisión permitida.');
        }

        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    public function delete(string $table, int $id): bool
    {
        if (!in_array($table, self::DELETABLE_TABLES, true)) {
            throw new \InvalidArgumentException('El tipo de registro solicitado no se puede eliminar.');
        }

        $statement = $this->connection->prepare(
            "DELETE FROM `{$table}` WHERE `id` = :id"
        );
        $statement->execute([':id' => $id]);

        return $statement->rowCount() === 1;
    }

    public function create(string $table, array $values): void
    {
        $columns = array_keys($values);
        $sqlColumns = implode(', ', array_map(
            static fn (string $column): string => '`' . $column . '`',
            $columns
        ));
        $placeholders = implode(', ', array_map(
            static fn (string $column): string => ':' . $column,
            $columns
        ));
        $statement = $this->connection->prepare(
            "INSERT INTO `{$table}` ({$sqlColumns}) VALUES ({$placeholders})"
        );

        try {
            $statement->execute($values);
        } catch (PDOException $exception) {
            $errorInfo = $exception->errorInfo;
            if ($exception->getCode() === '23000' && isset($errorInfo[1]) && (int) $errorInfo[1] === 1062) {
                if ($table === 'cliente') {
                    throw new RuntimeException('El correo o teléfono ya está registrado.', 0, $exception);
                }

                throw new RuntimeException('Ya existe un registro con ese nombre.', 0, $exception);
            }
            if ($exception->getCode() === '23000' && isset($errorInfo[1]) && (int) $errorInfo[1] === 1452) {
                throw new RuntimeException('Una categoría, marca o proveedor seleccionado ya no existe. Recarga el formulario e inténtalo de nuevo.', 0, $exception);
            }

            throw $exception;
        }
    }
}
