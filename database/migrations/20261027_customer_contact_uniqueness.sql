ALTER TABLE `cliente`
    DROP INDEX `uk_cliente_nombre`,
    ADD UNIQUE KEY `uk_cliente_correo` (`correo`),
    ADD UNIQUE KEY `uk_cliente_telefono` (`telefono`);
