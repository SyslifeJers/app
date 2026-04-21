CREATE TABLE IF NOT EXISTS `ReservacionContinua` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `paciente_id` INT NOT NULL,
    `psicologo_id` INT NOT NULL,
    `tipo` VARCHAR(30) NOT NULL,
    `hora_inicio` TIME NOT NULL,
    `tiempo` INT NOT NULL DEFAULT 60,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NULL,
    `forzada` TINYINT(1) NOT NULL DEFAULT 0,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_por` INT NULL,
    `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_reservacion_continua_paciente` FOREIGN KEY (`paciente_id`) REFERENCES `nino` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_reservacion_continua_psicologo` FOREIGN KEY (`psicologo_id`) REFERENCES `Usuarios` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_reservacion_continua_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `Usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS `ReservacionContinuaDia` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `reservacion_id` INT NOT NULL,
    `dia_semana` TINYINT NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_reservacion_dia` (`reservacion_id`, `dia_semana`),
    CONSTRAINT `fk_reservacion_continua_dia_reservacion` FOREIGN KEY (`reservacion_id`) REFERENCES `ReservacionContinua` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);
