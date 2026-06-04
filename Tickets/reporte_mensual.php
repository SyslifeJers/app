<?php
include '../Modulos/head.php';

date_default_timezone_set('America/Mexico_City');

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
$esAdminTickets = ($rolUsuario === 3);
$idUsuario = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;

function ticketReporteEstadoTexto(string $estado): string
{
    switch (strtolower(trim($estado))) {
        case 'abierto':
            return 'Abierto';
        case 'en_progreso':
            return 'En progreso';
        case 'resuelto':
            return 'Resuelto';
        case 'cerrado':
            return 'Cerrado';
        default:
            return $estado !== '' ? $estado : 'N/D';
    }
}

function ticketReporteEstadoClase(string $estado): string
{
    switch (strtolower(trim($estado))) {
        case 'abierto':
            return 'bg-danger-subtle text-danger-emphasis border border-danger-subtle';
        case 'en_progreso':
            return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        case 'resuelto':
            return 'bg-success-subtle text-success-emphasis border border-success-subtle';
        case 'cerrado':
            return 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle';
        default:
            return 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle';
    }
}

function ticketReporteFecha(?string $fecha): string
{
    if ($fecha === null || trim($fecha) === '') {
        return '';
    }

    try {
        return (new DateTime($fecha, new DateTimeZone('America/Mexico_City')))->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return $fecha;
    }
}

function ticketReportePorcentaje(int $valor, int $total): string
{
    if ($total <= 0) {
        return '0%';
    }

    return number_format(($valor / $total) * 100, 1) . '%';
}

$mesParam = isset($_GET['mes']) ? trim((string) $_GET['mes']) : date('Y-m');
$mesObj = DateTime::createFromFormat('Y-m-d', $mesParam . '-01', new DateTimeZone('America/Mexico_City'));
if (!$mesObj || $mesObj->format('Y-m') !== $mesParam) {
    $mesObj = new DateTime('first day of this month', new DateTimeZone('America/Mexico_City'));
    $mesParam = $mesObj->format('Y-m');
}

$inicioMes = $mesObj->format('Y-m-01 00:00:00');
$finMes = (clone $mesObj)->modify('last day of this month')->format('Y-m-d 23:59:59');
$mesAnterior = (clone $mesObj)->modify('-1 month')->format('Y-m');
$mesSiguiente = (clone $mesObj)->modify('+1 month')->format('Y-m');
$tituloMes = $mesObj->format('m/Y');

$condiciones = ['t.created_at BETWEEN ? AND ?'];
$tipos = 'ss';
$parametros = [$inicioMes, $finMes];

if (!$esAdminTickets) {
    $condiciones[] = 't.creado_por = ?';
    $tipos .= 'i';
    $parametros[] = $idUsuario;
}

$where = implode(' AND ', $condiciones);

$tickets = [];
$totalesEstado = [];
$totalesProblema = [];
$totalesArea = [];
$totalesUsuario = [];
$totalesDia = [];
$resumen = [
    'total' => 0,
    'abierto' => 0,
    'en_progreso' => 0,
    'resuelto' => 0,
    'cerrado' => 0,
    'con_nino' => 0,
    'con_adjuntos' => 0,
];

$sqlResumen = "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN t.estado = 'abierto' THEN 1 ELSE 0 END) AS abierto,
        SUM(CASE WHEN t.estado = 'en_progreso' THEN 1 ELSE 0 END) AS en_progreso,
        SUM(CASE WHEN t.estado = 'resuelto' THEN 1 ELSE 0 END) AS resuelto,
        SUM(CASE WHEN t.estado = 'cerrado' THEN 1 ELSE 0 END) AS cerrado,
        SUM(CASE WHEN t.nino_id IS NOT NULL THEN 1 ELSE 0 END) AS con_nino,
        SUM(CASE WHEN adj.total_adjuntos > 0 THEN 1 ELSE 0 END) AS con_adjuntos
    FROM soporte_tickets t
    LEFT JOIN (
        SELECT ticket_id, COUNT(*) AS total_adjuntos
        FROM soporte_ticket_adjuntos
        GROUP BY ticket_id
    ) adj ON adj.ticket_id = t.id
    WHERE $where";

