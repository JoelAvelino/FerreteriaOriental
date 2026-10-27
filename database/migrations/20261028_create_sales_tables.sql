CREATE TABLE IF NOT EXISTS `venta` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `folio` VARCHAR(32) NOT NULL,
    `id_cliente` INT UNSIGNED NOT NULL,
    `id_metodo_pago` INT UNSIGNED NOT NULL,
    `total` DECIMAL(12,2) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_venta_folio` (`folio`),
    KEY `idx_venta_cliente` (`id_cliente`),
    KEY `idx_venta_metodo_pago` (`id_metodo_pago`),
    CONSTRAINT `fk_venta_cliente`
        FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_venta_metodo_pago`
        FOREIGN KEY (`id_metodo_pago`) REFERENCES `metodo_pago` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_venta_total` CHECK (`total` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `detalle_venta` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_venta` BIGINT UNSIGNED NOT NULL,
    `id_producto` INT UNSIGNED NOT NULL,
    `cantidad` INT UNSIGNED NOT NULL,
    `precio_unitario` DECIMAL(10,2) NOT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_detalle_venta_producto` (`id_venta`, `id_producto`),
    KEY `idx_detalle_venta_producto` (`id_producto`),
    CONSTRAINT `fk_detalle_venta_venta`
        FOREIGN KEY (`id_venta`) REFERENCES `venta` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_detalle_venta_producto`
        FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_detalle_venta_cantidad` CHECK (`cantidad` > 0),
    CONSTRAINT `chk_detalle_venta_importes` CHECK (`precio_unitario` > 0 AND `subtotal` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
