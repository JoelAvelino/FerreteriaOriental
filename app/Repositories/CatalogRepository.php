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

    public function updateProduct(int $id, array $values): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE `producto` SET `nombre` = :nombre, `descripcion` = :descripcion, '
            . '`precio_compra` = :precio_compra, `precio_venta` = :precio_venta, `stock` = :stock, '
            . '`id_categoria` = :id_categoria, `id_marca` = :id_marca, `id_proveedor` = :id_proveedor '
            . 'WHERE `id` = :id'
        );
        $statement->execute($values + [':id' => $id]);

        return $this->findProduct($id) !== null;
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
