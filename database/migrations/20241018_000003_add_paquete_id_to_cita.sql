ALTER TABLE `Cita`
    ADD COLUMN `paquete_id` INT NULL AFTER `Tipo`,
    ADD CONSTRAINT `fk_cita_paquete`
        FOREIGN KEY (`paquete_id`) REFERENCES `Paquetes` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE;
