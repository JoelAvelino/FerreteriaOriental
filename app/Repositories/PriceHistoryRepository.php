<?php

namespace App\Repositories;

use PDO;

class PriceHistoryRepository
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function search(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        $product = $filters['producto'];

        if ($product !== '') {
            $escapedProduct = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $product);
            $namePattern = '%' . $escapedProduct . '%';
            if (preg_match('/^\d+$/D', $product)) {
                $conditions[] = '(`codigo_producto` = :codigo OR `nombre_producto` LIKE :nombre ESCAPE \'!\')';
                $parameters[':codigo'] = $product;
            } else {
                $conditions[] = '`nombre_producto` LIKE :nombre ESCAPE \'!\'';
            }
            $parameters[':nombre'] = $namePattern;
        }

        if ($filters['fecha_inicio'] !== '') {
            $conditions[] = '`created_at` >= :fecha_inicio';
            $parameters[':fecha_inicio'] = $filters['fecha_inicio'] . ' 00:00:00';
        }
        if ($filters['fecha_fin'] !== '') {
            $conditions[] = '`created_at` <= :fecha_fin';
            $parameters[':fecha_fin'] = $filters['fecha_fin'] . ' 23:59:59';
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $statement = $this->connection->prepare(
            'SELECT `id`, `codigo_producto`, `nombre_producto`, `precio_anterior`, '
            . '`precio_nuevo`, `area_responsable`, `created_at` '
            . 'FROM `historial_precio`' . $where
            . ' ORDER BY `created_at` DESC, `id` DESC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }
}
