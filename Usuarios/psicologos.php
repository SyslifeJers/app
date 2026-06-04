<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
include '../Modulos/head.php';

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
$rolesPermitidos = [1, 3, 5];
$puedeVerMontos = in_array($rolUsuario, [3, 5], true);

if (!in_array($rolUsuario, $rolesPermitidos, true)) {
    http_response_code(403);
    ?>
    <div class="container mt-5">
        <div class="alert alert-warning" role="alert">No tienes permiso para consultar esta sección.</div>
    </div>
    <?php
    include '../Modulos/footer.php';
    exit;
}

date_default_timezone_set('America/Mexico_City');

function tablaExiste(mysqli $conn, string $tabla): bool
{
    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $tabla);
    $stmt->execute();
    $stmt->bind_result($total);
    $existe = $stmt->fetch() && (int) $total > 0;
    $stmt->close();
    return $existe;
}

function formatoFechaCorta(string $fecha): string
{
    $obj = DateTime::createFromFormat('Y-m-d', $fecha);
    return $obj ? $obj->format('d/m/Y') : $fecha;
}

function dinero(float $monto): string
{
    return '$' . number_format($monto, 2);
}

function claseBadgeEstatus(string $estatus): string
{
    $estatusNormalizado = strtolower(trim($estatus));
    if ($estatusNormalizado === 'cancelada') {
        return 'bg-danger-subtle text-danger-emphasis border border-danger-subtle';
    }
    if ($estatusNormalizado === 'finalizada') {
        return 'bg-success-subtle text-success-emphasis border border-success-subtle';
    }
    if ($estatusNormalizado === 'reprogramado') {
        return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
    }
    if ($estatusNormalizado === 'creada') {
        return 'bg-primary-subtle text-primary-emphasis border border-primary-subtle';
    }
    return 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle';
}

function claseBadgeClasificacion(string $clasificacion): string
{
    $clasificacionNormalizada = strtolower(trim($clasificacion));
    if ($clasificacionNormalizada === 'paquete' || $clasificacionNormalizada === 'paquete/saldo') {
        return 'bg-info-subtle text-info-emphasis border border-info-subtle';
    }
    if ($clasificacionNormalizada === 'pago normal') {
        return 'bg-success-subtle text-success-emphasis border border-success-subtle';
    }
    return 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle';
}

$hoy = new DateTime('now', new DateTimeZone('America/Mexico_City'));
$rango = isset($_GET['rango']) ? trim((string) $_GET['rango']) : 'dia';
$rangosValidos = ['dia', 'semana', 'mes', 'personalizado'];
if (!in_array($rango, $rangosValidos, true)) {
    $rango = 'dia';
}

$fechaFinObj = clone $hoy;
$fechaInicioObj = clone $hoy;

if ($rango === 'semana') {
    $fechaInicioObj->modify('-6 days');
} elseif ($rango === 'mes') {
    $fechaInicioObj->modify('-29 days');
} elseif ($rango === 'personalizado') {
    $inicioParam = isset($_GET['fecha_inicio']) ? trim((string) $_GET['fecha_inicio']) : '';
    $finParam = isset($_GET['fecha_fin']) ? trim((string) $_GET['fecha_fin']) : '';
    $inicioCustom = DateTime::createFromFormat('Y-m-d', $inicioParam) ?: null;
    $finCustom = DateTime::createFromFormat('Y-m-d', $finParam) ?: null;
    if ($inicioCustom && $finCustom) {
        $fechaInicioObj = $inicioCustom;
        $fechaFinObj = $finCustom;
    }
}

if ($fechaInicioObj > $fechaFinObj) {
    $tmp = $fechaInicioObj;
    $fechaInicioObj = $fechaFinObj;
    $fechaFinObj = $tmp;
}

$fechaInicio = $fechaInicioObj->format('Y-m-d');
$fechaFin = $fechaFinObj->format('Y-m-d');
$psicologoSeleccionado = isset($_GET['psicologo_id']) && ctype_digit((string) $_GET['psicologo_id']) ? (int) $_GET['psicologo_id'] : 0;

