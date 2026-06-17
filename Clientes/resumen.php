<?php
include '../Modulos/head.php';

$tutorId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$tutor = null;
$pacientes = [];
$citasPorPaciente = [];
$pagosSinCitaPorPaciente = [];
$flujosPorPaciente = [];
$flujosDisponible = false;
$totales = [
    'pacientes' => 0,
    'citas' => 0,
    'finalizadas' => 0,
    'programadas' => 0,
    'canceladas' => 0,
    'recaudado' => 0.0,
];
$error = '';

function formatoFechaHoraResumen(?string $fecha): array
{
    if (empty($fecha)) {
        return ['-', '-'];
    }

    try {
        $dt = new DateTime($fecha);
        return [$dt->format('d/m/Y'), $dt->format('H:i')];
    } catch (Throwable $e) {
        return [$fecha, '-'];
    }
}

if ($tutorId <= 0) {
    $error = 'Selecciona un cliente valido.';
} else {
    $stmtTutor = $conn->prepare('SELECT id, name, telefono, correo, activo, fecha FROM Clientes WHERE id = ? LIMIT 1');
    if ($stmtTutor === false) {
        $error = 'No se pudo preparar la consulta del cliente.';
    } else {
        $stmtTutor->bind_param('i', $tutorId);
        $stmtTutor->execute();
        $resultadoTutor = $stmtTutor->get_result();
        $tutor = $resultadoTutor->fetch_assoc();
        $stmtTutor->close();

        if (!$tutor) {
            $error = 'No se encontro el cliente solicitado.';
        }
    }
}

