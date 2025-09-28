CREATE TABLE IF NOT EXISTS `Paquetes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `primer_pago_monto` DECIMAL(10,2) NOT NULL,
    `saldo_adicional` DECIMAL(10,2) NOT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_paquetes_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
