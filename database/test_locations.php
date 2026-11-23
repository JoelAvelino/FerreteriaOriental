<?php

use App\Database;
use App\Repositories\CatalogRepository;
use App\Services\CatalogService;

require __DIR__ . '/../app/Database.php';
require __DIR__ . '/../app/Repositories/CatalogRepository.php';
require __DIR__ . '/../app/Services/CatalogService.php';

$config = require __DIR__ . '/../config/database.php';
$connection = (new Database($config))->getConnection();
$repository = new CatalogRepository($connection);
$service = new CatalogService($repository);
$suffix = bin2hex(random_bytes(5));
$tests = 0;
$emptyFilters = ['busqueda' => '', 'id_producto' => null, 'pasillo' => ''];
$tableCounts = [];
foreach (['producto', 'categoria', 'marca', 'proveedor'] as $table) {
    $tableCounts[$table] = (int) $connection->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}
$existingProduct = $connection->query(
    'SELECT `id`, `nombre`, `pasillo`, `estante`, `stock` FROM `producto` ORDER BY `id` LIMIT 1'
)->fetch();

$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$connection->beginTransaction();

try {
    $insert = static function (string $sql, array $parameters = []) use ($connection): int {
        $statement = $connection->prepare($sql);
        $statement->execute($parameters);

        return (int) $connection->lastInsertId();
    };

    $categoryName = 'Categoría ficticia HU13 ' . $suffix;
    $categoryId = $insert(
        'INSERT INTO `categoria` (`nombre`) VALUES (:nombre)',
        [':nombre' => $categoryName]
    );
    $brandId = $insert(
        'INSERT INTO `marca` (`nombre`, `pais_origen`) VALUES (:nombre, :pais)',
        [':nombre' => 'Marca ficticia HU13 ' . $suffix, ':pais' => 'País ficticio']
    );
    $supplierId = $insert(
        'INSERT INTO `proveedor` (`nombre`, `contacto`) VALUES (:nombre, :contacto)',
        [':nombre' => 'Proveedor ficticio HU13 ' . $suffix, ':contacto' => 'Contacto ficticio']
    );
    $insertProduct = static function (
        string $name,
        ?string $aisle,
        ?string $shelf,
        int $stock
    ) use ($insert, $categoryId, $brandId, $supplierId): int {
        return $insert(
            'INSERT INTO `producto` '
            . '(`nombre`, `id_categoria`, `id_marca`, `id_proveedor`, `pasillo`, `estante`, `stock`) '
            . 'VALUES (:nombre, :categoria, :marca, :proveedor, :pasillo, :estante, :stock)',
            [
                ':nombre' => $name,
                ':categoria' => $categoryId,
                ':marca' => $brandId,
                ':proveedor' => $supplierId,
                ':pasillo' => $aisle,
                ':estante' => $shelf,
                ':stock' => $stock,
            ]
        );
    };

    $locatedName = 'Producto HU13 Alpha ' . $suffix;
    $otherAisleName = 'Producto HU13 Beta ' . $suffix;
    $unlocatedName = 'Producto HU13 sin ubicación ' . $suffix;
    $locatedId = $insertProduct($locatedName, 'A-01', 'E-02', 5);
    $otherAisleId = $insertProduct($otherAisleName, 'B-03', 'E-04', 6);
    $unlocatedId = $insertProduct($unlocatedName, null, null, 7);

    [$filters, $errors] = $service->validateLocationFilters($emptyFilters);
    $assert($errors === [], 'Los filtros vacíos se consideraron inválidos.');
    $allProducts = $service->searchProductsForLocations($filters);
    $assert(count($allProducts) === $tableCounts['producto'] + 3, 'El listado general no incluyó todos los productos.');

    $findById = static function (array $products, int $id): ?array {
        foreach ($products as $product) {
            if ((int) $product['id'] === $id) {
                return $product;
            }
        }

        return null;
    };
    $locatedProduct = $findById($allProducts, $locatedId);
    $unlocatedProduct = $findById($allProducts, $unlocatedId);
    $assert($locatedProduct !== null, 'El producto con ubicación no aparece en el listado general.');
    $assert(
        $locatedProduct['pasillo'] === 'A-01'
        && $locatedProduct['estante'] === 'E-02'
        && $locatedProduct['categoria'] === $categoryName
        && (int) $locatedProduct['stock'] === 5,
        'No se consultaron correctamente la ubicación, categoría y existencia.'
    );
    $assert(
        $unlocatedProduct !== null
        && $unlocatedProduct['pasillo'] === null
        && $unlocatedProduct['estante'] === null,
        'El producto sin ubicación se excluyó o sus valores no se conservaron.'
    );

    [$filters, $errors] = $service->validateLocationFilters([
        'busqueda' => (string) $locatedId,
        'pasillo' => '',
    ]);
    $idResults = $errors ? [] : $service->searchProductsForLocations($filters);
    $assert(count($idResults) === 1 && (int) $idResults[0]['id'] === $locatedId, 'La búsqueda por ID no encontró el producto.');

    [$filters, $errors] = $service->validateLocationFilters([
        'busqueda' => 'Alpha ' . $suffix,
        'pasillo' => '',
    ]);
    $nameResults = $errors ? [] : $service->searchProductsForLocations($filters);
    $assert(count($nameResults) === 1 && $nameResults[0]['nombre'] === $locatedName, 'La búsqueda por nombre no encontró el producto.');

    [$filters, $errors] = $service->validateLocationFilters([
        'busqueda' => '',
        'pasillo' => 'A-01',
    ]);
    $aisleResults = $errors ? [] : $service->searchProductsForLocations($filters);
    $assert(count($aisleResults) === 1 && (int) $aisleResults[0]['id'] === $locatedId, 'El filtro por pasillo no devolvió solo sus coincidencias.');

    [$filters, $errors] = $service->validateLocationFilters([
        'busqueda' => '',
        'pasillo' => 'B-03',
    ]);
    $otherAisleResults = $errors ? [] : $service->searchProductsForLocations($filters);
    $assert(count($otherAisleResults) === 1 && (int) $otherAisleResults[0]['id'] === $otherAisleId, 'El filtro no localizó el producto de otro pasillo.');

    [$filters, $errors] = $service->validateLocationFilters([
        'busqueda' => 'Alpha ' . $suffix,
        'pasillo' => 'A-01',
    ]);
    $combinedResults = $errors ? [] : $service->searchProductsForLocations($filters);
    $assert(count($combinedResults) === 1 && (int) $combinedResults[0]['id'] === $locatedId, 'La búsqueda y el filtro por pasillo no se combinaron.');

    [$filters, $errors] = $service->validateLocationFilters([
        'busqueda' => 'HU13 inexistente ' . $suffix,
        'pasillo' => '',
    ]);
    $emptyResults = $errors ? [] : $service->searchProductsForLocations($filters);
    $assert($emptyResults === [], 'Una búsqueda sin coincidencias devolvió productos.');

    [$filters, $errors] = $service->validateLocationFilters([
        'busqueda' => "' OR 1=1 -- " . $suffix,
        'pasillo' => '',
    ]);
    $injectionLikeResults = $errors ? [] : $service->searchProductsForLocations($filters);
    $assert($injectionLikeResults === [], 'La búsqueda con caracteres SQL no se trató como un valor literal.');

    [$filters, $errors] = $service->validateLocationFilters([
        'busqueda' => '',
        'pasillo' => "' OR 1=1 -- " . $suffix,
    ]);
    $injectionLikeAisleResults = $errors ? [] : $service->searchProductsForLocations($filters);
    $assert($injectionLikeAisleResults === [], 'El filtro de pasillo con caracteres SQL no se trató como un valor literal.');

    [, $searchLengthErrors] = $service->validateLocationFilters([
        'busqueda' => str_repeat('x', 151),
        'pasillo' => '',
    ]);
    $assert(isset($searchLengthErrors['busqueda']), 'Se aceptó una búsqueda que excede la longitud permitida.');
    [, $aisleLengthErrors] = $service->validateLocationFilters([
        'busqueda' => '',
        'pasillo' => str_repeat('A', 51),
    ]);
    $assert(isset($aisleLengthErrors['pasillo']), 'Se aceptó un pasillo que excede la longitud del esquema.');
    [, $filterTypeErrors] = $service->validateLocationFilters([
        'busqueda' => [],
        'pasillo' => '',
    ]);
    $assert(isset($filterTypeErrors['busqueda']), 'Se aceptó un filtro de búsqueda con tipo inválido.');

    ob_start();
    $modules = $service->modules();
    $message = null;
    $messageType = 'success';
    $locationFilters = $emptyFilters;
    $locationErrors = [];
    $locationError = null;
    $locationProducts = $allProducts;
    require __DIR__ . '/../app/Views/locations.php';
    $locationsHtml = ob_get_clean();
    $assert(str_contains($locationsHtml, 'Sin ubicación'), 'La vista no indicó claramente que el producto carece de ubicación.');
    $assert(!str_contains($locationsHtml, 'NULL'), 'La vista expuso un valor NULL de la base de datos.');
    $assert(
        str_contains($locationsHtml, '<th>Código</th>')
        && str_contains($locationsHtml, '<th>Producto</th>')
        && str_contains($locationsHtml, '<th>Categoría</th>')
        && str_contains($locationsHtml, '<th>Pasillo</th>')
        && str_contains($locationsHtml, '<th>Estante</th>')
        && str_contains($locationsHtml, '<th>Stock</th>')
        && str_contains($locationsHtml, '<td>' . $locatedId . '</td>')
        && str_contains($locationsHtml, '<td>' . $locatedName . '</td>')
        && str_contains($locationsHtml, '<td>5</td>')
        && str_contains($locationsHtml, 'A-01')
        && str_contains($locationsHtml, 'E-02')
        && str_contains($locationsHtml, $categoryName),
        'La tabla no presentó todos los datos del producto y su ubicación.'
    );
    $assert(
        str_contains($locationsHtml, 'name="busqueda"')
        && str_contains($locationsHtml, 'name="pasillo"')
        && str_contains($locationsHtml, 'name="modulo" value="ubicaciones"')
        && str_contains($locationsHtml, '<button type="submit">Filtrar</button>')
        && str_contains($locationsHtml, '<a href="?modulo=ubicaciones">Limpiar filtros</a>'),
        'La vista no presentó los filtros, el botón de búsqueda o la opción de limpiar.'
    );

    ob_start();
    $locationProducts = [array_replace($unlocatedProduct, ['pasillo' => ' ', 'estante' => ''])];
    require __DIR__ . '/../app/Views/locations.php';
    $emptyLocationHtml = ob_get_clean();
    $assert(str_contains($emptyLocationHtml, 'Sin ubicación'), 'La vista no trató campos de ubicación vacíos como ausencia de ubicación.');

    ob_start();
    $locationProducts = [];
    $locationFilters = ['busqueda' => 'HU13 inexistente ' . $suffix, 'id_producto' => null, 'pasillo' => ''];
    require __DIR__ . '/../app/Views/locations.php';
    $emptyHtml = ob_get_clean();
    $assert(str_contains($emptyHtml, 'No se encontraron productos'), 'La vista no explicó que no hubo coincidencias.');

    $countsDuringReads = [];
    foreach (array_keys($tableCounts) as $table) {
        $countsDuringReads[$table] = (int) $connection->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }
    $assert(
        $countsDuringReads['producto'] === $tableCounts['producto'] + 3
        && $countsDuringReads['categoria'] === $tableCounts['categoria'] + 1
        && $countsDuringReads['marca'] === $tableCounts['marca'] + 1
        && $countsDuringReads['proveedor'] === $tableCounts['proveedor'] + 1,
        'Las consultas de ubicaciones modificaron datos o dejaron de ver los fixtures.'
    );

    if ($existingProduct !== false) {
        $existingAfterReads = $connection->prepare(
            'SELECT `id`, `nombre`, `pasillo`, `estante`, `stock` FROM `producto` WHERE `id` = :id'
        );
        $existingAfterReads->execute([':id' => $existingProduct['id']]);
        $existingAfterReads = $existingAfterReads->fetch();
        $assert($existingAfterReads === $existingProduct, 'La consulta de ubicaciones alteró un producto preexistente.');
    }
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}

$finalCounts = [];
foreach (array_keys($tableCounts) as $table) {
    $finalCounts[$table] = (int) $connection->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}
$assert($finalCounts === $tableCounts, 'La prueba dejó fixtures de ubicaciones persistentes.');

echo "Pruebas de ubicaciones: {$tests} comprobaciones OK." . PHP_EOL;