if ($tutor) {
    $stmtPacientes = $conn->prepare('SELECT id, name, edad, activo, FechaIngreso, saldo_paquete FROM nino WHERE idtutor = ? ORDER BY name ASC');
    if ($stmtPacientes !== false) {
        $stmtPacientes->bind_param('i', $tutorId);
        $stmtPacientes->execute();
        $resultadoPacientes = $stmtPacientes->get_result();
        while ($paciente = $resultadoPacientes->fetch_assoc()) {
            $pacienteId = (int) $paciente['id'];
            $pacientes[] = $paciente;
            $citasPorPaciente[$pacienteId] = [];
            $pagosSinCitaPorPaciente[$pacienteId] = [];
            $flujosPorPaciente[$pacienteId] = [
                'asignados' => 0,
                'activos' => 0,
                'pendientes' => 0,
                'vencidas' => 0,
                'ultimo_id' => 0,
            ];
        }
        $stmtPacientes->close();
    }

    $totales['pacientes'] = count($pacientes);

    if (!empty($pacientes)) {
        $stmtCitas = $conn->prepare("SELECT ci.id,
                                           ci.IdNino,
                                           ci.Programado,
                                           ci.Tiempo,
                                           ci.costo,
                                           ci.Tipo,
                                           ci.FormaPago,
                                           es.name AS Estatus,
                                           us.name AS Psicologo,
                                           COALESCE(pagos.total_pagado, 0) AS total_pagado,
                                           COALESCE(pagos.detalle, '') AS pagos_detalle
                                    FROM Cita ci
                                    INNER JOIN nino n ON n.id = ci.IdNino
                                    LEFT JOIN Usuarios us ON us.id = ci.IdUsuario
                                    LEFT JOIN Estatus es ON es.id = ci.Estatus
                                     LEFT JOIN (
                                         SELECT cita_id,
                                               SUM(monto) AS total_pagado,
                                               GROUP_CONCAT(CONCAT_WS('::', id, metodo_pago, monto, fecha_pago, COALESCE(observaciones, '')) ORDER BY fecha_pago ASC, id ASC SEPARATOR '||') AS detalle
                                         FROM Pagos
                                         WHERE cita_id IS NOT NULL
                                         GROUP BY cita_id
                                     ) pagos ON pagos.cita_id = ci.id
                                    WHERE n.idtutor = ?
                                    ORDER BY ci.IdNino ASC, ci.Programado DESC");

        if ($stmtCitas !== false) {
            $stmtCitas->bind_param('i', $tutorId);
            $stmtCitas->execute();
            $resultadoCitas = $stmtCitas->get_result();
            while ($cita = $resultadoCitas->fetch_assoc()) {
                $pacienteId = (int) $cita['IdNino'];
                if (!isset($citasPorPaciente[$pacienteId])) {
                    $citasPorPaciente[$pacienteId] = [];
                }

                $estatus = trim((string) ($cita['Estatus'] ?? ''));
                $estatusLower = function_exists('mb_strtolower') ? mb_strtolower($estatus, 'UTF-8') : strtolower($estatus);
                $totalPagado = (float) ($cita['total_pagado'] ?? 0);

                $totales['citas']++;
                if ($estatusLower === 'finalizada') {
                    $totales['finalizadas']++;
                    $totales['recaudado'] += $totalPagado > 0 ? $totalPagado : (float) ($cita['costo'] ?? 0);
                } elseif ($estatusLower === 'cancelada') {
                    $totales['canceladas']++;
                } else {
                    $totales['programadas']++;
                }

                $citasPorPaciente[$pacienteId][] = $cita;
            }
            $stmtCitas->close();
        }

        $stmtPagosSinCita = $conn->prepare("SELECT p.id,
                                                   p.origen,
                                                   p.referencia_id,
                                                   p.paciente_id,
                                                   p.monto,
                                                   p.metodo_pago,
                                                   p.fecha_pago,
                                                   p.fecha_corte,
                                                   p.observaciones
                                            FROM Pagos p
                                            INNER JOIN nino n ON n.id = p.paciente_id
                                            WHERE n.idtutor = ? AND p.cita_id IS NULL
                                            ORDER BY p.paciente_id ASC, p.fecha_pago DESC, p.id DESC");

        if ($stmtPagosSinCita !== false) {
            $stmtPagosSinCita->bind_param('i', $tutorId);
            $stmtPagosSinCita->execute();
            $resultadoPagosSinCita = $stmtPagosSinCita->get_result();
            while ($pagoSinCita = $resultadoPagosSinCita->fetch_assoc()) {
                $pacienteIdPago = (int) ($pagoSinCita['paciente_id'] ?? 0);
                if (!isset($pagosSinCitaPorPaciente[$pacienteIdPago])) {
                    $pagosSinCitaPorPaciente[$pacienteIdPago] = [];
                }

                $pagosSinCitaPorPaciente[$pacienteIdPago][] = $pagoSinCita;
            }
            $stmtPagosSinCita->close();
        }

        $stmtFlujos = $conn->prepare("SELECT a.id_nino,
                                               COUNT(DISTINCT a.id_asignacion) AS asignados,
                                               COUNT(DISTINCT CASE WHEN a.estatus = 'activo' THEN a.id_asignacion END) AS activos,
                                               SUM(CASE WHEN ta.estatus IN ('pendiente', 'en_proceso') AND ta.activo = 1 THEN 1 ELSE 0 END) AS pendientes,
                                               SUM(CASE WHEN ta.estatus IN ('pendiente', 'en_proceso') AND ta.activo = 1 AND ta.fecha_limite IS NOT NULL AND ta.fecha_limite < CURDATE() THEN 1 ELSE 0 END) AS vencidas,
                                               MAX(a.id_asignacion) AS ultimo_id
                                        FROM exp_flujo_asignaciones a
                                        INNER JOIN nino n ON n.id = a.id_nino
                                        LEFT JOIN exp_flujo_tareas_asignadas ta ON ta.id_asignacion = a.id_asignacion
                                        WHERE n.idtutor = ? AND a.activo = 1
                                        GROUP BY a.id_nino");
        if ($stmtFlujos !== false) {
            $flujosDisponible = true;
            $stmtFlujos->bind_param('i', $tutorId);
            $stmtFlujos->execute();
            $resultadoFlujos = $stmtFlujos->get_result();
            while ($flujoResumen = $resultadoFlujos->fetch_assoc()) {
                $pacienteIdFlujo = (int) ($flujoResumen['id_nino'] ?? 0);
                if (isset($flujosPorPaciente[$pacienteIdFlujo])) {
                    $flujosPorPaciente[$pacienteIdFlujo] = [
                        'asignados' => (int) ($flujoResumen['asignados'] ?? 0),
                        'activos' => (int) ($flujoResumen['activos'] ?? 0),
                        'pendientes' => (int) ($flujoResumen['pendientes'] ?? 0),
                        'vencidas' => (int) ($flujoResumen['vencidas'] ?? 0),
                        'ultimo_id' => (int) ($flujoResumen['ultimo_id'] ?? 0),
                    ];
                }
            }
            $stmtFlujos->close();
        }
    }
}
?>

<div class="container mt-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="fas fa-arrow-left me-1"></i>Volver a clientes</a>
            <h2 class="h4 mb-1">Resumen del cliente</h2>
            <p class="text-muted mb-0">Detalle de pacientes asignados y citas generadas.</p>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif ($tutor): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-5">
                        <h3 class="h5 mb-1"><?php echo htmlspecialchars($tutor['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="text-muted small">Tutor ID: #<?php echo (int) $tutor['id']; ?></div>
                        <div class="text-muted small">Telefono: <?php echo htmlspecialchars($tutor['telefono'] ?: 'Sin registrar', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-muted small">Correo: <?php echo htmlspecialchars($tutor['correo'] ?: 'Sin registrar', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="col-lg-7">
                        <div class="row g-2 text-center">
                            <div class="col-6 col-md-2"><div class="border rounded-3 p-2"><div class="fw-bold"><?php echo (int) $totales['pacientes']; ?></div><small class="text-muted">Pacientes</small></div></div>
                            <div class="col-6 col-md-2"><div class="border rounded-3 p-2"><div class="fw-bold"><?php echo (int) $totales['citas']; ?></div><small class="text-muted">Citas</small></div></div>
                            <div class="col-6 col-md-2"><div class="border rounded-3 p-2"><div class="fw-bold"><?php echo (int) $totales['finalizadas']; ?></div><small class="text-muted">Finalizadas</small></div></div>
                            <div class="col-6 col-md-2"><div class="border rounded-3 p-2"><div class="fw-bold"><?php echo (int) $totales['programadas']; ?></div><small class="text-muted">Activas</small></div></div>
                            <div class="col-6 col-md-2"><div class="border rounded-3 p-2"><div class="fw-bold"><?php echo (int) $totales['canceladas']; ?></div><small class="text-muted">Canceladas</small></div></div>
                            <div class="col-6 col-md-2"><div class="border rounded-3 p-2"><div class="fw-bold">$<?php echo number_format($totales['recaudado'], 2); ?></div><small class="text-muted">Recaudado</small></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($pacientes)): ?>
            <div class="alert alert-info" role="alert">Este cliente no tiene ninos asignados.</div>
        <?php else: ?>
            <?php foreach ($pacientes as $paciente): ?>
                <?php
                $pacienteId = (int) $paciente['id'];
                $citas = $citasPorPaciente[$pacienteId] ?? [];
                $pagosSinCita = $pagosSinCitaPorPaciente[$pacienteId] ?? [];
                $flujosPaciente = $flujosPorPaciente[$pacienteId] ?? ['asignados' => 0, 'activos' => 0, 'pendientes' => 0, 'vencidas' => 0, 'ultimo_id' => 0];
                $historial = [];

                foreach ($citas as $cita) {
                    $historial[] = [
                        'tipo' => 'cita',
                        'fecha' => (string) ($cita['Programado'] ?? ''),
                        'data' => $cita,
                    ];
                }

                foreach ($pagosSinCita as $pagoSinCita) {
                    $historial[] = [
                        'tipo' => 'pago',
                        'fecha' => (string) ($pagoSinCita['fecha_pago'] ?? ''),
                        'data' => $pagoSinCita,
                    ];
                }

                usort($historial, static function (array $a, array $b): int {
                    return strcmp($b['fecha'], $a['fecha']);
                });
                ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between gap-2">
                        <div>
                            <h4 class="h5 mb-1"><i class="fas fa-child text-info me-2"></i><?php echo htmlspecialchars($paciente['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                            <div class="text-muted small">
                                Paciente ID: #<?php echo $pacienteId; ?> | Edad: <?php echo htmlspecialchars((string) ($paciente['edad'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?> | Saldo: $<?php echo number_format((float) ($paciente['saldo_paquete'] ?? 0), 2); ?>
                            </div>
                            <?php if ($flujosDisponible): ?>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <span class="badge bg-light text-dark">Flujos: <?php echo (int) $flujosPaciente['asignados']; ?></span>
                                    <span class="badge bg-primary">Activos: <?php echo (int) $flujosPaciente['activos']; ?></span>
                                    <span class="badge bg-warning text-dark">Tareas pendientes: <?php echo (int) $flujosPaciente['pendientes']; ?></span>
                                    <?php if ((int) $flujosPaciente['vencidas'] > 0): ?>
                                        <span class="badge bg-danger">Vencidas: <?php echo (int) $flujosPaciente['vencidas']; ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-start">
                            <a class="btn btn-sm btn-outline-primary" href="../FlujosSeguimiento/asignaciones.php">Asignar flujo</a>
                            <?php if ($flujosDisponible && (int) $flujosPaciente['ultimo_id'] > 0): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="../FlujosSeguimiento/seguimiento.php?id_asignacion=<?php echo (int) $flujosPaciente['ultimo_id']; ?>">Ver seguimiento</a>
                            <?php endif; ?>
                            <span class="badge align-self-start <?php echo ((int) ($paciente['activo'] ?? 0) === 1) ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo ((int) ($paciente['activo'] ?? 0) === 1) ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Registro</th>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th>Psicologo</th>
                                    <th>Tipo</th>
                                    <th>Estatus</th>
                                    <th>Forma de pago</th>
                                    <th class="text-end">Costo</th>
                                    <th class="text-end">Pagado</th>
                                    <th>Nota</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($historial)): ?>
                                    <tr><td colspan="11" class="text-center text-muted py-4">Sin citas ni pagos generados para este paciente.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($historial as $registro): ?>
                                        <?php
                                        if ($registro['tipo'] === 'pago') {
                                            $pagoSinCita = $registro['data'];
                                            [$fechaPagoSinCita, $horaPagoSinCita] = formatoFechaHoraResumen($pagoSinCita['fecha_pago'] ?? null);
                                            ?>
                                            <tr>
                                                <td><span class="badge bg-info text-dark"><i class="fas fa-receipt me-1"></i>Pago</span></td>
                                                <td>#<?php echo (int) $pagoSinCita['id']; ?></td>
                                                <td><?php echo htmlspecialchars($fechaPagoSinCita, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($horaPagoSinCita, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>Sin asignar</td>
                                                <td><?php echo htmlspecialchars((string) ($pagoSinCita['origen'] ?? 'Pago'), ENT_QUOTES, 'UTF-8'); ?> #<?php echo (int) ($pagoSinCita['referencia_id'] ?? 0); ?></td>
                                                <td>Pago sin cita</td>
                                                <td><?php echo htmlspecialchars((string) ($pagoSinCita['metodo_pago'] ?? 'Sin metodo'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-end">-</td>
                                                <td class="text-end fw-semibold">$<?php echo number_format((float) ($pagoSinCita['monto'] ?? 0), 2); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($pagoSinCita['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                            <?php
                                            continue;
                                        }

                                        $cita = $registro['data'];
                                        [$fechaCita, $horaCita] = formatoFechaHoraResumen($cita['Programado'] ?? null);
                                        ?>
                                        <tr>
                                            <td><span class="badge bg-primary"><i class="far fa-calendar-check me-1"></i>Cita</span></td>
                                            <td>#<?php echo (int) $cita['id']; ?></td>
                                            <td><?php echo htmlspecialchars($fechaCita, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($horaCita, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($cita['Psicologo'] ?: 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($cita['Tipo'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($cita['Estatus'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($cita['FormaPago'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end">$<?php echo number_format((float) ($cita['costo'] ?? 0), 2); ?></td>
                                            <td class="text-end">$<?php echo number_format((float) ($cita['total_pagado'] ?? 0), 2); ?></td>
                                            <td></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
include '../Modulos/footer.php';
?>