$tieneCitaPagos = tablaExiste($conn, 'CitaPagos');
$tieneSaldoMovimientos = tablaExiste($conn, 'SaldoMovimientos');

$psicologos = [];
$stmtPsicologos = $conn->prepare("SELECT usu.id, usu.name, usu.telefono, usu.correo, Rol.name AS rol FROM Usuarios usu INNER JOIN Rol ON Rol.id = usu.IdRol WHERE usu.activo = 1 AND LOWER(Rol.name) LIKE '%psicolog%' ORDER BY usu.name ASC");
if ($stmtPsicologos) {
    $stmtPsicologos->execute();
    $resultPsicologos = $stmtPsicologos->get_result();
    while ($row = $resultPsicologos->fetch_assoc()) {
        $psicologos[] = $row;
    }
    $stmtPsicologos->close();
}

$psicologoActual = null;
foreach ($psicologos as $psicologo) {
    if ((int) $psicologo['id'] === $psicologoSeleccionado) {
        $psicologoActual = $psicologo;
        break;
    }
}

$resumen = [
    'total_citas' => 0,
    'pacientes_unicos' => 0,
    'citas_paquete' => 0,
    'citas_normales' => 0,
    'citas_canceladas' => 0,
    'citas_sin_pago' => 0,
    'total_costo' => 0.0,
    'total_pagado' => 0.0,
];
$citas = [];
$metodosPago = [];

