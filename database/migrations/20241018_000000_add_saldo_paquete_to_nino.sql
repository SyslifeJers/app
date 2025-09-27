ALTER TABLE `nino`
    ADD COLUMN `saldo_paquete` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `idtutor`;
