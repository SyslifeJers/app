<?php
include '../Modulos/head.php';

function normalizarFecha($fecha)
{
    if (empty($fecha)) {
        return '';
    }

    $fecha = trim($fecha);
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);

    return ($dt && $dt->format('Y-m-d') === $fecha) ? $fecha : '';
}

if (!isset($_SESSION)) {
    session_start();
}

$mensajeCorteCaja = null;
if (isset($_SESSION['corte_caja_mensaje']) && is_array($_SESSION['corte_caja_mensaje'])) {
    $mensajeCorteCaja = $_SESSION['corte_caja_mensaje'];
    unset($_SESSION['corte_caja_mensaje']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_efectivo') {
    $fechaEfectivo = normalizarFecha($_POST['fecha_efectivo'] ?? '');
    $montoInicialRaw = str_replace(',', '', (string) ($_POST['efectivo_inicial'] ?? ''));
    $montoInicialRaw = trim($montoInicialRaw);

    $filtroFechaInicioPost = normalizarFecha($_POST['filtro_fecha_inicio'] ?? '');
    $filtroFechaFinPost = normalizarFecha($_POST['filtro_fecha_fin'] ?? '');
    $filtroStatusPost = isset($_POST['filtro_status']) ? trim((string) $_POST['filtro_status']) : '';
    $filtroPsicologoPost = isset($_POST['filtro_psicologo']) ? (int) $_POST['filtro_psicologo'] : 0;
    $filtroNinoPost = isset($_POST['filtro_nino']) ? (int) $_POST['filtro_nino'] : 0;

    $redirectParams = [];
    if ($filtroFechaInicioPost !== '') {
        $redirectParams['fecha_inicio'] = $filtroFechaInicioPost;
    }
    if ($filtroFechaFinPost !== '') {
        $redirectParams['fecha_fin'] = $filtroFechaFinPost;
    }
    if ($filtroStatusPost !== '') {
        $redirectParams['status'] = $filtroStatusPost;
    }
    if ($filtroPsicologoPost > 0) {
        $redirectParams['psicologo'] = $filtroPsicologoPost;
    }
    if ($filtroNinoPost > 0) {
        $redirectParams['nino'] = $filtroNinoPost;
    }
    if (!empty($redirectParams)) {
        $redirectParams['aplicar_filtros'] = '1';
    }

    $mensaje = ['tipo' => 'danger', 'texto' => 'Ocurrió un error desconocido al registrar el efectivo inicial.'];

    if ($fechaEfectivo === '') {
        $mensaje['texto'] = 'Selecciona una fecha válida para registrar el efectivo inicial.';
    } elseif ($montoInicialRaw === '' || !is_numeric($montoInicialRaw)) {
        $mensaje['texto'] = 'Ingresa un monto válido para el efectivo inicial.';
    } else {
        $montoInicial = (float) $montoInicialRaw;
        if ($montoInicial < 0) {
            $mensaje['texto'] = 'El efectivo inicial no puede ser negativo.';
        } else {
            $stmtExiste = $conn->prepare('SELECT efectivo_inicial FROM CorteCaja WHERE fecha = ? LIMIT 1');
            if ($stmtExiste instanceof mysqli_stmt) {
                $stmtExiste->bind_param('s', $fechaEfectivo);
                $stmtExiste->execute();
                $stmtExiste->store_result();

                if ($stmtExiste->num_rows > 0) {
                    $stmtExiste->bind_result($efectivoRegistrado);
                    $stmtExiste->fetch();
                    $mensaje['texto'] = sprintf(
                        'El efectivo inicial para %s ya fue registrado por $%s.',
                        DateTime::createFromFormat('Y-m-d', $fechaEfectivo)->format('d/m/Y'),
                        number_format((float) $efectivoRegistrado, 2)
                    );
                } else {
                    $stmtExiste->close();
                    $idUsuario = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;

                    if ($idUsuario > 0) {
                        $stmtInsert = $conn->prepare('INSERT INTO CorteCaja (fecha, efectivo_inicial, registrado_por) VALUES (?, ?, ?)');
                        if ($stmtInsert instanceof mysqli_stmt) {
                            $stmtInsert->bind_param('sdi', $fechaEfectivo, $montoInicial, $idUsuario);
                        }
                    } else {
                        $stmtInsert = $conn->prepare('INSERT INTO CorteCaja (fecha, efectivo_inicial, registrado_por) VALUES (?, ?, NULL)');
                        if ($stmtInsert instanceof mysqli_stmt) {
                            $stmtInsert->bind_param('sd', $fechaEfectivo, $montoInicial);
                        }
                    }

                    if (isset($stmtInsert) && $stmtInsert instanceof mysqli_stmt) {
                        if ($stmtInsert->execute()) {
                            $mensaje = [
                                'tipo' => 'success',
                                'texto' => sprintf(
                                    'Se registró $%s como efectivo inicial para %s.',
                                    number_format($montoInicial, 2),
                                    DateTime::createFromFormat('Y-m-d', $fechaEfectivo)->format('d/m/Y')
                                ),
                            ];
                        } else {
                            $mensaje['texto'] = 'No se pudo guardar el efectivo inicial. Inténtalo nuevamente.';
                        }
                        $stmtInsert->close();
                    } else {
                        $mensaje['texto'] = 'No se pudo preparar el registro del efectivo inicial.';
                    }
                }

                $stmtExiste->close();
            } else {
                $mensaje['texto'] = 'No se pudo verificar si ya existe un registro de efectivo inicial.';
            }
        }
    }

    $_SESSION['corte_caja_mensaje'] = $mensaje;

    $redirectUrl = 'index.php';
    if (!empty($redirectParams)) {
        $redirectUrl .= '?' . http_build_query($redirectParams);
    }

    header('Location: ' . $redirectUrl);
    exit;
}

date_default_timezone_set('America/Mexico_City');
$hoy = date('Y-m-d');
$aplicarFiltros = isset($_GET['aplicar_filtros']);

$fechaInicio = $aplicarFiltros ? ($_GET['fecha_inicio'] ?? '') : $hoy;
$fechaFin = $aplicarFiltros ? ($_GET['fecha_fin'] ?? '') : $hoy;
$statusSeleccionado = $aplicarFiltros ? ($_GET['status'] ?? '') : '';
$psicologoSeleccionado = $aplicarFiltros ? (int) ($_GET['psicologo'] ?? 0) : 0;
$ninoSeleccionado = $aplicarFiltros ? (int) ($_GET['nino'] ?? 0) : 0;

$fechaInicio = normalizarFecha($fechaInicio);
$fechaFin = normalizarFecha($fechaFin);
$statusSeleccionado = trim($statusSeleccionado);
$estatusDisponibles = ['Cancelada', 'Creada', 'Reprogramado', 'Finalizada'];

if ($statusSeleccionado !== '' && !in_array($statusSeleccionado, $estatusDisponibles, true)) {
    $statusSeleccionado = '';
}

if ($fechaInicio && $fechaFin && $fechaInicio > $fechaFin) {
    [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
}
$fechaEfectivoActual = $fechaInicio !== '' ? $fechaInicio : $hoy;
$efectivoInicialRegistrado = null;
$efectivoInicialBloqueado = false;

$stmtEfectivoActual = $conn->prepare('SELECT efectivo_inicial FROM CorteCaja WHERE fecha = ? LIMIT 1');
if ($stmtEfectivoActual instanceof mysqli_stmt) {
    $stmtEfectivoActual->bind_param('s', $fechaEfectivoActual);
    $stmtEfectivoActual->execute();
    $stmtEfectivoActual->bind_result($efectivoInicialConsulta);
    if ($stmtEfectivoActual->fetch()) {
        $efectivoInicialRegistrado = (float) $efectivoInicialConsulta;
        $efectivoInicialBloqueado = true;
    }
    $stmtEfectivoActual->close();
}
$efectivoInicialRegistradoTexto = $efectivoInicialRegistrado !== null
    ? '$' . number_format($efectivoInicialRegistrado, 2)
    : null;
$fechaEfectivoActualTexto = DateTime::createFromFormat('Y-m-d', $fechaEfectivoActual);
$fechaEfectivoActualTexto = $fechaEfectivoActualTexto
    ? $fechaEfectivoActualTexto->format('d/m/Y')
    : $fechaEfectivoActual;

$psicologosDisponibles = [];
$stmtPsicologos = $conn->prepare("SELECT usu.id, usu.name FROM Usuarios usu INNER JOIN Rol r ON r.id = usu.IdRol WHERE usu.activo = 1 AND LOWER(r.name) LIKE '%psicolog%' ORDER BY usu.name ASC");
if ($stmtPsicologos instanceof mysqli_stmt) {
    $stmtPsicologos->execute();
    $resultadoPsicologos = $stmtPsicologos->get_result();
    while ($filaPsicologo = $resultadoPsicologos->fetch_assoc()) {
        $psicologosDisponibles[] = $filaPsicologo;
    }
    $stmtPsicologos->close();
}

$ninosDisponibles = [];
$stmtNinos = $conn->prepare('SELECT id, name FROM nino WHERE activo = 1 ORDER BY name ASC');
if ($stmtNinos instanceof mysqli_stmt) {
    $stmtNinos->execute();
    $resultadoNinos = $stmtNinos->get_result();
    while ($filaNino = $resultadoNinos->fetch_assoc()) {
        $ninosDisponibles[] = $filaNino;
    }
    $stmtNinos->close();
}

$resumenPagos = [];
$resumenPagosError = '';
$resumenTotalesMetodo = [];
$resumenTotalesPsicologo = [];
$resumenTotalesOrigen = [];
$resumenTotalMonto = 0.0;
$resumenTotalMovimientos = 0;

$condicionesResumen = [];
$tiposResumen = '';
$parametrosResumen = [];

if ($fechaInicio !== '') {
    $condicionesResumen[] = 'fecha_corte >= ?';
    $tiposResumen .= 's';
    $parametrosResumen[] = $fechaInicio;
}

if ($fechaFin !== '') {
    $condicionesResumen[] = 'fecha_corte <= ?';
    $tiposResumen .= 's';
    $parametrosResumen[] = $fechaFin;
}

if ($psicologoSeleccionado > 0) {
    $condicionesResumen[] = 'psicologo_id = ?';
    $tiposResumen .= 'i';
    $parametrosResumen[] = $psicologoSeleccionado;
}

if ($ninoSeleccionado > 0) {
    $condicionesResumen[] = 'paciente_id = ?';
    $tiposResumen .= 'i';
    $parametrosResumen[] = $ninoSeleccionado;
}

if ($condicionesResumen === []) {
    $condicionesResumen[] = 'fecha_corte = ?';
    $tiposResumen .= 's';
    $parametrosResumen[] = $hoy;
}

$sqlResumenPagos = 'SELECT id, origen, referencia_id, paciente_nombre, psicologo_nombre, monto, metodo_pago, fecha_pago, fecha_corte, observaciones FROM PagoResumenDiario WHERE ' . implode(' AND ', $condicionesResumen) . ' ORDER BY fecha_pago DESC, id DESC LIMIT 1000';
$stmtResumenPagos = $conn->prepare($sqlResumenPagos);

if ($stmtResumenPagos instanceof mysqli_stmt) {
    if ($tiposResumen !== '') {
        $stmtResumenPagos->bind_param($tiposResumen, ...$parametrosResumen);
    }
    if ($stmtResumenPagos->execute()) {
        $resultadoResumenPagos = $stmtResumenPagos->get_result();
        while ($filaResumenPago = $resultadoResumenPagos->fetch_assoc()) {
            $resumenPagos[] = $filaResumenPago;
            $montoResumen = isset($filaResumenPago['monto']) ? (float) $filaResumenPago['monto'] : 0.0;
            $metodoResumen = trim((string) ($filaResumenPago['metodo_pago'] ?? 'Sin metodo'));
            $psicologoResumen = trim((string) ($filaResumenPago['psicologo_nombre'] ?? 'Sin asignar'));
            $origenResumen = trim((string) ($filaResumenPago['origen'] ?? 'sin_origen'));

            if ($metodoResumen === '') {
                $metodoResumen = 'Sin metodo';
            }
            if ($psicologoResumen === '') {
                $psicologoResumen = 'Sin asignar';
            }
            if ($origenResumen === '') {
                $origenResumen = 'sin_origen';
            }

            if (!isset($resumenTotalesMetodo[$metodoResumen])) {
                $resumenTotalesMetodo[$metodoResumen] = 0.0;
            }
            if (!isset($resumenTotalesPsicologo[$psicologoResumen])) {
                $resumenTotalesPsicologo[$psicologoResumen] = 0.0;
            }
            if (!isset($resumenTotalesOrigen[$origenResumen])) {
                $resumenTotalesOrigen[$origenResumen] = 0.0;
            }

            $resumenTotalesMetodo[$metodoResumen] += $montoResumen;
            $resumenTotalesPsicologo[$psicologoResumen] += $montoResumen;
            $resumenTotalesOrigen[$origenResumen] += $montoResumen;
            $resumenTotalMonto += $montoResumen;
            $resumenTotalMovimientos++;
        }
        $resultadoResumenPagos->free();
        arsort($resumenTotalesMetodo);
        arsort($resumenTotalesPsicologo);
        arsort($resumenTotalesOrigen);
    } else {
        $resumenPagosError = 'No fue posible consultar el resumen de pagos del corte.';
    }
    $stmtResumenPagos->close();
} else {
    $resumenPagosError = 'No fue posible preparar la consulta del resumen de pagos del corte.';
}

$condiciones = [];
$tipos = '';
$parametros = [];

// Filtro de estatus
if ($statusSeleccionado === '' || $statusSeleccionado === null) {
    $condiciones[] = 'ci.Estatus IN (1, 4)';
}

// Fechas (usa límites para mantener el índice)
if (!empty($fechaInicio)) {
    $condiciones[] = 'ci.Programado >= ?';
    $tipos .= 's';
    $parametros[] = $fechaInicio . ' 00:00:00';
}

if (!empty($fechaFin)) {
    $condiciones[] = 'ci.Programado <= ?';
    $tipos .= 's';
    $parametros[] = $fechaFin . ' 23:59:59';
}

if ($psicologoSeleccionado > 0) {
    $condiciones[] = 'ci.IdUsuario = ?';
    $tipos .= 'i';
    $parametros[] = $psicologoSeleccionado;
}

if ($ninoSeleccionado > 0) {
    $condiciones[] = 'ci.IdNino = ?';
    $tipos .= 'i';
    $parametros[] = $ninoSeleccionado;
}

// Estatus específico
if (!empty($statusSeleccionado)) {
    $condiciones[] = 'es.name = ?';
    $tipos .= 's';
    $parametros[] = $statusSeleccionado;
}

// Fallback
if (empty($condiciones)) {
    $condiciones[] = '1=1';
}

// Subconsulta de pagos segura y compatible
$joinPagos = "LEFT JOIN (
                 SELECT 
                     cp.cita_id,
                     GROUP_CONCAT(CONCAT(cp.metodo, ':', cp.monto) SEPARATOR '|') AS detalle
                 FROM CitaPagos cp
                 GROUP BY cp.cita_id
              ) AS pagos ON pagos.cita_id = ci.id";

$sql = "SELECT
            ci.id,
            n.name,
            us.name AS Psicologo,
            ci.costo,
            ci.Programado,
            DATE(ci.Programado) AS Fecha,
            TIME(ci.Programado) AS Hora,
            ci.Tipo,
            es.name AS Estatus,
            ci.FormaPago,
            COALESCE(pagos.detalle, '') AS pagos_detalle
        FROM Cita ci
        INNER JOIN nino n      ON n.id = ci.IdNino
        INNER JOIN Usuarios us ON us.id = ci.IdUsuario
        INNER JOIN Estatus es  ON es.id = ci.Estatus
        $joinPagos
        WHERE " . implode(' AND ', $condiciones) . "
        GROUP BY
            ci.id, n.name, us.name, ci.costo, ci.Programado, ci.Tipo, es.name, ci.FormaPago, pagos.detalle
        ORDER BY ci.Programado ASC";

$stmt = $conn->prepare($sql);
$errorConsulta = '';

if ($stmt === false) {
    $errorConsulta = $conn->error;
    $result = false;
} else {
    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$parametros);
    }

    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">Citas</h4>
                <a class="btn btn-outline-primary btn-sm" href="calendario.php">
                    <i class="far fa-calendar-alt me-1"></i>Ver calendario
                </a>
            </div>
            <div class="card-body">
                <?php if ($mensajeCorteCaja !== null): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($mensajeCorteCaja['tipo'], ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensajeCorteCaja['texto'], ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>

                <div class="border rounded-3 p-3 mb-4 bg-light">
                    <h5 class="mb-3">Efectivo inicial del corte</h5>
                    <form method="post" class="row g-3 align-items-end">
                        <input type="hidden" name="accion" value="registrar_efectivo">
                        <input type="hidden" name="fecha_efectivo" value="<?php echo htmlspecialchars($fechaEfectivoActual, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="filtro_fecha_inicio" value="<?php echo htmlspecialchars($fechaInicio, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="filtro_fecha_fin" value="<?php echo htmlspecialchars($fechaFin, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="filtro_status" value="<?php echo htmlspecialchars($statusSeleccionado, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="filtro_psicologo" value="<?php echo (int) $psicologoSeleccionado; ?>">
                        <input type="hidden" name="filtro_nino" value="<?php echo (int) $ninoSeleccionado; ?>">

                        <div class="col-md-4 col-lg-3">
                            <label class="form-label" for="fecha_efectivo_input">Fecha del corte</label>
                            <input type="date" id="fecha_efectivo_input" class="form-control" value="<?php echo htmlspecialchars($fechaEfectivoActual, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                            <div class="form-text">Basado en el filtro de fecha seleccionado.</div>
                        </div>

                        <div class="col-md-4 col-lg-3">
                            <label class="form-label" for="efectivo_inicial">Efectivo inicial</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" min="0" step="0.01" class="form-control" id="efectivo_inicial" name="efectivo_inicial" value="<?php echo $efectivoInicialRegistrado !== null ? htmlspecialchars(number_format($efectivoInicialRegistrado, 2, '.', ''), ENT_QUOTES, 'UTF-8') : ''; ?>" <?php echo $efectivoInicialBloqueado ? 'readonly' : ''; ?> required>
                            </div>
                            <?php if ($efectivoInicialBloqueado): ?>
                                <div class="form-text text-success">Efectivo registrado: <?php echo htmlspecialchars($efectivoInicialRegistradoTexto, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php else: ?>
                                <div class="form-text">Ingresa con cuánto efectivo inició la jornada.</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4 col-lg-3">
                            <button type="submit" class="btn btn-primary w-100" <?php echo $efectivoInicialBloqueado ? 'disabled' : ''; ?>>Guardar efectivo inicial</button>
                        </div>

                        <div class="col-12 col-lg-3">
                            <div class="alert alert-secondary mb-0" role="alert">
                                <i class="fas fa-lock me-2"></i>
                                <?php if ($efectivoInicialBloqueado): ?>
                                    El efectivo inicial para <?php echo htmlspecialchars($fechaEfectivoActualTexto, ENT_QUOTES, 'UTF-8'); ?> está bloqueado.
                                <?php else: ?>
                                    Una vez guardado, el monto quedará bloqueado para evitar cambios.
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <form id="filtersForm" class="row mb-3 g-3" method="get">
                    <input type="hidden" name="aplicar_filtros" value="1">
                    <div class="col-md-3">
                        <label for="min-date" class="form-label">Fecha Inicio:</label>
                        <input type="date" class="form-control" id="min-date" name="fecha_inicio" value="<?php echo htmlspecialchars($fechaInicio, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="max-date" class="form-label">Fecha Fin:</label>
                        <input type="date" class="form-control" id="max-date" name="fecha_fin" value="<?php echo htmlspecialchars($fechaFin, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="status-filter" class="form-label">Estatus:</label>
                        <select class="form-select" id="status-filter" name="status">
                            <option value="" <?php echo $statusSeleccionado === '' ? 'selected' : ''; ?>>Todos</option>
                            <option value="Cancelada" <?php echo $statusSeleccionado === 'Cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                            <option value="Creada" <?php echo $statusSeleccionado === 'Creada' ? 'selected' : ''; ?>>Creada</option>
                            <option value="Reprogramado" <?php echo $statusSeleccionado === 'Reprogramado' ? 'selected' : ''; ?>>Reprogramado</option>
                            <option value="Finalizada" <?php echo $statusSeleccionado === 'Finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="psicologo-filter" class="form-label">Psicologa</label>
                        <select class="form-select" id="psicologo-filter" name="psicologo">
                            <option value="0">Todas</option>
                            <?php foreach ($psicologosDisponibles as $psicologoOption): ?>
                                <option value="<?php echo (int) $psicologoOption['id']; ?>" <?php echo $psicologoSeleccionado === (int) $psicologoOption['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $psicologoOption['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="nino-filter" class="form-label">Nino</label>
                        <select class="form-select" id="nino-filter" name="nino">
                            <option value="0">Todos</option>
                            <?php foreach ($ninosDisponibles as $ninoOption): ?>
                                <option value="<?php echo (int) $ninoOption['id']; ?>" <?php echo $ninoSeleccionado === (int) $ninoOption['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $ninoOption['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-black w-100" id="filter-button">Filtrar</button>
                    </div>
                </form>

                <div class="border rounded-3 p-3 mb-4 bg-white">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Resumen de pagos del corte</h5>
                            <div class="text-muted small">Movimientos registrados por fecha de corte.</div>
                        </div>
                        <div class="text-md-end">
                            <div class="small text-muted">Periodo</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($fechaInicio !== '' ? $fechaInicio : $hoy, ENT_QUOTES, 'UTF-8'); ?> al <?php echo htmlspecialchars($fechaFin !== '' ? $fechaFin : $hoy, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>

                    <?php if ($resumenPagosError !== ''): ?>
                        <div class="alert alert-warning mb-0"><?php echo htmlspecialchars($resumenPagosError, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php else: ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <div class="text-muted small">Total cobrado</div>
                                    <div class="fs-4 fw-bold">$<?php echo number_format($resumenTotalMonto, 2); ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <div class="text-muted small">Movimientos</div>
                                    <div class="fs-4 fw-bold"><?php echo (int) $resumenTotalMovimientos; ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <div class="text-muted small">Efectivo inicial</div>
                                    <div class="fs-4 fw-bold"><?php echo htmlspecialchars($efectivoInicialRegistradoTexto ?? '$0.00', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-lg-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <h6 class="mb-3">Total por metodo</h6>
                                    <?php if ($resumenTotalesMetodo === []): ?>
                                        <div class="text-muted small">Sin pagos registrados.</div>
                                    <?php else: ?>
                                        <?php foreach ($resumenTotalesMetodo as $metodoResumen => $montoResumen): ?>
                                            <div class="d-flex justify-content-between gap-3 small mb-2">
                                                <span><?php echo htmlspecialchars($metodoResumen, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="fw-semibold">$<?php echo number_format($montoResumen, 2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <h6 class="mb-3">Total por psicologo</h6>
                                    <?php if ($resumenTotalesPsicologo === []): ?>
                                        <div class="text-muted small">Sin pagos registrados.</div>
                                    <?php else: ?>
                                        <?php foreach ($resumenTotalesPsicologo as $psicologoResumen => $montoResumen): ?>
                                            <div class="d-flex justify-content-between gap-3 small mb-2">
                                                <span><?php echo htmlspecialchars($psicologoResumen, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="fw-semibold">$<?php echo number_format($montoResumen, 2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <h6 class="mb-3">Total por origen</h6>
                                    <?php if ($resumenTotalesOrigen === []): ?>
                                        <div class="text-muted small">Sin pagos registrados.</div>
                                    <?php else: ?>
                                        <?php foreach ($resumenTotalesOrigen as $origenResumen => $montoResumen): ?>
                                            <div class="d-flex justify-content-between gap-3 small mb-2">
                                                <span><?php echo htmlspecialchars($origenResumen, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="fw-semibold">$<?php echo number_format($montoResumen, 2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Fecha pago</th>
                                        <th>Fecha corte</th>
                                        <th>Paciente</th>
                                        <th>Psicólogo</th>
                                        <th>Origen</th>
                                        <th>Metodo</th>
                                        <th>Monto</th>
                                        <th>Nota</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($resumenPagos === []): ?>
                                        <tr>
                                            <td colspan="8" class="text-muted">No hay movimientos de pago en el periodo seleccionado.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($resumenPagos as $filaResumenPago): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars((string) ($filaResumenPago['fecha_pago'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($filaResumenPago['fecha_corte'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($filaResumenPago['paciente_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($filaResumenPago['psicologo_nombre'] ?? 'Sin asignar'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($filaResumenPago['origen'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> #<?php echo (int) ($filaResumenPago['referencia_id'] ?? 0); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($filaResumenPago['metodo_pago'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="fw-semibold">$<?php echo number_format((float) ($filaResumenPago['monto'] ?? 0), 2); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($filaResumenPago['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">

                    <?php
                    if ($result && $result->num_rows > 0) {
                        echo "<table border='1' id='myTable'>
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Id</th>
                                        <th>Paciente</th>
                                        <th>Psicólogo</th>
                                        <th>Costo</th>
                                        <th>Hora</th>
                                        <th>Tipo</th>
                                        <th>Pagos registrados</th>
                                        <th>Estatus</th>
                                        <th>Forma de pago</th>

                                    </tr>
                                </thead>
                                <tbody>";
                        // Recorrer los resultados y mostrarlos en la tabla
                        while ($row = $result->fetch_assoc())  {
                            $pagosDetalleRaw = isset($row['pagos_detalle']) ? (string) $row['pagos_detalle'] : '';
                            $pagosRegistrados = [];

                            if ($pagosDetalleRaw !== '') {
                                $registros = explode(chr(30), $pagosDetalleRaw);
                                foreach ($registros as $registro) {
                                    if ($registro === '') {
                                        continue;
                                    }

                                    $partes = explode(chr(31), $registro);
                                    $metodo = isset($partes[0]) ? trim((string) $partes[0]) : '';
                                    if ($metodo === '') {
                                        continue;
                                    }

                                    $monto = isset($partes[1]) ? (float) $partes[1] : 0.0;
                                    $pagosRegistrados[] = htmlspecialchars($metodo, ENT_QUOTES, 'UTF-8') . ' $' . number_format($monto, 2, '.', ',');
                                }
                            }

                            $pagosDetalleHtml = $pagosRegistrados === []
                                ? htmlspecialchars('Sin registros', ENT_QUOTES, 'UTF-8')
                                : implode('<br>', $pagosRegistrados);

                            $fecha = htmlspecialchars($row['Fecha'], ENT_QUOTES, 'UTF-8');
                            $id = htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8');
                            $nombre = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                            $psicologo = htmlspecialchars($row['Psicologo'], ENT_QUOTES, 'UTF-8');
                            $costo = htmlspecialchars($row['costo'], ENT_QUOTES, 'UTF-8');
                            $hora = htmlspecialchars($row['Hora'], ENT_QUOTES, 'UTF-8');
                            $tipo = htmlspecialchars($row['Tipo'], ENT_QUOTES, 'UTF-8');
                            $estatus = htmlspecialchars($row['Estatus'], ENT_QUOTES, 'UTF-8');
                            $formaPago = isset($row['FormaPago']) && $row['FormaPago'] !== ''
                                ? htmlspecialchars($row['FormaPago'], ENT_QUOTES, 'UTF-8')
                                : htmlspecialchars('Sin registrar', ENT_QUOTES, 'UTF-8');

                            echo '<tr>';
                            echo '<td>' . $fecha . '</td>';
                            echo '<td>' . $id . '</td>';
                            echo '<td>' . $nombre . '</td>';
                            echo '<td>' . $psicologo . '</td>';
                            echo '<td>' . $costo . '</td>';
                            echo '<td>' . $hora . '</td>';
                            echo '<td>' . $tipo . '</td>';
                            echo '<td>' . $pagosDetalleHtml . '</td>';
                            echo '<td>' . $estatus . '</td>';
                            echo '<td>' . $formaPago . '</td>';
                            echo '</tr>';
                        }
                        echo "</tbody></table>";
                    } elseif ($errorConsulta !== '') {
                        echo "<div class='alert alert-danger'>Ocurrió un error al consultar las citas. Por favor intenta de nuevo.</div>";
                    } else {
                        echo "<div class='alert alert-info'>No se encontraron resultados con los filtros seleccionados.</div>";
                    }

                    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                        $stmt->close();
                    }

                    $conn->close();
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<hr>
<!-- Formulario para generar el reporte -->
<form id="reporteForm">
    <input type="hidden" id="fecha_inicio" name="fecha_inicio">
    <input type="hidden" id="fecha_fin" name="fecha_fin">
    <input type="hidden" id="reporte_psicologo" name="tipoPid">
    <input type="hidden" id="reporte_nino" name="idNino">
    <button type="submit" class="btn btn-black">Generar Reporte</button>
</form>

<!-- Iframe para mostrar el reporte -->
<iframe id="reporteFrame" style="width: 100%; height: 600px;"></iframe>
<?php include '../Modulos/footer.php'; ?>

<script>
    document.getElementById('reporteForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const fecha_inicio = document.getElementById('min-date').value;
    const fecha_fin = document.getElementById('max-date').value;
    const psicologo = document.getElementById('psicologo-filter').value || '0';
    const nino = document.getElementById('nino-filter').value || '0';
    document.getElementById('reporte_psicologo').value = psicologo;
    document.getElementById('reporte_nino').value = nino;
    const url = '../Reportes/imprimir.php?fecha_inicio=' + encodeURIComponent(fecha_inicio) + '&fecha_fin=' + encodeURIComponent(fecha_fin) + '&tipoPid=' + encodeURIComponent(psicologo) + '&idNino=' + encodeURIComponent(nino);
    document.getElementById('reporteFrame').src = url;
});
    $(document).ready(function () {
        var $table = $('#myTable');

        if ($table.length) {
            $table.DataTable({
                language: {
                    lengthMenu: 'Número de filas _MENU_',
                    zeroRecords: 'No encontró nada, usa los filtros para pulir la búsqueda',
                    info: 'Página _PAGE_ de _PAGES_',
                    search: 'Buscar:',
                    paginate: {
                        first: 'Primero',
                        last: 'Último',
                        next: 'Siguiente',
                        previous: 'Previo'
                    },
                    infoEmpty: 'No hay registros disponibles',
                    infoFiltered: '(Buscamos en _MAX_ resultados)',
                },
            });
        }
    });
</script>
