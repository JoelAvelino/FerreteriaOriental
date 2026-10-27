<?php

$escape = static fn ($value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formatMoney = static fn ($amount): string => number_format((float) $amount, 2, '.', ',');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Historial de ventas | Ferretería Oriental</title>
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
            <a href="?modulo=<?= $escape($key) ?>"<?= $key === 'historial_ventas' ? ' aria-current="page"' : '' ?>><?= $escape($item['title']) ?></a>
        <?php endforeach; ?>
    </nav>

    <section class="sale-form-section">
        <h2>Historial de ventas</h2>
        <?php if ($historyError !== null): ?>
            <div class="alert error" role="alert"><?= $escape($historyError) ?></div>
        <?php endif; ?>

        <?php if ($sale !== null): ?>
            <section aria-labelledby="sale-detail-title">
                <h2 id="sale-detail-title">Detalle de la venta <?= $escape($sale['folio']) ?></h2>
                <dl class="sale-detail-meta">
                    <div><dt>Fecha</dt><dd><time datetime="<?= $escape($sale['created_at']) ?>"><?= $escape($sale['created_at']) ?></time></dd></div>
                    <div><dt>Cliente</dt><dd><?= $escape($sale['cliente']) ?></dd></div>
                    <div><dt>Método de pago</dt><dd><?= $escape($sale['metodo_pago']) ?></dd></div>
                </dl>
                <?php if (!$sale['detalles']): ?>
                    <p class="empty">Esta venta no tiene productos registrados.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr><th>Producto</th><th>Cantidad</th><th>Precio unitario histórico</th><th>Subtotal</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($sale['detalles'] as $detail): ?>
                                <tr>
                                    <td><?= $escape($detail['producto']) ?></td>
                                    <td><?= $escape($detail['cantidad']) ?></td>
                                    <td>$<?= $escape($formatMoney($detail['precio_unitario'])) ?></td>
                                    <td>$<?= $escape($formatMoney($detail['subtotal'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                            <tr><th colspan="3">Total de la venta</th><th>$<?= $escape($formatMoney($sale['total'])) ?></th></tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
                <p><a href="?modulo=historial_ventas">Volver al historial</a></p>
            </section>
        <?php endif; ?>

        <div class="table-wrap">
            <?php if ($sales): ?>
                <table>
                    <thead>
                    <tr><th>Folio</th><th>Fecha</th><th>Cliente</th><th>Total</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sales as $record): ?>
                        <tr>
                            <td><?= $escape($record['folio']) ?></td>
                            <td><time datetime="<?= $escape($record['created_at']) ?>"><?= $escape($record['created_at']) ?></time></td>
                            <td><?= $escape($record['cliente']) ?></td>
                            <td>$<?= $escape($formatMoney($record['total'])) ?></td>
                            <td><a href="?modulo=historial_ventas&amp;folio=<?= rawurlencode((string) $record['folio']) ?>">Consultar detalle</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($historyError === null): ?>
                <p class="empty">Aún no hay ventas registradas.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
