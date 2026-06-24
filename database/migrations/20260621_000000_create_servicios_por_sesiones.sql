ALTER TABLE `Precios`
    ADD COLUMN `sesiones_total` INT NOT NULL DEFAULT 1 AFTER `costo`;

CREATE TABLE IF NOT EXISTS `ServiciosPaciente` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `precio_id` INT NOT NULL,
    `nino_id` INT NOT NULL,
    `psicologo_id` INT NULL,
    `total` DECIMAL(10,2) NOT NULL,
    `pago_inicial` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `saldo_restante` DECIMAL(10,2) NOT NULL,
    `sesiones_total` INT NOT NULL,
    `sesiones_completadas` INT NOT NULL DEFAULT 0,
    `estatus_id` INT NOT NULL DEFAULT 2,
    `creado_por` INT NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_servicios_paciente_precio` (`precio_id`),
    KEY `idx_servicios_paciente_nino` (`nino_id`),
    KEY `idx_servicios_paciente_estatus` (`estatus_id`),
    KEY `idx_servicios_paciente_psicologo` (`psicologo_id`),
    CONSTRAINT `fk_servicios_paciente_precio`
        FOREIGN KEY (`precio_id`) REFERENCES `Precios` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_servicios_paciente_nino`
        FOREIGN KEY (`nino_id`) REFERENCES `nino` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_servicios_paciente_psicologo`
        FOREIGN KEY (`psicologo_id`) REFERENCES `Usuarios` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT `fk_servicios_paciente_estatus`
        FOREIGN KEY (`estatus_id`) REFERENCES `Estatus` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ServicioPagos` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `servicio_paciente_id` INT NOT NULL,
    `cita_id` INT NULL,
    `metodo` VARCHAR(50) NOT NULL,
    `monto` DECIMAL(10,2) NOT NULL,
    `registrado_por` INT NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_servicio_pagos_servicio` (`servicio_paciente_id`),
    KEY `idx_servicio_pagos_cita` (`cita_id`),
    CONSTRAINT `fk_servicio_pagos_servicio`
        FOREIGN KEY (`servicio_paciente_id`) REFERENCES `ServiciosPaciente` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_servicio_pagos_cita`
        FOREIGN KEY (`cita_id`) REFERENCES `Cita` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `Cita`
    ADD COLUMN `servicio_paciente_id` INT NULL AFTER `paquete_id`,
    ADD COLUMN `servicio_sesion` INT NULL AFTER `servicio_paciente_id`,
    ADD KEY `idx_cita_servicio_paciente` (`servicio_paciente_id`),
    ADD CONSTRAINT `fk_cita_servicio_paciente`
        FOREIGN KEY (`servicio_paciente_id`) REFERENCES `ServiciosPaciente` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE;
