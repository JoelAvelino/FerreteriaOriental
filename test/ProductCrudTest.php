<?php

declare(strict_types=1);

namespace Tests;

use PDO;

require_once __DIR__ . '/ConnectionIntegrationTest.php';

class ProductCrudTest extends ConnectionIntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Esquema de prueba en $this->db para 4 tablas: categorias, marcas, proveedores y productos
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS categorias (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre VARCHAR(100) NOT NULL
            );

            CREATE TABLE IF NOT EXISTS marcas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre VARCHAR(100) NOT NULL
            );

            CREATE TABLE IF NOT EXISTS proveedores (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre VARCHAR(120) NOT NULL,
                telefono VARCHAR(30)
            );

            CREATE TABLE IF NOT EXISTS productos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre VARCHAR(150) NOT NULL,
                precio_venta DECIMAL(10,2) NOT NULL,
                stock INTEGER NOT NULL,
                id_categoria INTEGER,
                id_marca INTEGER,
                id_proveedor INTEGER
            );
        ');
    }

    /**
     * Prueba 1: Inserción en la tabla `categorias`.
     */
    public function testInsertarEnTablaCategorias(): void
    {
        $stmt = $this->db->prepare('INSERT INTO categorias (nombre) VALUES (:nombre)');
        $resultado = $stmt->execute([':nombre' => 'Herramienta Manual']);

        $this->assertTrue($resultado);
        $idInsertado = (int) $this->db->lastInsertId();
        $this->assertGreaterThan(0, $idInsertado);

        $fila = $this->db->query("SELECT nombre FROM categorias WHERE id = {$idInsertado}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Herramienta Manual', $fila['nombre']);
    }

    /**
     * Prueba 2: Inserción en la tabla `marcas`.
     */
    public function testInsertarEnTablaMarcas(): void
    {
        $stmt = $this->db->prepare('INSERT INTO marcas (nombre) VALUES (:nombre)');
        $resultado = $stmt->execute([':nombre' => 'Truper']);

        $this->assertTrue($resultado);
        $idInsertado = (int) $this->db->lastInsertId();
        $this->assertGreaterThan(0, $idInsertado);

        $fila = $this->db->query("SELECT nombre FROM marcas WHERE id = {$idInsertado}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Truper', $fila['nombre']);
    }

    /**
     * Prueba 3: Inserción en la tabla `proveedores`.
     */
    public function testInsertarEnTablaProveedores(): void
    {
        $stmt = $this->db->prepare('INSERT INTO proveedores (nombre, telefono) VALUES (:nombre, :telefono)');
        $resultado = $stmt->execute([
            ':nombre' => 'Distribuidora Ferretera del Sur',
            ':telefono' => '2221234567',
        ]);

        $this->assertTrue($resultado);
        $idInsertado = (int) $this->db->lastInsertId();
        $this->assertGreaterThan(0, $idInsertado);

        $fila = $this->db->query("SELECT nombre, telefono FROM proveedores WHERE id = {$idInsertado}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Distribuidora Ferretera del Sur', $fila['nombre']);
        $this->assertSame('2221234567', $fila['telefono']);
    }

    /**
     * Prueba 4: Inserción en la tabla `productos`.
     */
    public function testInsertarEnTablaProductos(): void
    {
        $this->db->exec("INSERT INTO categorias (nombre) VALUES ('Fijación')");
        $this->db->exec("INSERT INTO marcas (nombre) VALUES ('Pretul')");
        $this->db->exec("INSERT INTO proveedores (nombre, telefono) VALUES ('Suministros Oriente', '2227654321')");

        $stmt = $this->db->prepare('
            INSERT INTO productos (nombre, precio_venta, stock, id_categoria, id_marca, id_proveedor)
            VALUES (:nombre, :precio_venta, :stock, :id_categoria, :id_marca, :id_proveedor)
        ');
        $resultado = $stmt->execute([
            ':nombre' => 'Caja de tornillos 2in',
            ':precio_venta' => 89.50,
            ':stock' => 40,
            ':id_categoria' => 1,
            ':id_marca' => 1,
            ':id_proveedor' => 1,
        ]);

        $this->assertTrue($resultado);
        $idInsertado = (int) $this->db->lastInsertId();
        $this->assertGreaterThan(0, $idInsertado);

        $fila = $this->db->query("SELECT nombre, stock FROM productos WHERE id = {$idInsertado}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Caja de tornillos 2in', $fila['nombre']);
        $this->assertSame(40, (int) $fila['stock']);
    }
}