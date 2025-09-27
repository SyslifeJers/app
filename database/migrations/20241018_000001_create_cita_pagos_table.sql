CREATE TABLE IF NOT EXISTS `CitaPagos` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `cita_id` INT NOT NULL,
    `metodo` VARCHAR(50) NOT NULL,
    `monto` DECIMAL(10,2) NOT NULL,
    `registrado_por` INT NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cita_pagos_cita_id` (`cita_id`),
    CONSTRAINT `fk_cita_pagos_cita`
        FOREIGN KEY (`cita_id`) REFERENCES `Cita` (`id`)
        ON DELETE CASCADE
);
