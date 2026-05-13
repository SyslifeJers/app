<?php
include '../Modulos/head.php';

$tutorId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$tutor = null;
$pacientes = [];
$citasPorPaciente = [];
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
                                           COALESCE(pagos.total_pagado, 0) AS total_pagado
                                    FROM Cita ci
                                    INNER JOIN nino n ON n.id = ci.IdNino
                                    LEFT JOIN Usuarios us ON us.id = ci.IdUsuario
                                    LEFT JOIN Estatus es ON es.id = ci.Estatus
                                    LEFT JOIN (
                                        SELECT cita_id, SUM(monto) AS total_pagado
                                        FROM CitaPagos
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
                ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between gap-2">
                        <div>
                            <h4 class="h5 mb-1"><i class="fas fa-child text-info me-2"></i><?php echo htmlspecialchars($paciente['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                            <div class="text-muted small">
                                Paciente ID: #<?php echo $pacienteId; ?> | Edad: <?php echo htmlspecialchars((string) ($paciente['edad'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?> | Saldo: $<?php echo number_format((float) ($paciente['saldo_paquete'] ?? 0), 2); ?>
                            </div>
                        </div>
                        <span class="badge align-self-start <?php echo ((int) ($paciente['activo'] ?? 0) === 1) ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo ((int) ($paciente['activo'] ?? 0) === 1) ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th>Psicologo</th>
                                    <th>Tipo</th>
                                    <th>Estatus</th>
                                    <th>Forma de pago</th>
                                    <th class="text-end">Costo</th>
                                    <th class="text-end">Pagado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($citas)): ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4">Sin citas generadas para este paciente.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($citas as $cita): ?>
                                        <?php [$fechaCita, $horaCita] = formatoFechaHoraResumen($cita['Programado'] ?? null); ?>
                                        <tr>
                                            <td>#<?php echo (int) $cita['id']; ?></td>
                                            <td><?php echo htmlspecialchars($fechaCita, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($horaCita, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($cita['Psicologo'] ?: 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($cita['Tipo'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($cita['Estatus'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($cita['FormaPago'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end">$<?php echo number_format((float) ($cita['costo'] ?? 0), 2); ?></td>
                                            <td class="text-end">$<?php echo number_format((float) ($cita['total_pagado'] ?? 0), 2); ?></td>
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