if ($psicologoActual !== null) {
    $joinPagos = $tieneCitaPagos
        ? "LEFT JOIN (SELECT cita_id, SUM(monto) AS total_pagado, SUM(CASE WHEN LOWER(TRIM(metodo)) = 'saldo' THEN monto ELSE 0 END) AS saldo_pagado, GROUP_CONCAT(CONCAT(metodo, ': ', FORMAT(monto, 2)) SEPARATOR ', ') AS detalle_pago FROM CitaPagos GROUP BY cita_id) cp ON cp.cita_id = ci.id"
        : '';
    $joinSaldo = $tieneSaldoMovimientos
        ? "LEFT JOIN (SELECT cita_id, SUM(ABS(monto)) AS saldo_consumido FROM SaldoMovimientos WHERE tipo = 'consumo_cita' GROUP BY cita_id) sm ON sm.cita_id = ci.id"
        : '';
    $saldoExpr = $tieneSaldoMovimientos ? 'COALESCE(sm.saldo_consumido, 0)' : '0';
    $pagoExpr = $tieneCitaPagos ? 'COALESCE(cp.total_pagado, 0)' : '0';
    $saldoPagoExpr = $tieneCitaPagos ? 'COALESCE(cp.saldo_pagado, 0)' : '0';
    $detallePagoExpr = $tieneCitaPagos ? 'COALESCE(cp.detalle_pago, \'\')' : "''";
    $paqueteCond = "($saldoExpr > 0 OR $saldoPagoExpr > 0 OR ci.FormaPago LIKE 'Paquete%' OR LOWER(COALESCE(ci.FormaPago, '')) LIKE '%saldo%' OR ci.paquete_id IS NOT NULL)";
    $pagadaCond = "($pagoExpr > 0 OR (ci.FormaPago IS NOT NULL AND ci.FormaPago <> ''))";
    $canceladaCond = "LOWER(COALESCE(es.name, '')) = 'cancelada'";

    $sqlResumen = "SELECT
            COUNT(*) AS total_citas,
            COUNT(DISTINCT ci.IdNino) AS pacientes_unicos,
            SUM(CASE WHEN $canceladaCond THEN 1 ELSE 0 END) AS citas_canceladas,
            SUM(CASE WHEN NOT ($canceladaCond) AND $paqueteCond THEN 1 ELSE 0 END) AS citas_paquete,
            SUM(CASE WHEN NOT ($canceladaCond) AND NOT ($paqueteCond) AND $pagadaCond THEN 1 ELSE 0 END) AS citas_normales,
            SUM(CASE WHEN NOT ($canceladaCond) AND NOT ($paqueteCond) AND NOT ($pagadaCond) THEN 1 ELSE 0 END) AS citas_sin_pago,
            SUM(CASE WHEN NOT ($canceladaCond) THEN ci.costo ELSE 0 END) AS total_costo,
            SUM(CASE WHEN NOT ($canceladaCond) THEN $pagoExpr ELSE 0 END) AS total_pagado
        FROM Cita ci
        INNER JOIN nino n ON n.id = ci.IdNino
        LEFT JOIN Estatus es ON es.id = ci.Estatus
        $joinPagos
        $joinSaldo
        WHERE ci.IdUsuario = ? AND DATE(ci.Programado) BETWEEN ? AND ?";

    $stmtResumen = $conn->prepare($sqlResumen);
    if ($stmtResumen) {
        $stmtResumen->bind_param('iss', $psicologoSeleccionado, $fechaInicio, $fechaFin);
        $stmtResumen->execute();
        $rowResumen = $stmtResumen->get_result()->fetch_assoc();
        if ($rowResumen) {
            foreach ($resumen as $key => $value) {
                $resumen[$key] = isset($rowResumen[$key]) ? (is_float($value) ? (float) $rowResumen[$key] : (int) $rowResumen[$key]) : $value;
            }
        }
        $stmtResumen->close();
    }

    $sqlCitas = "SELECT
            ci.id,
            ci.Programado,
            ci.Tipo,
            ci.costo,
            ci.FormaPago,
            n.name AS paciente,
            COALESCE(es.name, 'Sin estatus') AS estatus,
            $pagoExpr AS total_pagado,
            $saldoExpr AS saldo_consumido,
            $detallePagoExpr AS detalle_pago,
            CASE WHEN $paqueteCond THEN 'Paquete/saldo' WHEN $pagadaCond THEN 'Pago normal' ELSE 'Sin pago' END AS clasificacion
        FROM Cita ci
        INNER JOIN nino n ON n.id = ci.IdNino
        LEFT JOIN Estatus es ON es.id = ci.Estatus
        $joinPagos
        $joinSaldo
        WHERE ci.IdUsuario = ? AND DATE(ci.Programado) BETWEEN ? AND ?
        ORDER BY ci.Programado DESC
        LIMIT 500";

    $stmtCitas = $conn->prepare($sqlCitas);
    if ($stmtCitas) {
        $stmtCitas->bind_param('iss', $psicologoSeleccionado, $fechaInicio, $fechaFin);
        $stmtCitas->execute();
        $resultCitas = $stmtCitas->get_result();
        while ($row = $resultCitas->fetch_assoc()) {
            $citas[] = $row;
        }
        $stmtCitas->close();
    }

    if ($tieneCitaPagos) {
        $sqlMetodos = "SELECT cp.metodo, SUM(cp.monto) AS total, COUNT(*) AS movimientos
            FROM CitaPagos cp
            INNER JOIN Cita ci ON ci.id = cp.cita_id
            LEFT JOIN Estatus es ON es.id = ci.Estatus
            WHERE ci.IdUsuario = ? AND DATE(ci.Programado) BETWEEN ? AND ? AND NOT ($canceladaCond)
            GROUP BY cp.metodo
            ORDER BY total DESC";
        $stmtMetodos = $conn->prepare($sqlMetodos);
        if ($stmtMetodos) {
            $stmtMetodos->bind_param('iss', $psicologoSeleccionado, $fechaInicio, $fechaFin);
            $stmtMetodos->execute();
            $resultMetodos = $stmtMetodos->get_result();
            while ($row = $resultMetodos->fetch_assoc()) {
                $metodosPago[] = $row;
            }
            $stmtMetodos->close();
        }
    }
}
?>

