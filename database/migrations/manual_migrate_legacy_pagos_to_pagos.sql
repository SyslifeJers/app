-- Migra pagos historicos al nuevo modelo `Pagos` / `SaldoMovimientos`.
-- Ejecutar despues de crear las tablas con:
-- database/migrations/20260512_000000_create_pagos_saldo_movimientos_tables.sql
--
-- El script es idempotente: puede ejecutarse mas de una vez sin duplicar registros
-- generados por esta migracion.

START TRANSACTION;

-- 1) Fuente principal: PagoResumenDiario.
-- Conserva fecha_pago, fecha_corte, origen, paciente, psicologo y notas usadas por corte.
INSERT INTO Pagos (
    origen,
    referencia_id,
    cita_id,
    paquete_id,
    paciente_id,
    paciente_nombre,
    psicologo_id,
    psicologo_nombre,
    monto,
    metodo_pago,
    fecha_pago,
    fecha_corte,
    registrado_por,
    observaciones,
    creado_en
)
SELECT
    CASE
        WHEN pr.observaciones LIKE '%paquete%' THEN 'paquete'
        ELSE pr.origen
    END AS origen,
    pr.id AS referencia_id,
    pr.cita_id,
    ci.paquete_id,
    COALESCE(pr.paciente_id, ci.IdNino, d.nino_id, ad.nino_id) AS paciente_id,
    LEFT(COALESCE(NULLIF(pr.paciente_nombre, ''), n.name, nd.name, nad.name, 'Sin paciente'), 150) AS paciente_nombre,
    COALESCE(pr.psicologo_id, ci.IdUsuario, d.psicologo_id, ad.psicologo_id) AS psicologo_id,
    LEFT(COALESCE(NULLIF(pr.psicologo_nombre, ''), u.name, ud.name, uad.name, 'Sin asignar'), 150) AS psicologo_nombre,
    pr.monto,
    pr.metodo_pago,
    pr.fecha_pago,
    pr.fecha_corte,
    pr.registrado_por,
    LEFT(CONCAT('Migrado PagoResumenDiario #', pr.id, IF(pr.observaciones IS NULL OR pr.observaciones = '', '', CONCAT(': ', pr.observaciones))), 255) AS observaciones,
    pr.creado_en
FROM PagoResumenDiario pr
LEFT JOIN Cita ci ON ci.id = pr.cita_id
LEFT JOIN nino n ON n.id = ci.IdNino
LEFT JOIN Usuarios u ON u.id = ci.IdUsuario
LEFT JOIN Diagnosticos d ON d.id = pr.diagnostico_id
LEFT JOIN nino nd ON nd.id = d.nino_id
LEFT JOIN Usuarios ud ON ud.id = d.psicologo_id
LEFT JOIN AdeudosDiagnostico ad ON ad.id = pr.adeudo_id
LEFT JOIN nino nad ON nad.id = ad.nino_id
LEFT JOIN Usuarios uad ON uad.id = ad.psicologo_id
WHERE pr.monto > 0
  AND COALESCE(pr.paciente_id, ci.IdNino, d.nino_id, ad.nino_id) IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM Pagos p
      WHERE p.origen = CASE WHEN pr.observaciones LIKE '%paquete%' THEN 'paquete' ELSE pr.origen END
        AND p.referencia_id = pr.id
        AND p.observaciones LIKE CONCAT('Migrado PagoResumenDiario #', pr.id, '%')
  );

