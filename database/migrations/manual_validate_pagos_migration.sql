-- Consultas de validacion posterior a manual_migrate_legacy_pagos_to_pagos.sql

-- Totales generales migrados.
SELECT 'Pagos nuevos' AS concepto, COUNT(*) AS registros, COALESCE(SUM(monto), 0) AS total
FROM Pagos;

SELECT 'PagoResumenDiario legacy' AS concepto, COUNT(*) AS registros, COALESCE(SUM(monto), 0) AS total
FROM PagoResumenDiario;

SELECT 'CitaPagos legacy' AS concepto, COUNT(*) AS registros, COALESCE(SUM(monto), 0) AS total
FROM CitaPagos;

-- Comparativo por fecha de corte entre resumen viejo y pagos nuevos migrados desde resumen.
SELECT
    pr.fecha_corte,
    COALESCE(pr.total_resumen, 0) AS total_resumen_viejo,
    COALESCE(p.total_pagos, 0) AS total_pagos_nuevo,
    COALESCE(p.total_pagos, 0) - COALESCE(pr.total_resumen, 0) AS diferencia
FROM (
    SELECT fecha_corte, SUM(monto) AS total_resumen
    FROM PagoResumenDiario
    GROUP BY fecha_corte
) pr
LEFT JOIN (
    SELECT fecha_corte, SUM(monto) AS total_pagos
    FROM Pagos
    WHERE observaciones LIKE 'Migrado PagoResumenDiario #%'
    GROUP BY fecha_corte
) p ON p.fecha_corte = pr.fecha_corte
ORDER BY pr.fecha_corte DESC;

-- Pagos legacy de citas que no encontraron equivalente directo en Pagos.
SELECT
    cp.id,
    cp.cita_id,
    cp.metodo,
    cp.monto,
    cp.creado_en
FROM CitaPagos cp
WHERE NOT EXISTS (
    SELECT 1
    FROM Pagos p
    WHERE p.origen IN ('cita', 'paquete')
      AND p.cita_id = cp.cita_id
      AND p.monto = cp.monto
      AND p.metodo_pago = cp.metodo
      AND DATE(p.fecha_pago) = DATE(cp.creado_en)
)
ORDER BY cp.creado_en DESC;

-- Pacientes con saldo actual y movimientos de saldo registrados.
SELECT
    n.id AS paciente_id,
    n.name AS paciente,
    n.saldo_paquete AS saldo_actual,
    COALESCE(SUM(sm.monto), 0) AS saldo_movimientos,
    n.saldo_paquete - COALESCE(SUM(sm.monto), 0) AS diferencia
FROM nino n
LEFT JOIN SaldoMovimientos sm ON sm.paciente_id = n.id
WHERE n.saldo_paquete <> 0 OR sm.id IS NOT NULL
GROUP BY n.id, n.name, n.saldo_paquete
ORDER BY ABS(n.saldo_paquete - COALESCE(SUM(sm.monto), 0)) DESC, n.name ASC;
