CREATE TABLE `historial_precio` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_producto` INT UNSIGNED NULL,
    `codigo_producto` INT UNSIGNED NOT NULL,
    `nombre_producto` VARCHAR(150) NOT NULL,
    `precio_anterior` DECIMAL(10,2) NULL,
    `precio_nuevo` DECIMAL(10,2) NULL,
    `area_responsable` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_historial_precio_producto` (`id_producto`),
    KEY `idx_historial_precio_codigo_fecha` (`codigo_producto`, `created_at`, `id`),
    KEY `idx_historial_precio_fecha_id` (`created_at`, `id`),
    CONSTRAINT `fk_historial_precio_producto`
        FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `producto`
    ADD COLUMN `pasillo` VARCHAR(50) NULL AFTER `precio_venta`,
    ADD COLUMN `estante` VARCHAR(50) NULL AFTER `pasillo`;

ALTER TABLE `producto`
    ADD CONSTRAINT `chk_producto_ubicacion_completa`
        CHECK (
            (`pasillo` IS NULL AND `estante` IS NULL)
            OR (
                `pasillo` IS NOT NULL
                AND `estante` IS NOT NULL
                AND CHAR_LENGTH(TRIM(`pasillo`)) > 0
                AND CHAR_LENGTH(TRIM(`estante`)) > 0
            )
        );

CREATE INDEX `idx_producto_pasillo` ON `producto` (`pasillo`);
