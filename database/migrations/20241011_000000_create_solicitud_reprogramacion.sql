-- Crear tabla para las solicitudes de reprogramación generadas por el equipo de ventas
CREATE TABLE IF NOT EXISTS `SolicitudReprogramacion` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `cita_id` INT NOT NULL,
  `fecha_anterior` DATETIME NOT NULL,
  `nueva_fecha` DATETIME NOT NULL,
  `estatus` ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `tipo` ENUM('reprogramacion','cancelacion') NOT NULL DEFAULT 'reprogramacion',
  `solicitado_por` INT NOT NULL,
  `comentarios` TEXT NULL,
  `fecha_solicitud` DATETIME NOT NULL,
  `aprobado_por` INT DEFAULT NULL,
  `fecha_respuesta` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_solicitud_estatus` (`estatus`),
  KEY `idx_solicitud_cita` (`cita_id`),
  CONSTRAINT `fk_solicitud_cita` FOREIGN KEY (`cita_id`) REFERENCES `Cita` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_solicitud_solicitante` FOREIGN KEY (`solicitado_por`) REFERENCES `Usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_solicitud_aprobador` FOREIGN KEY (`aprobado_por`) REFERENCES `Usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registrar el nuevo rol de Coordinador si no existe
INSERT INTO `Rol` (`id`, `name`, `activo`)
VALUES (4, 'Coordinador', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `activo` = VALUES(`activo`);