<div class="container mt-4">
    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-lg-between gap-3">
                        <div>
                            <h2 class="h4 mb-1"><i class="fas fa-brain text-primary me-2"></i>Psicólogos</h2>
                            <p class="text-muted mb-0 small">Consulta actividad por psicólogo. Ventas ve datos operativos; administración ve montos.</p>
                        </div>
                        <form method="get" class="row row-cols-1 row-cols-md-auto g-2 align-items-end">
                            <input type="hidden" name="psicologo_id" value="<?php echo (int) $psicologoSeleccionado; ?>">
                            <div class="col">
                                <label class="form-label" for="rango">Rango</label>
                                <select class="form-select" id="rango" name="rango" onchange="toggleFechasPersonalizadas()">
                                    <option value="dia" <?php echo $rango === 'dia' ? 'selected' : ''; ?>>1 día</option>
                                    <option value="semana" <?php echo $rango === 'semana' ? 'selected' : ''; ?>>1 semana</option>
                                    <option value="mes" <?php echo $rango === 'mes' ? 'selected' : ''; ?>>1 mes</option>
                                    <option value="personalizado" <?php echo $rango === 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                                </select>
                            </div>
                            <div class="col fechas-personalizadas">
                                <label class="form-label" for="fecha_inicio">Inicio</label>
                                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fechaInicio, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col fechas-personalizadas">
                                <label class="form-label" for="fecha_fin">Fin</label>
                                <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fechaFin, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col">
                                <button class="btn btn-primary" type="submit">Aplicar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h3 class="h5 mb-0">Listado</h3>
                </div>
                <div class="list-group list-group-flush p-3 gap-2">
                    <?php if (empty($psicologos)) { ?>
                        <div class="list-group-item text-muted">No se encontraron psicólogos activos.</div>
                    <?php } ?>
                    <?php foreach ($psicologos as $psicologo) { ?>
                        <?php
                        $activo = (int) $psicologo['id'] === $psicologoSeleccionado;
                        $url = '?psicologo_id=' . (int) $psicologo['id'] . '&rango=' . urlencode($rango) . '&fecha_inicio=' . urlencode($fechaInicio) . '&fecha_fin=' . urlencode($fechaFin);
                        ?>
                        <?php $inicial = strtoupper(substr(trim((string) $psicologo['name']), 0, 1)); ?>
                        <a class="list-group-item list-group-item-action rounded-3 border <?php echo $activo ? 'active shadow-sm' : 'bg-white'; ?>" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0 <?php echo $activo ? 'bg-white text-primary' : 'bg-primary-subtle text-primary-emphasis'; ?>" style="width: 42px; height: 42px; font-weight: 700;">
                                    <?php echo htmlspecialchars($inicial !== '' ? $inicial : 'P', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="min-width-0 flex-grow-1">
                                    <div class="fw-semibold lh-sm mb-2"><?php echo htmlspecialchars((string) $psicologo['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="small <?php echo $activo ? 'text-white-50' : 'text-muted'; ?> d-flex flex-column gap-1">
                                        <span><i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars((string) ($psicologo['telefono'] ?: 'Sin teléfono'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="text-truncate"><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars((string) ($psicologo['correo'] ?: 'Sin correo'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <?php if ($psicologoActual === null) { ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                        <h3 class="h5">Selecciona un psicólogo</h3>
                        <p class="text-muted mb-0">El resumen aparecerá al elegir un psicólogo del listado.</p>
                    </div>
                </div>
            <?php } else { ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-md-between gap-2 mb-3">
                            <div>
                                <h3 class="h5 mb-1"><?php echo htmlspecialchars((string) $psicologoActual['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="text-muted mb-0 small">Resumen del <?php echo formatoFechaCorta($fechaInicio); ?> al <?php echo formatoFechaCorta($fechaFin); ?></p>
                            </div>
                            <?php if (!$puedeVerMontos) { ?>
                                <span class="badge bg-info-subtle text-info-emphasis align-self-start">Vista sin montos</span>
                            <?php } ?>
                        </div>
                        <div class="row g-3">
                            <div class="col-6 col-xl-4"><div class="p-3 bg-light rounded"><div class="text-muted small">Total citas</div><div class="h4 mb-0"><?php echo (int) $resumen['total_citas']; ?></div></div></div>
                            <div class="col-6 col-xl-4"><div class="p-3 bg-light rounded"><div class="text-muted small">Pacientes</div><div class="h4 mb-0"><?php echo (int) $resumen['pacientes_unicos']; ?></div></div></div>
                            <div class="col-6 col-xl-4"><div class="p-3 bg-light rounded"><div class="text-muted small">Pago normal</div><div class="h4 mb-0"><?php echo (int) $resumen['citas_normales']; ?></div></div></div>
                            <div class="col-6 col-xl-4"><div class="p-3 bg-light rounded"><div class="text-muted small">Paquete/saldo</div><div class="h4 mb-0"><?php echo (int) $resumen['citas_paquete']; ?></div></div></div>
                            <div class="col-6 col-xl-4"><div class="p-3 bg-light rounded"><div class="text-muted small">Canceladas</div><div class="h4 mb-0"><?php echo (int) $resumen['citas_canceladas']; ?></div></div></div>
                            <div class="col-6 col-xl-4"><div class="p-3 bg-light rounded"><div class="text-muted small">Sin pago</div><div class="h4 mb-0"><?php echo (int) $resumen['citas_sin_pago']; ?></div></div></div>
                            <?php if ($puedeVerMontos) { ?>
                                <div class="col-6 col-xl-4"><div class="p-3 bg-success-subtle rounded"><div class="text-muted small">Total cobrado</div><div class="h4 mb-0"><?php echo dinero((float) $resumen['total_pagado']); ?></div></div></div>
                                <div class="col-6 col-xl-4"><div class="p-3 bg-primary-subtle rounded"><div class="text-muted small">Costo citas</div><div class="h4 mb-0"><?php echo dinero((float) $resumen['total_costo']); ?></div></div></div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <?php if ($puedeVerMontos) { ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0"><h3 class="h5 mb-0">Totales por método</h3></div>
                        <div class="card-body">
                            <?php if (empty($metodosPago)) { ?>
                                <p class="text-muted mb-0">No hay pagos registrados en el rango.</p>
                            <?php } else { ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead><tr><th>Método</th><th>Movimientos</th><th class="text-end">Total</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($metodosPago as $metodo) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars((string) $metodo['metodo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo (int) $metodo['movimientos']; ?></td>
                                                <td class="text-end"><?php echo dinero((float) $metodo['total']); ?></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0"><h3 class="h5 mb-0">Citas del rango</h3></div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tablaCitasPsicologo">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Paciente</th>
                                    <th>Tipo</th>
                                    <th>Estatus</th>
                                    <th>Clasificación</th>
                                    <th>Forma de pago</th>
                                    <?php if ($puedeVerMontos) { ?><th class="text-end">Costo</th><th class="text-end">Pagado</th><?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($citas as $cita) { ?>
                                <?php
                                $estatusTexto = (string) $cita['estatus'];
                                $clasificacionTexto = (string) $cita['clasificacion'];
                                $formaPagoTexto = (string) ($cita['FormaPago'] ?: $cita['detalle_pago'] ?: '-');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $cita['Programado'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $cita['paciente'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $cita['Tipo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="badge rounded-pill <?php echo claseBadgeEstatus($estatusTexto); ?>"><?php echo htmlspecialchars($estatusTexto, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><span class="badge rounded-pill <?php echo claseBadgeClasificacion($clasificacionTexto); ?>"><?php echo htmlspecialchars($clasificacionTexto, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><span class="small text-muted"><?php echo htmlspecialchars($formaPagoTexto, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <?php if ($puedeVerMontos) { ?>
                                        <td class="text-end"><?php echo dinero((float) $cita['costo']); ?></td>
                                        <td class="text-end"><?php echo dinero((float) $cita['total_pagado']); ?></td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php include '../Modulos/footer.php'; ?>

<script>
function toggleFechasPersonalizadas() {
    var mostrar = document.getElementById('rango').value === 'personalizado';
    document.querySelectorAll('.fechas-personalizadas').forEach(function (item) {
        item.style.display = mostrar ? '' : 'none';
    });
}

$(document).ready(function () {
    toggleFechasPersonalizadas();
    if ($('#tablaCitasPsicologo tbody tr').length > 0) {
        $('#tablaCitasPsicologo').DataTable({
            language: {
                lengthMenu: 'Número de filas _MENU_',
                zeroRecords: 'No se encontraron citas',
                info: 'Página _PAGE_ de _PAGES_',
                search: 'Buscar:',
                paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Previo' },
                infoEmpty: 'No hay registros disponibles',
                infoFiltered: '(filtrado de _MAX_ registros)'
            },
            order: [[0, 'desc']]
        });
    }
});
</script>
