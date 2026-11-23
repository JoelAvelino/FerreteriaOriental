<?php

$escape = static fn ($value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formatPrice = static fn ($price, string $emptyLabel): string => $price === null
    ? $emptyLabel
    : '$' . number_format((float) $price, 2, '.', ',');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Historial de precios | Ferretería Oriental</title>
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
            <a href="?modulo=<?= $escape($key) ?>"<?= $key === 'historial_precios' ? ' aria-current="page"' : '' ?>><?= $escape($item['title']) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($message !== null): ?>
        <div class="alert <?= $escape($messageType) ?>" role="status"><?= $escape($message) ?></div>
    <?php endif; ?>

    <section class="sale-form-section" aria-labelledby="price-history-title">
        <h2 id="price-history-title">Historial de precios</h2>
        <form class="filters" method="get" action="?modulo=historial_precios">
            <input type="hidden" name="modulo" value="historial_precios">
            <div>
                <label for="producto">Producto (código o nombre)</label>
                <input id="producto" name="producto" type="search" maxlength="150" value="<?= $escape($filters['producto']) ?>">
                <?php if (isset($filterErrors['producto'])): ?>
                    <p class="field-error" role="alert"><?= $escape($filterErrors['producto']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="fecha_inicio">Fecha inicial</label>
                <input id="fecha_inicio" name="fecha_inicio" type="date" value="<?= $escape($filters['fecha_inicio']) ?>">
                <?php if (isset($filterErrors['fecha_inicio'])): ?>
                    <p class="field-error" role="alert"><?= $escape($filterErrors['fecha_inicio']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="fecha_fin">Fecha final</label>
                <input id="fecha_fin" name="fecha_fin" type="date" value="<?= $escape($filters['fecha_fin']) ?>">
                <?php if (isset($filterErrors['fecha_fin'])): ?>
                    <p class="field-error" role="alert"><?= $escape($filterErrors['fecha_fin']) ?></p>
                <?php endif; ?>
            </div>
            <div class="filter-actions">
                <button type="submit">Filtrar</button>
                <a href="?modulo=historial_precios">Limpiar filtros</a>
            </div>
        </form>

        <?php if ($historyError !== null): ?>
            <div class="alert error" role="alert"><?= $escape($historyError) ?></div>
        <?php elseif (!$filterErrors && !$historyRecords): ?>
            <p class="empty">No se encontraron cambios de precio</p>
        <?php elseif (!$filterErrors): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Precio anterior</th>
                        <th>Precio nuevo</th>
                        <th>Responsable</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($historyRecords as $record): ?>
                        <?php $timestamp = new DateTimeImmutable($record['created_at']); ?>
                        <tr>
                            <td>
                                <time datetime="<?= $escape($timestamp->format('Y-m-d\TH:i:s')) ?>">
                                    <?= $escape($timestamp->format('d/m/Y H:i:s')) ?>
                                </time>
                            </td>
                            <td><?= $escape($record['codigo_producto']) ?></td>
                            <td><?= $escape($record['nombre_producto']) ?></td>
                            <td><?= $escape($formatPrice($record['precio_anterior'], 'Sin precio anterior')) ?></td>
                            <td><?= $escape($formatPrice($record['precio_nuevo'], 'Sin precio asignado')) ?></td>
                            <td><?= $escape($record['area_responsable']) ?></td>
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
