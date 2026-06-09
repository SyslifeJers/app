CREATE TABLE IF NOT EXISTS `ReunionInternaRecurrencia` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `titulo` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NULL,
    `hora_inicio` TIME NOT NULL,
    `hora_fin` TIME NOT NULL,
    `frecuencia` ENUM('semanal', 'mensual_dia_semana', 'anual_aviso') NOT NULL,
    `intervalo` INT NOT NULL DEFAULT 1,
    `dia_semana` TINYINT NULL,
    `semana_mes` TINYINT NULL,
    `mes_anual` TINYINT NULL,
    `dia_anual` TINYINT NULL,
    `bloquea_agenda` TINYINT(1) NOT NULL DEFAULT 1,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_por` INT NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_reunion_recurrencia_rango` (`activo`, `fecha_inicio`, `fecha_fin`),
    INDEX `idx_reunion_recurrencia_frecuencia` (`frecuencia`, `dia_semana`, `semana_mes`)
);

CREATE TABLE IF NOT EXISTS `ReunionInternaRecurrenciaPsicologo` (
    `recurrencia_id` INT NOT NULL,
    `psicologo_id` INT NOT NULL,
    PRIMARY KEY (`recurrencia_id`, `psicologo_id`),
    CONSTRAINT `fk_reunion_recurrencia_psicologo_recurrencia` FOREIGN KEY (`recurrencia_id`) REFERENCES `ReunionInternaRecurrencia` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS `ReunionInternaRecurrenciaExcepcion` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `recurrencia_id` INT NOT NULL,
    `fecha_ocurrencia` DATE NOT NULL,
    `accion` ENUM('cancelada') NOT NULL DEFAULT 'cancelada',
    `creado_por` INT NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_reunion_recurrencia_excepcion` (`recurrencia_id`, `fecha_ocurrencia`, `accion`),
    CONSTRAINT `fk_reunion_recurrencia_excepcion_recurrencia` FOREIGN KEY (`recurrencia_id`) REFERENCES `ReunionInternaRecurrencia` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
