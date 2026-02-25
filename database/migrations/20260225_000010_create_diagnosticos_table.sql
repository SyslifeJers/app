CREATE TABLE IF NOT EXISTS `Diagnosticos` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nino_id` INT NOT NULL,
    `psicologo_id` INT NULL,
    `cita_inicial_id` INT NULL,
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
    KEY `idx_diagnosticos_nino` (`nino_id`),
    KEY `idx_diagnosticos_estatus` (`estatus_id`),
    KEY `idx_diagnosticos_psicologo` (`psicologo_id`),
    KEY `idx_diagnosticos_cita_inicial` (`cita_inicial_id`),
    CONSTRAINT `fk_diagnosticos_nino`
        FOREIGN KEY (`nino_id`) REFERENCES `nino` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_diagnosticos_psicologo`
        FOREIGN KEY (`psicologo_id`) REFERENCES `Usuarios` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT `fk_diagnosticos_cita_inicial`
        FOREIGN KEY (`cita_inicial_id`) REFERENCES `Cita` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT `fk_diagnosticos_estatus`
        FOREIGN KEY (`estatus_id`) REFERENCES `Estatus` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
