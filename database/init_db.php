<?php

require __DIR__ . '/../app/Database.php';

$config = require __DIR__ . '/../config/database.php';

try {
    $database = new App\Database($config);
    $database->ensureDatabase();
    $database->installSchema();

    $report = $database->validateSchema();

    echo "Base de datos inicializada correctamente.\n";
    echo "Tablas verificadas: " . implode(', ', $report['tables']) . "\n";

    if (!empty($report['missing'])) {
        echo "Faltan tablas: " . implode(', ', $report['missing']) . "\n";
        exit(1);
    }

    echo "Relaciones de clave foránea verificadas: " . count($report['constraints']) . "\n";
    echo "Estado: OK\n";
} catch (Throwable $exception) {
    echo 'Error al inicializar la base de datos: ' . $exception->getMessage() . PHP_EOL;
    exit(1);
}
