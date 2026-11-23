<?php

$escape = static fn ($value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ubicaciones | Ferretería Oriental</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<header>
    <h1>Ferretería Oriental</h1>
    <p>Administración de categorías, marcas, proveedores, clientes, métodos de pago, productos y ventas</p>
</header>
<main>
    <nav aria-label="Módulos">
        <?php foreach ($modules as $key => $item): ?>
            <a href="?modulo=<?= $escape($key) ?>"<?= $key === 'ubicaciones' ? ' aria-current="page"' : '' ?>><?= $escape($item['title']) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($message !== null): ?>
        <div class="alert <?= $escape($messageType) ?>" role="status"><?= $escape($message) ?></div>
    <?php endif; ?>

    <section class="product-catalog" aria-labelledby="locations-title">
        <h2 id="locations-title">Ubicaciones</h2>
        <form class="filters" method="get" action="?modulo=ubicaciones">
            <input type="hidden" name="modulo" value="ubicaciones">
            <div>
                <label for="busqueda">Buscar por código (ID) o nombre</label>
                <input id="busqueda" name="busqueda" type="search" maxlength="150" value="<?= $escape($locationFilters['busqueda']) ?>">
                <?php if (isset($locationErrors['busqueda'])): ?>
                    <p class="field-error" role="alert"><?= $escape($locationErrors['busqueda']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="pasillo">Filtrar por pasillo</label>
                <input id="pasillo" name="pasillo" type="search" maxlength="50" value="<?= $escape($locationFilters['pasillo']) ?>">
                <?php if (isset($locationErrors['pasillo'])): ?>
                    <p class="field-error" role="alert"><?= $escape($locationErrors['pasillo']) ?></p>
                <?php endif; ?>
            </div>
            <div class="filter-actions">
                <button type="submit">Filtrar</button>
                <a href="?modulo=ubicaciones">Limpiar filtros</a>
            </div>
        </form>

        <?php if ($locationError !== null): ?>
            <div class="alert error" role="alert"><?= $escape($locationError) ?></div>
        <?php elseif (!$locationErrors && !$locationProducts): ?>
            <p class="empty">No se encontraron productos</p>
        <?php elseif (!$locationErrors): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Pasillo</th>
                        <th>Estante</th>
                        <th>Stock</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($locationProducts as $product): ?>
                        <?php
                        $pasillo = trim((string) ($product['pasillo'] ?? ''));
                        $estante = trim((string) ($product['estante'] ?? ''));
                        $hasLocation = $pasillo !== '' && $estante !== '';
                        ?>
                        <tr>
                            <td><?= $escape($product['id']) ?></td>
                            <td><?= $escape($product['nombre']) ?></td>
                            <td><?= $escape($product['categoria']) ?></td>
                            <?php if ($hasLocation): ?>
                                <td><?= $escape($pasillo) ?></td>
                                <td><?= $escape($estante) ?></td>
                            <?php else: ?>
                                <td colspan="2">Sin ubicación</td>
                            <?php endif; ?>
                            <td><?= $escape($product['stock']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
