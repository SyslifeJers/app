# Reglas Del Proyecto `git`

Este proyecto debe seguir las reglas generales de `../REGLAS_PROYECTOS.md`.

## Reglas Especificas

1. Usar `America/Mexico_City` para citas, reuniones, caja, pagos, reportes y logs.
2. No interpolar variables directamente en SQL; usar `prepare()` y `bind_param()`.
3. No usar `SHOW TABLES` ni scripts que requieran permisos administrativos de base de datos.
4. No aplicar `DROP TABLE`, `TRUNCATE`, `ALTER TABLE` o borrados masivos sin respaldo y confirmacion.
5. No usar procedimientos almacenados (`CREATE PROCEDURE`, `CALL`, `DROP PROCEDURE`).
6. Mantener `Usuarios`, `Cita`, `nino` y `Estatus` compatibles con `Terapia`.
7. Las reuniones normales y recurrentes creadas aqui pueden ser leidas por `Terapia`; no cambiar su estructura sin revisar esa app.
8. Si una consulta depende de una tabla opcional, manejar el fallo de forma controlada y no romper toda la pagina.