$stmtResumen = $conn->prepare($sqlResumen);
if ($stmtResumen instanceof mysqli_stmt) {
    $stmtResumen->bind_param($tipos, ...$parametros);
    $stmtResumen->execute();
    $rowResumen = $stmtResumen->get_result()->fetch_assoc();
    if ($rowResumen) {
        foreach ($resumen as $key => $value) {
            $resumen[$key] = isset($rowResumen[$key]) ? (int) $rowResumen[$key] : 0;
        }
    }
    $stmtResumen->close();
}

$consultasAgrupadas = [
    'estado' => "SELECT COALESCE(NULLIF(t.estado, ''), 'N/D') AS etiqueta, COUNT(*) AS total FROM soporte_tickets t WHERE $where GROUP BY etiqueta ORDER BY total DESC",
    'problema' => "SELECT COALESCE(NULLIF(t.problema_general, ''), 'Sin problema') AS etiqueta, COUNT(*) AS total FROM soporte_tickets t WHERE $where GROUP BY etiqueta ORDER BY total DESC",
    'area' => "SELECT COALESCE(NULLIF(t.area_problema, ''), 'Sin area') AS etiqueta, COUNT(*) AS total FROM soporte_tickets t WHERE $where GROUP BY etiqueta ORDER BY total DESC",
    'dia' => "SELECT DATE(t.created_at) AS etiqueta, COUNT(*) AS total FROM soporte_tickets t WHERE $where GROUP BY etiqueta ORDER BY etiqueta ASC",
];

foreach ($consultasAgrupadas as $tipoGrupo => $sqlGrupo) {
    $stmtGrupo = $conn->prepare($sqlGrupo);
    if (!($stmtGrupo instanceof mysqli_stmt)) {
        continue;
    }
    $stmtGrupo->bind_param($tipos, ...$parametros);
    $stmtGrupo->execute();
    $resultadoGrupo = $stmtGrupo->get_result();
    while ($row = $resultadoGrupo->fetch_assoc()) {
        $item = [
            'etiqueta' => (string) ($row['etiqueta'] ?? 'N/D'),
            'total' => (int) ($row['total'] ?? 0),
        ];
        if ($tipoGrupo === 'estado') {
            $totalesEstado[] = $item;
        } elseif ($tipoGrupo === 'problema') {
            $totalesProblema[] = $item;
        } elseif ($tipoGrupo === 'area') {
            $totalesArea[] = $item;
        } elseif ($tipoGrupo === 'dia') {
            $totalesDia[] = $item;
        }
    }
    $stmtGrupo->close();
}

