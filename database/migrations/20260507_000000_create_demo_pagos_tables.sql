CREATE TABLE IF NOT EXISTS `DemoPagos` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `origen` VARCHAR(30) NOT NULL,
    `referencia_id` INT NULL,
    `cita_id` INT NULL,
    `paquete_id` INT NULL,
    `paciente_id` INT NOT NULL,
    `paciente_nombre` VARCHAR(150) NOT NULL,
    `psicologo_id` INT NULL,
    `psicologo_nombre` VARCHAR(150) NULL,
    `monto` DECIMAL(10,2) NOT NULL,
    `metodo_pago` VARCHAR(50) NOT NULL,
    `fecha_pago` DATETIME NOT NULL,
    `fecha_corte` DATE NOT NULL,
    `registrado_por` INT NULL,
    `observaciones` VARCHAR(255) NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_demo_pagos_origen_referencia` (`origen`, `referencia_id`),
    KEY `idx_demo_pagos_cita` (`cita_id`),
    KEY `idx_demo_pagos_paquete` (`paquete_id`),
    KEY `idx_demo_pagos_paciente` (`paciente_id`),
    KEY `idx_demo_pagos_fecha_corte` (`fecha_corte`),
    KEY `idx_demo_pagos_metodo` (`metodo_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `DemoSaldoMovimientos` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `paciente_id` INT NOT NULL,
    `tipo` VARCHAR(30) NOT NULL,
    `monto` DECIMAL(10,2) NOT NULL,
    `saldo_anterior` DECIMAL(10,2) NOT NULL,
    `saldo_nuevo` DECIMAL(10,2) NOT NULL,
    `pago_id` INT NULL,
    `cita_id` INT NULL,
    `paquete_id` INT NULL,
    `registrado_por` INT NULL,
    `observaciones` VARCHAR(255) NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_demo_saldo_paciente` (`paciente_id`),
    KEY `idx_demo_saldo_tipo` (`tipo`),
    KEY `idx_demo_saldo_pago` (`pago_id`),
    KEY `idx_demo_saldo_cita` (`cita_id`),
    KEY `idx_demo_saldo_paquete` (`paquete_id`),
    CONSTRAINT `fk_demo_saldo_pago`
        FOREIGN KEY (`pago_id`) REFERENCES `DemoPagos` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
