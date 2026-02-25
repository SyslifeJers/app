ALTER TABLE `Cita`
    ADD COLUMN `diagnostico_id` INT NULL AFTER `paquete_id`,
    ADD COLUMN `diagnostico_sesion` INT NULL AFTER `diagnostico_id`,
    ADD CONSTRAINT `fk_cita_diagnostico`
        FOREIGN KEY (`diagnostico_id`) REFERENCES `Diagnosticos` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE;
