<?php

/** @var array $modules */
/** @var array $module */
/** @var array $records */
/** @var array $values */
/** @var array $errors */
/** @var array $options */
/** @var string|null $message */
/** @var string|null $messageType */
/** @var string|null $prerequisiteMessage */
/** @var bool $showListing */
/** @var string $csrfToken */

$escape = static fn ($value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$moduleKey = (string) ($_GET['modulo'] ?? 'categorias');
$productFormAction = $moduleKey === 'productos' && $productAction === 'editar'
    ? '?modulo=productos&accion=editar&id=' . $escape($editingProductId)
    : '?modulo=' . $escape($moduleKey);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($module['title']) ?> | Ferretería Oriental</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<header>
    <h1>Ferretería Oriental</h1>
    <p>Administración de categorías, marcas, proveedores y productos</p>
</header>
<main>
    <nav aria-label="Módulos">
        <?php foreach ($modules as $key => $item): ?>
            <a href="?modulo=<?= $escape($key) ?>"<?= $key === $moduleKey ? ' aria-current="page"' : '' ?>><?= $escape($item['title']) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!empty($productFormError)): ?>
        <div class="alert error" role="alert"><?= $escape($productFormError) ?></div>
    <?php endif; ?>
    <?php if ($message !== null): ?>
        <div class="alert <?= $escape($messageType) ?>" role="status"><?= $escape($message) ?></div>
    <?php endif; ?>
    <?php if ($prerequisiteMessage !== null): ?>
        <div class="alert error" role="alert"><?= $escape($prerequisiteMessage) ?></div>
    <?php endif; ?>

    <div class="columns">
        <section aria-labelledby="form-title">
            <h2 id="form-title"><?= $escape($moduleKey === 'productos' ? $productFormTitle : ($module['form_title'] ?? ('Registrar ' . mb_strtolower($module['title'], 'UTF-8')))) ?></h2>
            <?php if (!empty($errors['_form'])): ?>
                <div class="alert error" role="alert"><?= $escape($errors['_form']) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= $escape($productFormAction) ?>"<?= in_array($moduleKey, ['categorias', 'marcas', 'proveedores'], true) ? ' data-unique-name' : '' ?>>
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <?php if ($moduleKey === 'productos'): ?>
                    <input type="hidden" name="_accion" value="<?= $productAction === 'editar' ? 'actualizar' : 'crear' ?>">
                    <?php if ($productAction === 'editar' && $editingProductId !== null): ?>
                        <input type="hidden" name="_id" value="<?= $escape($editingProductId) ?>">
                    <?php endif; ?>
                <?php endif; ?>
                <?php foreach ($module['fields'] as $name => $field): ?>
                    <label for="<?= $escape($name) ?>"><?= $escape($field['label']) ?><?= $field['required'] ? ' *' : '' ?></label>
                    <?php if (($field['type'] ?? '') === 'select'): ?>
                        <select id="<?= $escape($name) ?>" name="<?= $escape($name) ?>"<?= $field['required'] ? ' required' : '' ?>>
                            <option value="">Selecciona <?= $escape(mb_strtolower($field['label'], 'UTF-8')) ?></option>
                            <?php foreach ($options[$name] ?? [] as $option): ?>
                                <option value="<?= $escape($option['id']) ?>"<?= (string) ($values[$name] ?? '') === (string) $option['id'] ? ' selected' : '' ?>><?= $escape($option['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($options[$name])): ?>
                            <p class="field-error">No hay opciones disponibles; registra primero un elemento de este tipo.</p>
                        <?php endif; ?>
                    <?php elseif (($field['type'] ?? '') === 'textarea'): ?>
                        <textarea id="<?= $escape($name) ?>" name="<?= $escape($name) ?>"<?= isset($field['max']) ? ' maxlength="' . $escape($field['max']) . '" data-character-count' : '' ?><?= $field['required'] ? ' required' : '' ?>><?= $escape($values[$name] ?? '') ?></textarea>
                    <?php else: ?>
                        <input id="<?= $escape($name) ?>" name="<?= $escape($name) ?>" type="<?= ($field['type'] ?? '') === 'email' ? 'email' : (($name === 'telefono') ? 'tel' : (($field['type'] ?? '') === 'integer' || ($field['type'] ?? '') === 'decimal' ? 'number' : 'text')) ?>" value="<?= $escape($values[$name] ?? '') ?>"<?= ($field['type'] ?? '') === 'integer' ? ' step="1"' : '' ?><?= ($field['type'] ?? '') === 'decimal' ? ' step="0.01"' : '' ?><?= isset($field['max']) ? ' maxlength="' . $escape($field['max']) . '"' : '' ?><?= $name === 'telefono' ? ' pattern="[0-9+(). -]{7,30}" title="Usa entre 7 y 30 caracteres: números, espacios o + ( ) . -"' : '' ?><?= $field['required'] ? ' required' : '' ?><?= isset($field['max']) ? ' data-character-count' : '' ?><?= $name === 'nombre' && in_array($moduleKey, ['categorias', 'marcas', 'proveedores'], true) ? ' data-unique-name-field aria-describedby="nombre-duplicate-error"' : '' ?>>
                    <?php endif; ?>
                    <?php if (isset($field['max'])): ?>
                        <p class="character-counter" id="<?= $escape($name) ?>-counter" aria-live="polite"></p>
                    <?php endif; ?>
                    <?php if ($name === 'nombre' && in_array($moduleKey, ['categorias', 'marcas', 'proveedores'], true)): ?>
                        <p class="field-error" id="nombre-duplicate-error" role="alert" hidden></p>
                    <?php endif; ?>
                    <?php if (isset($errors[$name])): ?>
                        <p class="field-error" role="alert"><?= $escape($errors[$name]) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
                <button type="submit" data-submit-button<?= $prerequisiteMessage !== null || $productFormError !== null ? ' disabled' : '' ?>><?= $moduleKey === 'productos' && $productAction === 'editar' ? 'Guardar cambios' : 'Guardar' ?></button>
            </form>
            <?php if ($moduleKey === 'productos' && $productAction === 'editar'): ?>
                <p><a href="?modulo=productos#catalogo">Cancelar edición</a></p>
            <?php endif; ?>
        </section>

        <?php if ($showListing): ?>
        <section aria-labelledby="list-title">
            <h2 id="list-title">Listado de <?= $escape(mb_strtolower($module['title'], 'UTF-8')) ?></h2>
            <?php if (!$records): ?>
                <p class="empty">Todavía no hay registros. Usa el formulario para agregar el primero.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <?php foreach ($module['fields'] as $field): ?>
                                <th><?= $escape($field['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?= $escape($record['id']) ?></td>
                                <?php foreach ($module['fields'] as $name => $field): ?>
                                    <td<?= $name === 'nombre' && in_array($moduleKey, ['categorias', 'marcas', 'proveedores'], true) ? ' data-existing-name="' . $escape($record[$name]) . '"' : '' ?>><?= nl2br($escape($record[$name])) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>

    <?php if ($moduleKey === 'productos'): ?>
        <section id="catalogo" class="product-catalog" aria-labelledby="product-list-title">
            <h2 id="product-list-title">Catálogo de productos</h2>
            <form class="filters" method="get" action="?modulo=productos#catalogo">
                <input type="hidden" name="modulo" value="productos">
                <div>
                    <label for="busqueda">Buscar por nombre o descripción</label>
                    <input id="busqueda" name="busqueda" type="search" value="<?= $escape($productFilters['busqueda']) ?>">
                </div>
                <?php foreach (['id_categoria' => 'Categoría', 'id_marca' => 'Marca', 'id_proveedor' => 'Proveedor'] as $field => $label): ?>
                    <div>
                        <label for="filtro-<?= $escape($field) ?>"><?= $escape($label) ?></label>
                        <select id="filtro-<?= $escape($field) ?>" name="<?= $escape($field) ?>">
                            <option value="">Todas</option>
                            <?php foreach ($options[$field] ?? [] as $option): ?>
                                <option value="<?= $escape($option['id']) ?>"<?= (string) ($productFilters[$field] ?? '') === (string) $option['id'] ? ' selected' : '' ?>><?= $escape($option['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($filterErrors[$field])): ?>
                            <p class="field-error" role="alert"><?= $escape($filterErrors[$field]) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (isset($filterErrors['busqueda'])): ?>
                    <p class="field-error" role="alert"><?= $escape($filterErrors['busqueda']) ?></p>
                <?php endif; ?>
                <div class="filter-actions">
                    <button type="submit">Buscar</button>
                    <a href="?modulo=productos#catalogo">Limpiar filtros</a>
                </div>
            </form>

            <?php if ($filterErrors): ?>
                <p class="alert error" role="alert">Corrige los filtros para consultar el catálogo.</p>
            <?php elseif (!$productRecords): ?>
                <p class="empty"><?= $productFilters['busqueda'] !== '' || $productFilters['id_categoria'] !== null || $productFilters['id_marca'] !== null || $productFilters['id_proveedor'] !== null
                    ? 'No hay productos que coincidan con los criterios seleccionados.'
                    : 'Todavía no hay productos registrados.' ?></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Precio de compra</th>
                            <th>Precio de venta</th>
                            <th>Existencia</th>
                            <th>Categoría</th>
                            <th>Marca</th>
                            <th>Proveedor</th>
                            <th>Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($productRecords as $product): ?>
                            <tr>
                                <td><?= $escape($product['nombre']) ?></td>
                                <td><?= nl2br($escape($product['descripcion'])) ?></td>
                                <td><?= $escape($product['precio_compra']) ?></td>
                                <td><?= $escape($product['precio_venta']) ?></td>
                                <td><?= $escape($product['stock']) ?></td>
                                <td><?= $escape($product['categoria']) ?></td>
                                <td><?= $escape($product['marca']) ?></td>
                                <td><?= $escape($product['proveedor']) ?></td>
                                <td><a href="?modulo=productos&amp;accion=editar&amp;id=<?= $escape($product['id']) ?>#form-title">Editar</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
