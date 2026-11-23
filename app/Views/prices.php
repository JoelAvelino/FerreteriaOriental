<?php

$escape = static fn ($value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formatPrice = static fn ($price): string => $price === null ? 'Sin precio asignado' : '$' . number_format((float) $price, 2, '.', ',');
$searchAction = '?modulo=precios';
$cancelParameters = ['modulo' => 'precios'];
if ($searchTerm !== '') {
    $cancelParameters['busqueda'] = $searchTerm;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Precios | Ferretería Oriental</title>
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
            <a href="?modulo=<?= $escape($key) ?>"<?= $key === 'precios' ? ' aria-current="page"' : '' ?>><?= $escape($item['title']) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($message !== null): ?>
        <div class="alert <?= $escape($messageType) ?>" role="status"><?= $escape($message) ?></div>
    <?php endif; ?>
    <?php if (isset($errors['_form'])): ?>
        <div class="alert error" role="alert"><?= $escape($errors['_form']) ?></div>
    <?php endif; ?>

    <section class="product-catalog" aria-labelledby="price-search-title">
        <h2 id="price-search-title">Actualizar precio de producto</h2>
        <form class="filters" method="get" action="<?= $escape($searchAction) ?>">
            <input type="hidden" name="modulo" value="precios">
            <div>
                <label for="busqueda">Buscar por código (ID) o nombre</label>
                <input id="busqueda" name="busqueda" type="search" value="<?= $escape($searchTerm) ?>" maxlength="150">
            </div>
            <div class="filter-actions">
                <button type="submit">Buscar producto</button>
            </div>
        </form>
        <?php if (isset($errors['busqueda'])): ?>
            <p class="field-error" role="alert"><?= $escape($errors['busqueda']) ?></p>
        <?php endif; ?>

        <?php if ($searchTerm === '' && !isset($errors['busqueda'])): ?>
            <p class="empty">Ingresa un código o al menos dos caracteres del nombre para buscar.</p>
        <?php elseif (!isset($errors['busqueda']) && !$searchResults): ?>
            <p class="empty">No se encontraron productos</p>
        <?php elseif ($searchResults): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Código</th><th>Producto</th><th>Precio actual</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($searchResults as $product): ?>
                        <tr>
                            <td><?= $escape($product['id']) ?></td>
                            <td><?= $escape($product['nombre']) ?></td>
                            <td><?= $escape($formatPrice($product['precio_venta'])) ?></td>
                            <td>
                                <a href="?modulo=precios&amp;id=<?= $escape($product['id']) ?>&amp;busqueda=<?= rawurlencode($searchTerm) ?>">Actualizar precio</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($selectedProduct !== null): ?>
        <section class="product-catalog" aria-labelledby="selected-product-title">
            <h2 id="selected-product-title">Producto seleccionado</h2>
            <dl class="sale-detail-meta">
                <div><dt>Código</dt><dd><?= $escape($selectedProduct['id']) ?></dd></div>
                <div><dt>Nombre</dt><dd><?= $escape($selectedProduct['nombre']) ?></dd></div>
                <div><dt>Precio actual</dt><dd><?= $escape($formatPrice($selectedProduct['precio_venta'])) ?></dd></div>
            </dl>

            <form method="post" action="?modulo=precios" class="price-update-form">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="_accion" value="actualizar_precio">
                <input type="hidden" name="id_producto" value="<?= $escape($selectedProduct['id']) ?>">
                <input type="hidden" name="busqueda" value="<?= $escape($searchTerm) ?>">
                <label for="precio_nuevo">Nuevo precio</label>
                <input
                    id="precio_nuevo"
                    name="precio_nuevo"
                    type="number"
                    min="0.01"
                    max="99999999.99"
                    step="0.01"
                    value="<?= $escape($newPrice) ?>"
                    required
                    aria-describedby="precio-nuevo-ayuda<?= isset($errors['precio_nuevo']) ? ' precio-nuevo-error' : '' ?>"
                >
                <p id="precio-nuevo-ayuda" class="empty">Usa un valor mayor que cero y con hasta dos decimales.</p>
                <?php if (isset($errors['precio_nuevo'])): ?>
                    <p id="precio-nuevo-error" class="field-error" role="alert"><?= $escape($errors['precio_nuevo']) ?></p>
                <?php endif; ?>
                <button type="submit" data-submit-button>Guardar nuevo precio</button>
                <a href="?<?= $escape(http_build_query($cancelParameters)) ?>">Cancelar</a>
            </form>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
