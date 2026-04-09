-- Registrar rol Practicante (solo lectura de agenda)
-- Nota: si ya existe el id=6 o el nombre, no hace cambios.
INSERT INTO `Rol` (`id`, `name`, `activo`)
SELECT 6, 'Practicante', 1
WHERE NOT EXISTS (
  SELECT 1 FROM `Rol` WHERE `id` = 6 OR `name` = 'Practicante'
);