if ($esAdminTickets) {
    $sqlUsuarios = "SELECT COALESCE(NULLIF(u.name, ''), u.user, 'Sin usuario') AS etiqueta, COUNT(*) AS total
        FROM soporte_tickets t
        LEFT JOIN Usuarios u ON u.id = t.creado_por
        WHERE $where
        GROUP BY etiqueta
        ORDER BY total DESC";
    $stmtUsuarios = $conn->prepare($sqlUsuarios);
    if ($stmtUsuarios instanceof mysqli_stmt) {
        $stmtUsuarios->bind_param($tipos, ...$parametros);
        $stmtUsuarios->execute();
        $resultadoUsuarios = $stmtUsuarios->get_result();
        while ($row = $resultadoUsuarios->fetch_assoc()) {
            $totalesUsuario[] = [
                'etiqueta' => (string) ($row['etiqueta'] ?? 'Sin usuario'),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }
        $stmtUsuarios->close();
    }
}

$sqlTickets = "SELECT t.id,
        t.estado,
        t.problema_general,
        t.area_problema,
        t.created_at,
        u.user AS creador_usuario,
        u.name AS creador_nombre,
        n.name AS nino_nombre,
        COALESCE(adj.total_adjuntos, 0) AS total_adjuntos,
        COALESCE(msg.total_mensajes, 0) AS total_mensajes
    FROM soporte_tickets t
    LEFT JOIN Usuarios u ON u.id = t.creado_por
    LEFT JOIN nino n ON n.id = t.nino_id
    LEFT JOIN (
        SELECT ticket_id, COUNT(*) AS total_adjuntos
        FROM soporte_ticket_adjuntos
        GROUP BY ticket_id
    ) adj ON adj.ticket_id = t.id
    LEFT JOIN (
        SELECT ticket_id, COUNT(*) AS total_mensajes
        FROM soporte_ticket_mensajes
        GROUP BY ticket_id
    ) msg ON msg.ticket_id = t.id
    WHERE $where
    ORDER BY t.created_at DESC, t.id DESC";

$stmtTickets = $conn->prepare($sqlTickets);
if ($stmtTickets instanceof mysqli_stmt) {
    $stmtTickets->bind_param($tipos, ...$parametros);
    $stmtTickets->execute();
    $resultadoTickets = $stmtTickets->get_result();
    while ($row = $resultadoTickets->fetch_assoc()) {
        $tickets[] = $row;
    }
    $stmtTickets->close();
}
?>

<div class="container mt-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-0 py-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                <div>
                    <h2 class="h4 mb-1 d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-circle p-2">
                            <i class="fas fa-chart-bar"></i>
                        </span>
                        Reporte mensual de tickets
                    </h2>
                    <p class="text-muted mb-0 small">
                        <?php echo $esAdminTickets ? 'Resumen mensual general de soporte.' : 'Resumen mensual de tus tickets de soporte.'; ?>
                    </p>
                </div>
                <div class="d-flex flex-column flex-md-row gap-2">
                    <a class="btn btn-outline-secondary" href="/Tickets/reporte_mensual.php?mes=<?php echo htmlspecialchars($mesAnterior, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-chevron-left me-1"></i>Mes anterior
                    </a>
                    <form method="get" class="d-flex gap-2">
                        <input type="month" class="form-control" name="mes" value="<?php echo htmlspecialchars($mesParam, ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="btn btn-primary" type="submit">Ver</button>
                    </form>
                    <a class="btn btn-outline-secondary" href="/Tickets/reporte_mensual.php?mes=<?php echo htmlspecialchars($mesSiguiente, ENT_QUOTES, 'UTF-8'); ?>">
                        Mes siguiente<i class="fas fa-chevron-right ms-1"></i>
                    </a>
                    <a class="btn btn-danger" target="_blank" href="/Tickets/reporte_mensual_pdf.php?mes=<?php echo htmlspecialchars($mesParam, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-file-pdf me-1"></i>PDF
                    </a>
                    <a class="btn btn-outline-primary" href="/Tickets/index.php">Tickets</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Total del mes</div><div class="h3 mb-0"><?php echo (int) $resumen['total']; ?></div><div class="small text-muted">Mes <?php echo htmlspecialchars($tituloMes, ENT_QUOTES, 'UTF-8'); ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Abiertos</div><div class="h3 mb-0 text-danger"><?php echo (int) $resumen['abierto']; ?></div><div class="small text-muted"><?php echo ticketReportePorcentaje((int) $resumen['abierto'], (int) $resumen['total']); ?> del total</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">En progreso</div><div class="h3 mb-0 text-warning"><?php echo (int) $resumen['en_progreso']; ?></div><div class="small text-muted"><?php echo ticketReportePorcentaje((int) $resumen['en_progreso'], (int) $resumen['total']); ?> del total</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Cerrados/Resueltos</div><div class="h3 mb-0 text-success"><?php echo (int) $resumen['cerrado'] + (int) $resumen['resuelto']; ?></div><div class="small text-muted"><?php echo ticketReportePorcentaje((int) $resumen['cerrado'] + (int) $resumen['resuelto'], (int) $resumen['total']); ?> del total</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Con niño</div><div class="h3 mb-0"><?php echo (int) $resumen['con_nino']; ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Con adjuntos</div><div class="h3 mb-0"><?php echo (int) $resumen['con_adjuntos']; ?></div></div></div></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0"><h3 class="h5 mb-0">Por estado</h3></div>
                <div class="card-body">
                    <?php if (empty($totalesEstado)): ?>
                        <p class="text-muted mb-0">Sin tickets en el mes.</p>
                    <?php endif; ?>
                    <?php foreach ($totalesEstado as $item): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo htmlspecialchars(ticketReporteEstadoTexto($item['etiqueta']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <strong><?php echo (int) $item['total']; ?></strong>
                            </div>
                            <div class="progress" style="height: 8px;"><div class="progress-bar" style="width: <?php echo htmlspecialchars(ticketReportePorcentaje((int) $item['total'], (int) $resumen['total']), ENT_QUOTES, 'UTF-8'); ?>"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0"><h3 class="h5 mb-0">Por problema</h3></div>
                <div class="card-body">
                    <?php if (empty($totalesProblema)): ?>
                        <p class="text-muted mb-0">Sin datos.</p>
                    <?php endif; ?>
                    <?php foreach ($totalesProblema as $item): ?>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span><?php echo htmlspecialchars($item['etiqueta'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <strong><?php echo (int) $item['total']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0"><h3 class="h5 mb-0">Por área</h3></div>
                <div class="card-body">
                    <?php if (empty($totalesArea)): ?>
                        <p class="text-muted mb-0">Sin datos.</p>
                    <?php endif; ?>
                    <?php foreach ($totalesArea as $item): ?>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span><?php echo htmlspecialchars($item['etiqueta'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <strong><?php echo (int) $item['total']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0"><h3 class="h5 mb-0"><?php echo $esAdminTickets ? 'Por usuario' : 'Por día'; ?></h3></div>
                <div class="card-body">
                    <?php $grupoFinal = $esAdminTickets ? $totalesUsuario : $totalesDia; ?>
                    <?php if (empty($grupoFinal)): ?>
                        <p class="text-muted mb-0">Sin datos.</p>
                    <?php endif; ?>
                    <?php foreach ($grupoFinal as $item): ?>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span><?php echo htmlspecialchars($item['etiqueta'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <strong><?php echo (int) $item['total']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center gap-2">
            <h3 class="h5 mb-0">Tickets del mes</h3>
            <span class="badge bg-primary-subtle text-primary-emphasis"><?php echo count($tickets); ?> registros</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="ticketsReporteTable">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Estado</th>
                        <th>Problema</th>
                        <th>Área</th>
                        <th>Niño</th>
                        <?php if ($esAdminTickets): ?><th>Creado por</th><?php endif; ?>
                        <th>Adjuntos</th>
                        <th>Mensajes</th>
                        <th>Fecha</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <?php
                            $creador = trim((string) ($ticket['creador_nombre'] ?? ''));
                            if ($creador === '') {
                                $creador = (string) ($ticket['creador_usuario'] ?? '');
                            }
                            $estado = (string) ($ticket['estado'] ?? '');
                        ?>
                        <tr>
                            <td>#<?php echo (int) ($ticket['id'] ?? 0); ?></td>
                            <td><span class="badge rounded-pill <?php echo htmlspecialchars(ticketReporteEstadoClase($estado), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ticketReporteEstadoTexto($estado), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo htmlspecialchars((string) ($ticket['problema_general'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($ticket['area_problema'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($ticket['nino_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php if ($esAdminTickets): ?><td><?php echo htmlspecialchars($creador, ENT_QUOTES, 'UTF-8'); ?></td><?php endif; ?>
                            <td><?php echo (int) ($ticket['total_adjuntos'] ?? 0); ?></td>
                            <td><?php echo (int) ($ticket['total_mensajes'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars(ticketReporteFecha($ticket['created_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><a class="btn btn-outline-primary btn-sm" href="/Tickets/ver.php?id=<?php echo (int) ($ticket['id'] ?? 0); ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../Modulos/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn && typeof jQuery.fn.DataTable === 'function') {
        jQuery('#ticketsReporteTable').DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            }
        });
    }
});
</script>
