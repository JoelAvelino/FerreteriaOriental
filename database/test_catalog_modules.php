<?php

use App\Database;
use App\Repositories\CatalogRepository;
use App\Services\CatalogService;

require __DIR__ . '/../app/Database.php';
require __DIR__ . '/../app/Repositories/CatalogRepository.php';
require __DIR__ . '/../app/Services/CatalogService.php';

$config = require __DIR__ . '/../config/database.php';
$connection = (new Database($config))->getConnection();
$service = new CatalogService(new CatalogRepository($connection));
$suffix = bin2hex(random_bytes(5));
$tests = 0;

$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$connection->beginTransaction();

try {
    $cases = [
        ['categorias', ['nombre' => 'Prueba categoria ' . $suffix, 'descripcion' => 'Dato ficticio de prueba']],
        ['marcas', ['nombre' => 'Prueba marca ' . $suffix, 'pais_origen' => 'Pais ficticio']],
        ['proveedores', ['nombre' => 'Prueba proveedor ' . $suffix, 'contacto' => 'Contacto de prueba', 'telefono' => '+1 555-0100', 'correo' => 'prueba-' . $suffix . '@example.test']],
    ];

    foreach ($cases as [$module, $input]) {
        [$values, $errors] = $service->validate($module, $input);
        $assert($errors === [], "La validación de {$module} rechazó datos de prueba válidos.");
        $service->create($module, $values);
        $listedNames = array_column($service->list($module), 'nombre');
        $assert(in_array($input['nombre'], $listedNames, true), "El registro de {$module} no apareció en el listado.");
    }

    foreach ($cases as [$module, $input]) {
        $duplicateRejected = false;
        try {
            $service->create($module, $input);
        } catch (RuntimeException $exception) {
            $duplicateRejected = $exception->getMessage() === 'Ya existe un registro con ese nombre.';
        }
        $assert($duplicateRejected, "No se informó correctamente un nombre duplicado en {$module}.");
    }

    [, $categoryErrors] = $service->validate('categorias', ['nombre' => ' ', 'descripcion' => '']);
    $assert(isset($categoryErrors['nombre']), 'No se rechazó el nombre vacío de categoría.');

    [, $malformedCategoryErrors] = $service->validate('categorias', ['nombre' => ['valor no permitido'], 'descripcion' => '']);
    $assert(isset($malformedCategoryErrors['nombre']), 'No se rechazó el nombre con tipo de dato inválido.');

    [, $brandErrors] = $service->validate('marcas', ['nombre' => 'Marca ficticia', 'pais_origen' => '']);
    $assert(isset($brandErrors['pais_origen']), 'No se rechazó el país de origen vacío.');

    [, $supplierErrors] = $service->validate('proveedores', [
        'nombre' => 'Proveedor ficticio',
        'contacto' => 'Contacto',
        'telefono' => '',
        'correo' => 'no-es-correo',
    ]);
    $assert(isset($supplierErrors['correo']), 'No se rechazó el correo inválido.');

    [, $phoneErrors] = $service->validate('proveedores', [
        'nombre' => 'Proveedor ficticio',
        'contacto' => 'Contacto',
        'telefono' => '12',
        'correo' => '',
    ]);
    $assert(isset($phoneErrors['telefono']), 'No se rechazó el teléfono con formato inválido.');

    $referenceIds = [];
    foreach (['id_categoria' => 'categorias', 'id_marca' => 'marcas', 'id_proveedor' => 'proveedores'] as $field => $module) {
        $options = $service->options('productos')[$field];
        $fixtureName = [
            'id_categoria' => $cases[0][1]['nombre'],
            'id_marca' => $cases[1][1]['nombre'],
            'id_proveedor' => $cases[2][1]['nombre'],
        ][$field];
        $option = array_values(array_filter(
            $options,
            static fn (array $item): bool => $item['nombre'] === $fixtureName
        ));
        $assert(count($option) === 1, "No se encontró en el selector la opción de prueba para {$module}.");
        $referenceIds[$field] = (int) $option[0]['id'];
    }

    $productInput = array_merge([
        'nombre' => 'Prueba producto ' . $suffix,
        'descripcion' => 'Producto ficticio para prueba',
        'precio_compra' => '12.50',
        'precio_venta' => '20.00',
        'stock' => '7',
    ], $referenceIds);
    [$productValues, $productErrors] = $service->validate('productos', $productInput);
    $assert($productErrors === [], 'La validación de producto rechazó campos o referencias válidas.');
    $service->create('productos', $productValues);
    $productQuery = $connection->prepare(
        'SELECT id, nombre, id_categoria, id_marca, id_proveedor, stock, precio_compra, precio_venta FROM producto WHERE nombre = :nombre'
    );
    $productQuery->execute([':nombre' => $productInput['nombre']]);
    $savedProduct = $productQuery->fetch();
    $assert($savedProduct !== false, 'El producto de prueba no se persistió.');
    $savedProductId = (int) $savedProduct['id'];
    $assert((int) $savedProduct['id_categoria'] === $referenceIds['id_categoria'], 'No se persistió el id de categoría seleccionado.');
    $assert((int) $savedProduct['id_marca'] === $referenceIds['id_marca'], 'No se persistió el id de marca seleccionado.');
    $assert((int) $savedProduct['id_proveedor'] === $referenceIds['id_proveedor'], 'No se persistió el id de proveedor seleccionado.');
    $assert((int) $savedProduct['stock'] === 7, 'No se persistió el stock ingresado.');

    [$filters, $filterErrors] = $service->validateProductFilters([
        'busqueda' => $productInput['nombre'],
        'id_categoria' => (string) $referenceIds['id_categoria'],
        'id_marca' => (string) $referenceIds['id_marca'],
        'id_proveedor' => (string) $referenceIds['id_proveedor'],
    ]);
    $assert($filterErrors === [], 'Los filtros válidos de catálogo fueron rechazados.');
    $filteredProducts = $service->searchProducts($filters);
    $assert(count($filteredProducts) === 1, 'La búsqueda y los filtros no encontraron únicamente el producto coincidente.');
    $assert($filteredProducts[0]['categoria'] === $cases[0][1]['nombre'], 'El catálogo no muestra la categoría relacionada.');
    $assert($filteredProducts[0]['marca'] === $cases[1][1]['nombre'], 'El catálogo no muestra la marca relacionada.');
    $assert($filteredProducts[0]['proveedor'] === $cases[2][1]['nombre'], 'El catálogo no muestra el proveedor relacionado.');
    $assert((int) $filteredProducts[0]['stock'] === 7, 'El catálogo no muestra la existencia del producto.');

    [$noMatchFilters, $noMatchErrors] = $service->validateProductFilters(['busqueda' => 'producto inexistente ' . $suffix]);
    $assert($noMatchErrors === [] && $service->searchProducts($noMatchFilters) === [], 'La búsqueda sin coincidencias no devolvió un resultado vacío.');

    [$invalidFilterValues, $invalidFilterErrors] = $service->validateProductFilters(['id_categoria' => '999999999']);
    $assert(isset($invalidFilterErrors['id_categoria']), 'Se aceptó un filtro con categoría inexistente.');

    $loadedProduct = $service->findProduct($savedProductId);
    $assert($loadedProduct !== null && $loadedProduct['nombre'] === $productInput['nombre'], 'No se cargaron los datos del producto para edición.');
    $editedInput = array_merge($productInput, [
        'nombre' => 'Producto editado ' . $suffix,
        'descripcion' => 'Descripción modificada de prueba',
        'precio_compra' => '15.75',
        'precio_venta' => '25.50',
        'stock' => '9',
    ]);
    [$editedValues, $editErrors] = $service->validate('productos', $editedInput);
    $assert($editErrors === [], 'La validación de la edición rechazó valores válidos.');
    $assert($service->updateProduct($savedProductId, $editedValues), 'La actualización del producto no reportó que el registro existe.');
    $updatedProduct = $service->findProduct($savedProductId);
    $assert($updatedProduct['nombre'] === $editedInput['nombre'], 'El nombre actualizado no se persistió.');
    $assert($updatedProduct['descripcion'] === $editedInput['descripcion'], 'La descripción actualizada no se persistió.');
    $assert((int) $updatedProduct['stock'] === 9, 'La existencia actualizada no se persistió.');
    $assert((int) $updatedProduct['id_categoria'] === $referenceIds['id_categoria'], 'La edición no mantuvo la categoría asociada.');
    $assert((int) $updatedProduct['id_marca'] === $referenceIds['id_marca'], 'La edición no mantuvo la marca asociada.');
    $assert((int) $updatedProduct['id_proveedor'] === $referenceIds['id_proveedor'], 'La edición no mantuvo el proveedor asociado.');
    [$updatedFilters] = $service->validateProductFilters(['busqueda' => $editedInput['nombre']]);
    $assert(count($service->searchProducts($updatedFilters)) === 1, 'La búsqueda no refleja el producto con sus datos actualizados.');
    $assert(!$service->updateProduct(4294967295, $editedValues), 'Se indicó éxito al actualizar un producto inexistente.');

    $productWithoutStock = $productInput;
    $productWithoutStock['nombre'] .= ' sin stock';
    $productWithoutStock['stock'] = '';
    [$defaultStockValues, $defaultStockErrors] = $service->validate('productos', $productWithoutStock);
    $assert($defaultStockErrors === [] && !array_key_exists('stock', $defaultStockValues), 'El stock vacío no usa el valor predeterminado documentado.');
    $service->create('productos', $defaultStockValues);
    $defaultStockQuery = $connection->prepare('SELECT stock FROM producto WHERE nombre = :nombre');
    $defaultStockQuery->execute([':nombre' => $productWithoutStock['nombre']]);
    $assert((int) $defaultStockQuery->fetchColumn() === 0, 'La base de datos no aplicó el stock predeterminado.');

    $invalidReferenceInput = $productInput;
    $invalidReferenceInput['id_marca'] = '4294967295';
    [, $invalidReferenceErrors] = $service->validate('productos', $invalidReferenceInput);
    $assert(isset($invalidReferenceErrors['id_marca']), 'Se aceptó un identificador de marca inexistente.');

    $invalidPriceInput = $productInput;
    $invalidPriceInput['precio_compra'] = '12.345';
    [, $invalidPriceErrors] = $service->validate('productos', $invalidPriceInput);
    $assert(isset($invalidPriceErrors['precio_compra']), 'Se aceptó un precio con más decimales que DECIMAL(10,2).');

    $invalidStockInput = $productInput;
    $invalidStockInput['stock'] = '2147483648';
    [, $invalidStockErrors] = $service->validate('productos', $invalidStockInput);
    $assert(isset($invalidStockErrors['stock']), 'Se aceptó un stock fuera del rango INT de MySQL.');

    $missingProductFields = $productInput;
    $missingProductFields['nombre'] = '';
    $missingProductFields['id_categoria'] = '';
    [, $missingProductErrors] = $service->validate('productos', $missingProductFields);
    $assert(isset($missingProductErrors['nombre'], $missingProductErrors['id_categoria']), 'No se validaron los campos obligatorios del producto.');

    $foreignKeyRejected = false;
    try {
        $invalidInsert = $connection->prepare(
            'INSERT INTO producto (nombre, id_categoria, id_marca, id_proveedor) VALUES (:nombre, :id_categoria, :id_marca, :id_proveedor)'
        );
        $invalidInsert->execute([
            ':nombre' => 'FK inválida ' . $suffix,
            ':id_categoria' => 4294967295,
            ':id_marca' => $referenceIds['id_marca'],
            ':id_proveedor' => $referenceIds['id_proveedor'],
        ]);
    } catch (PDOException $exception) {
        $foreignKeyRejected = isset($exception->errorInfo[1]) && (int) $exception->errorInfo[1] === 1452;
    }
    $assert($foreignKeyRejected, 'MySQL no rechazó la referencia foránea inválida.');

    echo "Pruebas de módulos de catálogo: {$tests} comprobaciones OK." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
