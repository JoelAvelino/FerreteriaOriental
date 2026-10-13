<?php

namespace App;

use PDO;
use RuntimeException;

class Database
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getServerConnection(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['charset']
        );

        return new PDO(
            $dsn,
            $this->config['username'],
            $this->config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            ]
        );
    }

    public function getConnection(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset']
        );

        return new PDO(
            $dsn,
            $this->config['username'],
            $this->config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            ]
        );
    }

    public function ensureDatabase(): void
    {
        $server = $this->getServerConnection();
        $database = $this->config['database'];
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function installSchema(): void
    {
        $schemaPath = dirname(__DIR__) . '/database/schema.sql';

        if (!is_file($schemaPath)) {
            throw new RuntimeException('No se encontró el esquema SQL en ' . $schemaPath);
        }

        $sql = file_get_contents($schemaPath);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('El archivo de esquema SQL está vacío.');
        }

        $connection = $this->getConnection();
        $connection->exec($sql);
    }

    public function validateSchema(): array
    {
        $connection = $this->getConnection();
        $tables = [
            'categoria',
            'marca',
            'proveedor',
            'producto',
        ];

        $missing = [];
        foreach ($tables as $table) {
            $statement = $connection->prepare('SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
            $statement->execute([':table' => $table]);
            $result = $statement->fetch();

            if ((int) $result['total'] === 0) {
                $missing[] = $table;
            }
        }

        $constraints = $connection->query(
            'SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.key_column_usage WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL GROUP BY TABLE_NAME, CONSTRAINT_NAME ORDER BY TABLE_NAME, CONSTRAINT_NAME'
        )->fetchAll();

        return [
            'tables' => $tables,
            'missing' => $missing,
            'constraints' => $constraints,
        ];
    }
}
