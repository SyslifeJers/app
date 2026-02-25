CREATE TABLE IF NOT EXISTS `DiagnosticoPagos` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `diagnostico_id` INT NOT NULL,
    `metodo` VARCHAR(50) NOT NULL,
    `monto` DECIMAL(10,2) NOT NULL,
    `registrado_por` INT NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_diagnostico_pagos_diagnostico` (`diagnostico_id`),
    CONSTRAINT `fk_diagnostico_pagos_diagnostico`
        FOREIGN KEY (`diagnostico_id`) REFERENCES `Diagnosticos` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
