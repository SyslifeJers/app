CREATE TABLE IF NOT EXISTS `CorteCaja` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `fecha` DATE NOT NULL,
    `efectivo_inicial` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `registrado_por` INT NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_cortecaja_fecha` (`fecha`),
    KEY `idx_cortecaja_registrado_por` (`registrado_por`),
    CONSTRAINT `fk_cortecaja_usuario`
        FOREIGN KEY (`registrado_por`) REFERENCES `Usuarios` (`id`)
        ON DELETE SET NULL
);