-- 2) Complemento: CitaPagos que no tienen fila equivalente migrada desde PagoResumenDiario.
-- Usa CitaPagos.id como referencia para evitar duplicados.
INSERT INTO Pagos (
    origen,
    referencia_id,
    cita_id,
    paquete_id,
    paciente_id,
    paciente_nombre,
    psicologo_id,
    psicologo_nombre,
    monto,
    metodo_pago,
    fecha_pago,
    fecha_corte,
    registrado_por,
    observaciones,
    creado_en
)
SELECT
    CASE
        WHEN ci.paquete_id IS NOT NULL OR ci.FormaPago LIKE 'Paquete%' THEN 'paquete'
        ELSE 'cita'
    END AS origen,
    cp.id AS referencia_id,
    cp.cita_id,
    ci.paquete_id,
    ci.IdNino AS paciente_id,
    LEFT(COALESCE(n.name, 'Sin paciente'), 150) AS paciente_nombre,
    ci.IdUsuario AS psicologo_id,
    LEFT(COALESCE(u.name, 'Sin asignar'), 150) AS psicologo_nombre,
    cp.monto,
    cp.metodo,
    cp.creado_en AS fecha_pago,
    DATE(cp.creado_en) AS fecha_corte,
    cp.registrado_por,
    LEFT(CONCAT('Migrado CitaPagos #', cp.id, ' de cita #', cp.cita_id), 255) AS observaciones,
    cp.creado_en
FROM CitaPagos cp
INNER JOIN Cita ci ON ci.id = cp.cita_id
INNER JOIN nino n ON n.id = ci.IdNino
LEFT JOIN Usuarios u ON u.id = ci.IdUsuario
WHERE cp.monto > 0
  AND NOT EXISTS (
      SELECT 1
      FROM Pagos p
      WHERE p.origen IN ('cita', 'paquete')
        AND p.cita_id = cp.cita_id
        AND p.monto = cp.monto
        AND p.metodo_pago = cp.metodo
        AND DATE(p.fecha_pago) = DATE(cp.creado_en)
  )
  AND NOT EXISTS (
      SELECT 1
      FROM Pagos p2
      WHERE p2.referencia_id = cp.id
        AND p2.observaciones LIKE CONCAT('Migrado CitaPagos #', cp.id, '%')
  );

-- 3) Movimientos de saldo por paquetes migrados.
-- Si el pago migrado corresponde a paquete, crea el movimiento de saldo otorgado.
-- Usa saldo_anterior/saldo_nuevo informativos calculados contra el saldo actual para no alterar saldos reales.
INSERT INTO SaldoMovimientos (
    paciente_id,
    tipo,
    monto,
    saldo_anterior,
    saldo_nuevo,
    pago_id,
    cita_id,
    paquete_id,
    registrado_por,
    observaciones,
    creado_en
)
SELECT
    p.paciente_id,
    'paquete' AS tipo,
    pa.saldo_adicional AS monto,
    COALESCE(n.saldo_paquete, 0) - pa.saldo_adicional AS saldo_anterior,
    COALESCE(n.saldo_paquete, 0) AS saldo_nuevo,
    p.id AS pago_id,
    p.cita_id,
    p.paquete_id,
    p.registrado_por,
    LEFT(CONCAT('Migrado saldo de paquete desde pago #', p.id), 255) AS observaciones,
    p.creado_en
FROM Pagos p
INNER JOIN Paquetes pa ON pa.id = p.paquete_id
INNER JOIN nino n ON n.id = p.paciente_id
WHERE p.origen = 'paquete'
  AND pa.saldo_adicional <> 0
  AND NOT EXISTS (
      SELECT 1
      FROM SaldoMovimientos sm
      WHERE sm.pago_id = p.id
        AND sm.tipo = 'paquete'
  );

-- 4) Movimiento inicial para pacientes con saldo actual sin historial migrado.
-- No modifica nino.saldo_paquete; solo deja rastro para auditoria inicial.
INSERT INTO SaldoMovimientos (
    paciente_id,
    tipo,
    monto,
    saldo_anterior,
    saldo_nuevo,
    pago_id,
    cita_id,
    paquete_id,
    registrado_por,
    observaciones,
    creado_en
)
SELECT
    n.id,
    'saldo_inicial' AS tipo,
    n.saldo_paquete,
    0,
    n.saldo_paquete,
    NULL,
    NULL,
    NULL,
    NULL,
    'Migrado saldo actual inicial del paciente.',
    NOW()
FROM nino n
WHERE n.saldo_paquete <> 0
  AND NOT EXISTS (
      SELECT 1
      FROM SaldoMovimientos sm
      WHERE sm.paciente_id = n.id
        AND sm.tipo = 'saldo_inicial'
  );

COMMIT;
