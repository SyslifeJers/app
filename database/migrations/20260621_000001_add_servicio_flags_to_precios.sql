ALTER TABLE `Precios`
    MODIFY COLUMN `sesiones_total` INT NOT NULL DEFAULT 1,
    ADD COLUMN `requiere_cita` TINYINT(1) NOT NULL DEFAULT 1 AFTER `sesiones_total`,
    ADD COLUMN `permite_sin_fecha` TINYINT(1) NOT NULL DEFAULT 0 AFTER `requiere_cita`;
