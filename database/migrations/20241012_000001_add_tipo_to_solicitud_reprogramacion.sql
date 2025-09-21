-- Agrega la columna `tipo` para distinguir solicitudes de reprogramación y cancelación
ALTER TABLE `SolicitudReprogramacion`
    ADD COLUMN IF NOT EXISTS `tipo` ENUM('reprogramacion','cancelacion') NOT NULL DEFAULT 'reprogramacion' AFTER `estatus`;

-- Garantiza que los registros existentes queden marcados como solicitudes de reprogramación
UPDATE `SolicitudReprogramacion`
SET `tipo` = 'reprogramacion'
WHERE `tipo` IS NULL;
