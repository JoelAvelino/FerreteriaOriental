CREATE DATABASE IF NOT EXISTS `ferreteria_oriental` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ferreteria_oriental`;

CREATE TABLE IF NOT EXISTS `categoria` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_categoria_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `marca` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(150) NOT NULL,
    `pais_origen` VARCHAR(120) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_marca_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proveedor` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(150) NOT NULL,
    `contacto` VARCHAR(150) NOT NULL,
    `telefono` VARCHAR(30) NULL,
    `correo` VARCHAR(150) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_proveedor_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `producto` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `id_categoria` INT UNSIGNED NOT NULL,
    `id_marca` INT UNSIGNED NOT NULL,
    `id_proveedor` INT UNSIGNED NOT NULL,
    `stock` INT NOT NULL DEFAULT 0,
    `precio_compra` DECIMAL(10,2) NULL,
    `precio_venta` DECIMAL(10,2) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_producto_categoria` (`id_categoria`),
    KEY `idx_producto_marca` (`id_marca`),
    KEY `idx_producto_proveedor` (`id_proveedor`),
    CONSTRAINT `fk_producto_categoria`
        FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_producto_marca`
        FOREIGN KEY (`id_marca`) REFERENCES `marca` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_producto_proveedor`
        FOREIGN KEY (`id_proveedor`) REFERENCES `proveedor` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
