<?php

require __DIR__ . '/../app/Database.php';

$config = require __DIR__ . '/../config/database.php';

try {
    $database = new App\Database($config);
    $report = $database->validateSchema();

    echo "Validación de esquema\n";
    echo "Tablas esperadas: " . implode(', ', $report['tables']) . "\n";

    if (!empty($report['missing'])) {
        echo "Tablas faltantes: " . implode(', ', $report['missing']) . "\n";
        exit(1);
    }

    $constraintCount = count($report['constraints']);
    echo "Claves foráneas detectadas: {$constraintCount}\n";
    if ($constraintCount < 7) {
        echo "No se detectaron las siete relaciones esperadas de Sprint 1 y Sprint 2.\n";
        exit(1);
    }

    echo "Estado: OK\n";
} catch (Throwable $exception) {
    echo 'Error de validación: ' . $exception->getMessage() . PHP_EOL;
    exit(1);
}
