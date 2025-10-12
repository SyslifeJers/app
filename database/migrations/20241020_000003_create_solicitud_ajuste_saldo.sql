-- Crea la tabla para almacenar las solicitudes de ajuste de saldo de los pacientes.
CREATE TABLE IF NOT EXISTS `SolicitudAjusteSaldo` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nino_id` INT NOT NULL,
    `solicitado_por` INT NOT NULL,
    `aprobado_por` INT DEFAULT NULL,
    `monto` DECIMAL(10,2) NOT NULL,
    `saldo_anterior` DECIMAL(10,2) NOT NULL,
    `saldo_solicitado` DECIMAL(10,2) NOT NULL,
    `comentario` VARCHAR(255) DEFAULT NULL,
    `respuesta` VARCHAR(255) DEFAULT NULL,
    `estatus` ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    `fecha_solicitud` DATETIME NOT NULL,
    `fecha_resolucion` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_solicitud_ajuste_estatus` (`estatus`),
    KEY `idx_solicitud_ajuste_fecha` (`fecha_solicitud`),
    CONSTRAINT `fk_solicitud_ajuste_nino` FOREIGN KEY (`nino_id`) REFERENCES `nino`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_solicitud_ajuste_solicitante` FOREIGN KEY (`solicitado_por`) REFERENCES `Usuarios`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_solicitud_ajuste_aprobador` FOREIGN KEY (`aprobado_por`) REFERENCES `Usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
