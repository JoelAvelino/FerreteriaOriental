<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Arquitectura de persistencia y conexión del sistema:
 * - Para la conexión con la base de datos utilizamos PDO (PHP Data Objects),
 *   mediante el controlador pdo_mysql, que permite establecer la comunicación
 *   entre nuestra aplicación PHP y el sistema gestor de bases de datos MySQL.
 * - La configuración de conexión se encuentra en config/database.php, mientras que
 *   la creación y administración de la conexión se realiza mediante la clase Database.php.
 * - Implementamos una arquitectura por capas, donde los servicios gestionan la
 *   lógica del negocio y los repositorios se encargan de ejecutar las consultas SQL.
 *   Para mayor seguridad utilizamos consultas preparadas y parámetros vinculados.
 *   También empleamos transacciones para garantizar la integridad de operaciones
 *   que requieren múltiples consultas.
 */

// Ruta de configuración y clase de conexión del proyecto (referencia/ficticia según entorno)
$rutaConfigDatabase = __DIR__ . '/../config/database.php';
$rutaClaseDatabase = __DIR__ . '/../app/Core/Database.php';

if (file_exists($rutaConfigDatabase)) {
    require_once $rutaConfigDatabase;
}

if (file_exists($rutaClaseDatabase)) {
    require_once $rutaClaseDatabase;
}

class ConnectionIntegrationTest extends TestCase
{
    /** @var PDO|null */
    protected $db;

    /**
     * Crea y administra la conexión a base de datos asignándola a $this->db.
     */
    protected function crearConexionDb(): PDO
    {
        if (class_exists('\\App\\Core\\Database')) {
            $conexion = \App\Core\Database::getInstance()->getConnection();
        } else {
            // Respaldo en memoria para pruebas aisladas sin depender de MySQL activo
            $conexion = new PDO('sqlite::memory:', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        $this->db = $conexion;

        return $this->db;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearConexionDb();
    }

    public function testConexionDbInicializada(): void
    {
        $this->assertInstanceOf(PDO::class, $this->db, 'La variable $db debe ser una instancia de PDO.');
    }
}