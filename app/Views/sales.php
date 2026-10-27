<?php

$escape = static fn ($value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$productIds = is_array($values['producto_id'] ?? null) ? array_values($values['producto_id']) : [];
$quantities = is_array($values['cantidad'] ?? null) ? array_values($values['cantidad']) : [];
$lineCount = max(1, count($productIds), count($quantities));
$selectedCustomerId = is_scalar($values['id_cliente'] ?? null) ? (string) $values['id_cliente'] : '';
$selectedPaymentMethodId = is_scalar($values['id_metodo_pago'] ?? null) ? (string) $values['id_metodo_pago'] : '';
$hasUsableProduct = false;
foreach ($options['productos'] as $product) {
    if ($product['precio_venta'] !== null && (float) $product['precio_venta'] > 0 && (int) $product['stock'] > 0) {
        $hasUsableProduct = true;
        break;
    }
}
$canSubmit = !empty($options['clientes'])
    && !empty($options['metodos_pago'])
    && $hasUsableProduct;
$renderProductOptions = static function ($selectedId) use ($options, $escape): void {
    echo '<option value="">Selecciona un producto</option>';
    foreach ($options['productos'] as $product) {
        $price = $product['precio_venta'];
        $stock = (int) $product['stock'];
        $available = $price !== null && (float) $price > 0 && $stock > 0;
        $label = $product['nombre'] . ' — precio ' . ($price ?? 'sin precio') . ' — stock ' . $stock;
        echo '<option value="' . $escape($product['id']) . '" data-price="' . $escape($price ?? '') . '" data-stock="' . $escape($stock) . '"'
            . ((string) $selectedId === (string) $product['id'] ? ' selected' : '')
            . (!$available ? ' disabled' : '')
            . '>' . $escape($label) . '</option>';
    }
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ventas | Ferretería Oriental</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <script src="assets/js/app.js" defer></script>
    <script src="assets/js/sales.js" defer></script>
</head>
<body>
<header>
    <h1>Ferretería Oriental</h1>
    <p>Administración de categorías, marcas, proveedores, clientes, métodos de pago, productos y ventas</p>
</header>
<main>
    <nav aria-label="Módulos">
        <?php foreach ($modules as $key => $item): ?>
            <a href="?modulo=<?= $escape($key) ?>"<?= $key === 'ventas' ? ' aria-current="page"' : '' ?>><?= $escape($item['title']) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!empty($message)): ?>
        <div class="alert <?= $escape($messageType) ?>" role="status"><?= $escape($message) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors['_form'])): ?>
        <div class="alert error" role="alert"><?= $escape($errors['_form']) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors['productos'])): ?>
        <div class="alert error" role="alert"><?= $escape($errors['productos']) ?></div>
    <?php endif; ?>

    <section class="sale-form-section">
        <h2>Registrar venta</h2>
        <?php if (empty($options['clientes'])): ?>
            <p class="field-error">Registra al menos un cliente antes de crear una venta.</p>
        <?php endif; ?>
        <?php if (empty($options['metodos_pago'])): ?>
            <p class="field-error">Registra al menos un método de pago antes de crear una venta.</p>
        <?php endif; ?>
        <?php if (!$hasUsableProduct): ?>
            <p class="field-error">No hay productos con precio de venta válido y existencias disponibles.</p>
        <?php endif; ?>

        <form method="post" action="?modulo=ventas">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

            <div class="sale-header-fields">
                <div>
                    <label for="id_cliente">Cliente *</label>
                    <select id="id_cliente" name="id_cliente" required>
                        <option value="">Selecciona un cliente</option>
                        <?php foreach ($options['clientes'] as $customer): ?>
                            <option value="<?= $escape($customer['id']) ?>"<?= $selectedCustomerId === (string) $customer['id'] ? ' selected' : '' ?>>
                                <?= $escape($customer['nombre']) ?><?= $customer['correo'] ? ' — ' . $escape($customer['correo']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['id_cliente'])): ?>
                        <p class="field-error" role="alert"><?= $escape($errors['id_cliente']) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="id_metodo_pago">Método de pago *</label>
                    <select id="id_metodo_pago" name="id_metodo_pago" required>
                        <option value="">Selecciona un método de pago</option>
                        <?php foreach ($options['metodos_pago'] as $paymentMethod): ?>
                            <option value="<?= $escape($paymentMethod['id']) ?>"<?= $selectedPaymentMethodId === (string) $paymentMethod['id'] ? ' selected' : '' ?>>
                                <?= $escape($paymentMethod['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['id_metodo_pago'])): ?>
                        <p class="field-error" role="alert"><?= $escape($errors['id_metodo_pago']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <h3>Productos</h3>
            <div class="table-wrap">
                <table class="sale-lines-table">
                    <thead>
                    <tr><th>Producto</th><th>Cantidad</th><th>Subtotal estimado</th><th>Acción</th></tr>
                    </thead>
                    <tbody data-sale-lines>
                    <?php for ($index = 0; $index < $lineCount; $index++): ?>
                        <?php $selectedProductId = isset($productIds[$index]) && is_scalar($productIds[$index]) ? (string) $productIds[$index] : ''; ?>
                        <tr data-sale-line>
                            <td>
                                <select name="producto_id[]" required data-sale-product>
                                    <?php $renderProductOptions($selectedProductId); ?>
                                </select>
                                <p class="field-error" data-duplicate-error hidden>No repitas un producto en varias líneas.</p>
                            </td>
                            <td>
                                <input name="cantidad[]" type="number" min="1" max="2147483647" step="1" value="<?= $escape($quantities[$index] ?? '1') ?>" required data-sale-quantity>
                            </td>
                            <td><output data-line-subtotal>0.00</output></td>
                            <td><button type="button" class="secondary-button" data-remove-sale-line>Quitar</button></td>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <template data-sale-line-template>
                <tr data-sale-line>
                    <td>
                        <select name="producto_id[]" required data-sale-product>
                            <?php $renderProductOptions(''); ?>
                        </select>
                        <p class="field-error" data-duplicate-error hidden>No repitas un producto en varias líneas.</p>
                    </td>
                    <td><input name="cantidad[]" type="number" min="1" max="2147483647" step="1" value="1" required data-sale-quantity></td>
                    <td><output data-line-subtotal>0.00</output></td>
                    <td><button type="button" class="secondary-button" data-remove-sale-line>Quitar</button></td>
                </tr>
            </template>
            <button type="button" class="secondary-button" data-add-sale-line>Agregar producto</button>

            <div class="sale-summary">
                <strong>Total estimado: <output data-sale-total>0.00</output></strong>
                <span>El importe final se recalcula y valida en el servidor.</span>
            </div>
            <button type="submit" data-submit-button<?= !$canSubmit ? ' disabled' : '' ?>>Registrar venta</button>
        </form>
    </section>
</main>
</body>
</html>
