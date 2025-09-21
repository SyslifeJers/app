CREATE TABLE IF NOT EXISTS `LogSistema` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_id` INT NULL,
  `modulo` VARCHAR(100) NOT NULL,
  `accion` VARCHAR(100) NOT NULL,
  `descripcion` TEXT NOT NULL,
  `entidad` VARCHAR(100) DEFAULT NULL,
  `referencia` VARCHAR(100) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_logs_fecha` (`fecha`),
  KEY `idx_logs_modulo` (`modulo`),
  KEY `idx_logs_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
