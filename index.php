<?php
ob_start();

include 'Modulos/head.php';
require_once __DIR__ . '/Modulos/resumen_pagos.php';
require_once __DIR__ . '/Modulos/saldo_pacientes.php';
require_once __DIR__ . '/Modulos/servicios_paciente.php';

$rolUsuario = $_SESSION['rol'] ?? 0;
$esAdministrador = (int) $rolUsuario === 3;
$puedeRegistrarPagoSinCita = in_array((int) $rolUsuario, [1, 3], true);
$tutoresPagoSinCita = [];
$serviciosCompraSeparada = [];
$historialPacientesInicio = [];
$agendaPsicologosInicio = [];
$zonaHorariaApp = new DateTimeZone('America/Mexico_City');
$fechaCorteHoy = (new DateTime('now', $zonaHorariaApp))->format('Y-m-d');
$fechaCorteHoyTexto = DateTime::createFromFormat('Y-m-d', $fechaCorteHoy, $zonaHorariaApp);
$fechaCorteHoyTexto = $fechaCorteHoyTexto ? $fechaCorteHoyTexto->format('d/m/Y') : $fechaCorteHoy;
$mensajeCorteCaja = $_SESSION['corte_caja_mensaje'] ?? null;
unset($_SESSION['corte_caja_mensaje']);

$requiereEfectivoInicial = false;
$efectivoInicialRegistrado = null;

function registrarPagoInicio(mysqli $conn, array $data): int
{
    $stmt = $conn->prepare('INSERT INTO Pagos (origen, referencia_id, cita_id, paquete_id, paciente_id, paciente_nombre, psicologo_id, psicologo_nombre, monto, metodo_pago, fecha_pago, fecha_corte, registrado_por, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('No fue posible preparar el pago.');
    }

    $stmt->bind_param(
        'siiiisisdsssis',
        $data['origen'],
        $data['referencia_id'],
        $data['cita_id'],
        $data['paquete_id'],
        $data['paciente_id'],
        $data['paciente_nombre'],
        $data['psicologo_id'],
        $data['psicologo_nombre'],
        $data['monto'],
        $data['metodo_pago'],
        $data['fecha_pago'],
        $data['fecha_corte'],
        $data['registrado_por'],
        $data['observaciones']
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('No fue posible guardar el pago.');
    }

    $pagoId = (int) $conn->insert_id;
    $stmt->close();

    return $pagoId;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_efectivo_inicial') {
    $fechaEfectivo = trim((string) ($_POST['fecha_efectivo'] ?? ''));
    $montoInicialRaw = str_replace(',', '', (string) ($_POST['efectivo_inicial'] ?? ''));
    $montoInicialRaw = trim($montoInicialRaw);
    $mensaje = ['tipo' => 'danger', 'texto' => 'Ocurrió un error al registrar el efectivo inicial.'];

    $fechaValidada = DateTime::createFromFormat('Y-m-d', $fechaEfectivo, $zonaHorariaApp);
    if (!$fechaValidada || $fechaValidada->format('Y-m-d') !== $fechaEfectivo) {
        $mensaje['texto'] = 'Selecciona una fecha válida para el efectivo inicial.';
    } elseif ($montoInicialRaw === '' || !is_numeric($montoInicialRaw)) {
        $mensaje['texto'] = 'Ingresa un monto válido para el efectivo inicial.';
    } else {
        $montoInicial = (float) $montoInicialRaw;
        if ($montoInicial < 0) {
            $mensaje['texto'] = 'El efectivo inicial no puede ser negativo.';
        } else {
            $stmtExiste = $conn->prepare('SELECT 1 FROM CorteCaja WHERE fecha = ? LIMIT 1');
            if ($stmtExiste instanceof mysqli_stmt) {
                $stmtExiste->bind_param('s', $fechaEfectivo);
                $stmtExiste->execute();
                $stmtExiste->store_result();

                if ($stmtExiste->num_rows > 0) {
                    $mensaje['texto'] = sprintf(
                        'El efectivo inicial para %s ya fue registrado.',
                        $fechaValidada->format('d/m/Y')
                    );
                } else {
                    $idUsuario = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
                    if ($idUsuario) {
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
                                    $fechaValidada->format('d/m/Y')
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
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_pago_sin_cita') {
    $mensaje = ['tipo' => 'danger', 'texto' => 'No fue posible registrar el pago sin cita.'];

    try {
        if (!$puedeRegistrarPagoSinCita) {
            throw new RuntimeException('Solo administración o ventas pueden registrar pagos sin cita.');
        }

        $tutorId = isset($_POST['tutor_id']) ? (int) $_POST['tutor_id'] : 0;
        $pacienteId = isset($_POST['paciente_id']) ? (int) $_POST['paciente_id'] : 0;
        $metodoPago = isset($_POST['metodo_pago']) ? substr(trim((string) $_POST['metodo_pago']), 0, 50) : '';
        $monto = isset($_POST['monto']) ? (float) $_POST['monto'] : 0.0;
        $observaciones = isset($_POST['observaciones']) ? substr(trim((string) $_POST['observaciones']), 0, 255) : '';

        if ($tutorId <= 0 || $pacienteId <= 0 || $metodoPago === '' || $monto <= 0) {
            throw new RuntimeException('Selecciona tutor, paciente, método de pago y monto mayor a cero.');
        }

        $stmtPacientePago = $conn->prepare('SELECT id, name FROM nino WHERE id = ? AND idtutor = ? AND activo = 1 LIMIT 1');
        if (!$stmtPacientePago) {
            throw new RuntimeException('No fue posible validar el paciente.');
        }
        $stmtPacientePago->bind_param('ii', $pacienteId, $tutorId);
        $stmtPacientePago->execute();
        $resultadoPacientePago = $stmtPacientePago->get_result();
        $pacientePago = $resultadoPacientePago ? $resultadoPacientePago->fetch_assoc() : null;
        $stmtPacientePago->close();

        if (!is_array($pacientePago)) {
            throw new RuntimeException('El paciente seleccionado no corresponde al tutor.');
        }

        $fechaPago = (new DateTime('now', $zonaHorariaApp))->format('Y-m-d H:i:s');
        $notaPago = $observaciones !== '' ? $observaciones : 'Pago sin cita';
        $saldoAnterior = obtenerSaldoPaciente($conn, $pacienteId);

        $conn->begin_transaction();

        $datosPagoSinCita = [
            'origen' => 'sin_cita',
            'referencia_id' => $tutorId,
            'cita_id' => null,
            'paquete_id' => null,
            'paciente_id' => $pacienteId,
            'paciente_nombre' => substr((string) $pacientePago['name'], 0, 150),
            'psicologo_id' => null,
            'psicologo_nombre' => null,
            'monto' => $monto,
            'metodo_pago' => $metodoPago,
            'fecha_pago' => $fechaPago,
            'fecha_corte' => $fechaCorteHoy,
            'registrado_por' => isset($_SESSION['id']) ? (int) $_SESSION['id'] : null,
            'observaciones' => $notaPago,
        ];

        $pagoId = registrarPagoInicio($conn, $datosPagoSinCita);

        registrarResumenPago($conn, $datosPagoSinCita);

        if (!ajustarSaldoPaciente($conn, $pacienteId, $monto)) {
            throw new RuntimeException('No fue posible actualizar el saldo del paciente.');
        }

        $saldoNuevo = $saldoAnterior + $monto;
        try {
            if ($stmtSaldo = $conn->prepare('INSERT INTO SaldoMovimientos (paciente_id, tipo, monto, saldo_anterior, saldo_nuevo, pago_id, cita_id, paquete_id, registrado_por, observaciones) VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?)')) {
                $tipoMovimiento = $saldoAnterior < -0.0001 ? 'abono_adeudo' : 'agregar_saldo';
                $usuarioMovimiento = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
                $stmtSaldo->bind_param('isdddiis', $pacienteId, $tipoMovimiento, $monto, $saldoAnterior, $saldoNuevo, $pagoId, $usuarioMovimiento, $notaPago);
                $stmtSaldo->execute();
                $stmtSaldo->close();
            }
        } catch (Throwable $saldoLogError) {
            error_log('index.php pago sin cita: no se pudo registrar SaldoMovimientos: ' . $saldoLogError->getMessage());
        }

        $conn->commit();
        $mensaje = [
            'tipo' => 'success',
            'texto' => $saldoAnterior < -0.0001
                ? 'Pago sin cita registrado. Se abonó al adeudo y el excedente quedó como saldo disponible.'
                : 'Pago sin cita registrado como saldo disponible.',
        ];
    } catch (Throwable $e) {
        if ($conn instanceof mysqli) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
            }
        }
        $mensaje['texto'] = $e->getMessage();
    }

    $_SESSION['corte_caja_mensaje'] = $mensaje;
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_servicio_sin_cita') {
    $mensaje = ['tipo' => 'danger', 'texto' => 'No fue posible registrar la compra de servicio.'];

    try {
        if (!$puedeRegistrarPagoSinCita) {
            throw new RuntimeException('Solo administración o ventas pueden registrar compras de servicios sin cita.');
        }

        $pacienteId = isset($_POST['paciente_id']) ? (int) $_POST['paciente_id'] : 0;
        $precioId = isset($_POST['precio_id']) ? (int) $_POST['precio_id'] : 0;
        $monto = isset($_POST['monto']) ? (float) $_POST['monto'] : 0.0;
        $metodoPago = isset($_POST['metodo_pago']) ? trim((string) $_POST['metodo_pago']) : '';

        if ($pacienteId <= 0 || $precioId <= 0) {
            throw new RuntimeException('Selecciona paciente y servicio.');
        }

        $precioServicio = obtenerPrecioServicioPorId($conn, $precioId);
        if (!$precioServicio || ((int) $precioServicio['sesiones_total'] === 1 && (int) $precioServicio['requiere_cita'] === 1 && (int) $precioServicio['permite_sin_fecha'] !== 1)) {
            throw new RuntimeException('El tipo seleccionado no está configurado como servicio sin cita.');
        }

        if ($monto > 0.009 && !in_array($metodoPago, ['Efectivo', 'Transferencia', 'Tarjeta'], true)) {
            throw new RuntimeException('Selecciona un método de pago válido.');
        }

        $pacienteNombre = '';
        if ($stmtPaciente = $conn->prepare('SELECT name FROM nino WHERE id = ? LIMIT 1')) {
            $stmtPaciente->bind_param('i', $pacienteId);
            $stmtPaciente->execute();
            $stmtPaciente->bind_result($nombrePacienteDb);
            if ($stmtPaciente->fetch()) {
                $pacienteNombre = (string) $nombrePacienteDb;
            }
            $stmtPaciente->close();
        }

        if ($pacienteNombre === '') {
            throw new RuntimeException('Paciente no encontrado.');
        }

        $conn->begin_transaction();
        $servicio = prepararServicioPaciente($conn, $pacienteId, null, $precioServicio, isset($_SESSION['id']) ? (int) $_SESSION['id'] : null);

        if ($monto > 0.009) {
            registrarPagoServicioPaciente($conn, [
                'id' => (int) $servicio['id'],
                'nino_id' => $pacienteId,
                'psicologo_id' => null,
                'saldo_restante' => (float) $servicio['saldo_restante'],
                'servicio_nombre' => (string) ($precioServicio['name'] ?? ''),
                'paciente_nombre' => $pacienteNombre,
                'psicologo_nombre' => '',
            ], null, $metodoPago, $monto, isset($_SESSION['id']) ? (int) $_SESSION['id'] : null, date('Y-m-d H:i:s'));
        }

        $conn->commit();
        $mensaje = ['tipo' => 'success', 'texto' => 'Compra de servicio registrada correctamente.'];
    } catch (Throwable $e) {
        if ($conn instanceof mysqli && $conn->errno === 0) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
            }
        }
        $mensaje['texto'] = $e->getMessage();
    }

    $_SESSION['corte_caja_mensaje'] = $mensaje;
    header('Location: index.php');
    exit;
}

$stmtEfectivoHoy = $conn->prepare('SELECT efectivo_inicial FROM CorteCaja WHERE fecha = ? LIMIT 1');
if ($stmtEfectivoHoy instanceof mysqli_stmt) {
    $stmtEfectivoHoy->bind_param('s', $fechaCorteHoy);
    $stmtEfectivoHoy->execute();
    $stmtEfectivoHoy->bind_result($efectivoHoy);
    if ($stmtEfectivoHoy->fetch()) {
        $efectivoInicialRegistrado = (float) $efectivoHoy;
    } else {
        $requiereEfectivoInicial = true;
    }
    $stmtEfectivoHoy->close();
} else {
    $requiereEfectivoInicial = true;
}

$mensajeReprogramacion = $_SESSION['reprogramacion_mensaje'] ?? null;
$tipoReprogramacion = $_SESSION['reprogramacion_tipo'] ?? 'success';
unset($_SESSION['reprogramacion_mensaje'], $_SESSION['reprogramacion_tipo']);

$mensajeCancelacion = $_SESSION['cancelacion_mensaje'] ?? null;
$tipoCancelacion = $_SESSION['cancelacion_tipo'] ?? 'success';
unset($_SESSION['cancelacion_mensaje'], $_SESSION['cancelacion_tipo']);

$pendientesReprogramacion = 0;
$pendientesCancelacion = 0;
$tablaSolicitudesExiste = false;
try {
    if ($stmtSolicitudExiste = $conn->prepare('SELECT 1 FROM SolicitudReprogramacion LIMIT 1')) {
        $stmtSolicitudExiste->execute();
        $stmtSolicitudExiste->close();
        $tablaSolicitudesExiste = true;
    }
} catch (Throwable $solicitudesError) {
    error_log('index.php: no se pudo consultar SolicitudReprogramacion: ' . $solicitudesError->getMessage());
}

if ($tablaSolicitudesExiste && in_array($rolUsuario, [3, 5])) {
    if ($stmtPendientes = $conn->prepare("SELECT tipo, COUNT(*) FROM SolicitudReprogramacion WHERE estatus = 'pendiente' GROUP BY tipo")) {
        $stmtPendientes->execute();
        $stmtPendientes->bind_result($tipoSolicitud, $totalPendiente);
        while ($stmtPendientes->fetch()) {
            if ($tipoSolicitud === 'cancelacion') {
                $pendientesCancelacion = (int) $totalPendiente;
            } elseif ($tipoSolicitud === 'reprogramacion') {
                $pendientesReprogramacion = (int) $totalPendiente;
            }
        }
        $stmtPendientes->close();
    }
}

$reunionesProgramadas = [];
$reunionesProgramadasTotal = 0;
$fechaHoraActual = (new DateTime('now', $zonaHorariaApp))->format('Y-m-d H:i:s');

$sqlReunionesProgramadas = "SELECT
    ri.id,
    ri.titulo,
    ri.inicio,
    ri.fin,
    GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ', ') AS psicologos
FROM ReunionInterna ri
INNER JOIN ReunionInternaPsicologo rip ON rip.reunion_id = ri.id
INNER JOIN Usuarios u ON u.id = rip.psicologo_id
WHERE ri.inicio >= ? AND ri.fin <= ?
GROUP BY ri.id, ri.titulo, ri.inicio, ri.fin
ORDER BY ri.inicio ASC
LIMIT 100";

if ($stmtReunionesProgramadas = $conn->prepare($sqlReunionesProgramadas)) {
    $stmtReunionesProgramadas->bind_param('ss', $fechaHoraActual,$fechaHoraActual);
    if ($stmtReunionesProgramadas->execute()) {
        $resultadoReunionesProgramadas = $stmtReunionesProgramadas->get_result();
        while ($filaReunion = $resultadoReunionesProgramadas->fetch_assoc()) {
            $reunionesProgramadas[] = [
                'id' => (int) $filaReunion['id'],
                'titulo' => (string) ($filaReunion['titulo'] ?? 'Reunión interna'),
                'inicio' => (string) ($filaReunion['inicio'] ?? ''),
                'fin' => (string) ($filaReunion['fin'] ?? ''),
                'psicologos' => (string) ($filaReunion['psicologos'] ?? ''),
            ];
        }
    }
    $stmtReunionesProgramadas->close();
}

$reunionesProgramadasTotal = count($reunionesProgramadas);

if ($puedeRegistrarPagoSinCita && $resultadoTutoresPagoSinCita = $conn->query("SELECT c.id AS tutor_id, c.name AS tutor_nombre, n.id AS paciente_id, n.name AS paciente_nombre, COALESCE(n.saldo_paquete, 0) AS saldo_demo FROM Clientes c INNER JOIN nino n ON n.idtutor = c.id WHERE c.activo = 1 AND n.activo = 1 ORDER BY n.name ASC, c.name ASC LIMIT 1000")) {
    while ($tutorPagoSinCita = $resultadoTutoresPagoSinCita->fetch_assoc()) {
        $tutoresPagoSinCita[] = $tutorPagoSinCita;
    }
    $resultadoTutoresPagoSinCita->free();
}

if ($puedeRegistrarPagoSinCita && $resultadoServiciosCompra = $conn->query("SELECT id, name, costo, COALESCE(sesiones_total, 1) AS sesiones_total, COALESCE(requiere_cita, 1) AS requiere_cita, COALESCE(permite_sin_fecha, 0) AS permite_sin_fecha FROM Precios WHERE activo = 1 AND (COALESCE(sesiones_total, 1) <> 1 OR COALESCE(requiere_cita, 1) = 0 OR COALESCE(permite_sin_fecha, 0) = 1) ORDER BY name ASC")) {
    while ($servicioCompra = $resultadoServiciosCompra->fetch_assoc()) {
        $serviciosCompraSeparada[] = $servicioCompra;
    }
    $resultadoServiciosCompra->free();
}

if ($resultadoHistorialInicio = $conn->query("SELECT ci.id, ci.IdNino, ci.costo, ci.Programado, ci.Tipo, es.name AS estatus_nombre, u.name AS psicologo_nombre FROM Cita ci INNER JOIN Estatus es ON es.id = ci.Estatus INNER JOIN Usuarios u ON u.id = ci.IdUsuario ORDER BY ci.IdNino ASC, ci.Programado DESC LIMIT 3000")) {
    while ($row = $resultadoHistorialInicio->fetch_assoc()) {
        $pacienteKey = (string) (int) $row['IdNino'];
        if (!isset($historialPacientesInicio[$pacienteKey])) {
            $historialPacientesInicio[$pacienteKey] = [];
        }
        if (count($historialPacientesInicio[$pacienteKey]) < 10) {
            $historialPacientesInicio[$pacienteKey][] = [
                'id' => (int) $row['id'],
                'programado' => (string) $row['Programado'],
                'tipo' => (string) $row['Tipo'],
                'estatus' => (string) $row['estatus_nombre'],
                'psicologo' => (string) $row['psicologo_nombre'],
                'costo' => (float) $row['costo'],
            ];
        }
    }
    $resultadoHistorialInicio->free();
}

if ($resultadoAgendaInicio = $conn->query("SELECT ci.id, ci.IdUsuario, ci.Programado, COALESCE(ci.Tiempo, 60) AS Tiempo, es.name AS estatus_nombre, n.name AS paciente_nombre FROM Cita ci INNER JOIN Estatus es ON es.id = ci.Estatus INNER JOIN nino n ON n.id = ci.IdNino WHERE ci.Programado >= NOW() AND ci.Programado < DATE_ADD(CURDATE(), INTERVAL 8 DAY) AND LOWER(es.name) <> 'cancelada' ORDER BY ci.Programado ASC LIMIT 3000")) {
    while ($row = $resultadoAgendaInicio->fetch_assoc()) {
        $psicologoKey = (string) (int) $row['IdUsuario'];
        if (!isset($agendaPsicologosInicio[$psicologoKey])) {
            $agendaPsicologosInicio[$psicologoKey] = [];
        }
        $agendaPsicologosInicio[$psicologoKey][] = [
            'id' => (int) $row['id'],
            'programado' => (string) $row['Programado'],
            'tiempo' => (int) $row['Tiempo'],
            'estatus' => (string) $row['estatus_nombre'],
            'paciente' => (string) $row['paciente_nombre'],
        ];
    }
    $resultadoAgendaInicio->free();
}
?>

<div class="row">
  <div class="col-md-12">
    <div class="card">
      <div class="card-header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2">
          <h4 class="card-title mb-0">Citas</h4>
          <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalReunionesProgramadas">
            <i class="far fa-calendar-alt me-1"></i>
            Reuniones programadas hoy
            <?php if ($reunionesProgramadasTotal > 0): ?>
              <span class="badge bg-primary ms-1"><?php echo (int) $reunionesProgramadasTotal; ?></span>
            <?php endif; ?>
          </button>
        </div>
      </div>
      <div class="card-body">
        <?php if ($mensajeCorteCaja !== null): ?>
          <div class="alert alert-<?php echo htmlspecialchars($mensajeCorteCaja['tipo'], ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($mensajeCorteCaja['texto'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        <?php if ($mensajeReprogramacion): ?>
          <div class="alert alert-<?php echo htmlspecialchars($tipoReprogramacion, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($mensajeReprogramacion, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if ($mensajeCancelacion): ?>
          <div class="alert alert-<?php echo htmlspecialchars($tipoCancelacion, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($mensajeCancelacion, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (in_array($rolUsuario, [3, 4]) && $pendientesReprogramacion > 0): ?>
          <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Hay <?php echo (int) $pendientesReprogramacion; ?> solicitud(es) de reprogramación pendientes.
            <a href="/Citas/solicitudes.php" class="alert-link">Revisar solicitudes</a>.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (in_array($rolUsuario, [3, 4]) && $pendientesCancelacion > 0): ?>
          <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Hay <?php echo (int) $pendientesCancelacion; ?> solicitud(es) de cancelación pendientes.
            <a href="/Citas/solicitudes.php?tipo=cancelacion" class="alert-link">Revisar solicitudes</a>.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        <div class="table-responsive">

          <?php
          // Consulta SQL
          $selectSolicitudesReprogramacion = $tablaSolicitudesExiste ? ",\n       COALESCE(sr_reprogramacion.solicitudesPendientes, 0) as solicitudesReprogramacionPendientes" : ",\n       0 as solicitudesReprogramacionPendientes";
          $joinSolicitudesReprogramacion = $tablaSolicitudesExiste ? "LEFT JOIN (\n    SELECT cita_id, COUNT(*) AS solicitudesPendientes\n    FROM SolicitudReprogramacion\n    WHERE estatus = 'pendiente' AND tipo = 'reprogramacion'\n    GROUP BY cita_id\n) sr_reprogramacion ON sr_reprogramacion.cita_id = ci.id\n" : '';

$selectSolicitudesCancelacion = $tablaSolicitudesExiste ? ",\n       COALESCE(sr_cancelacion.solicitudesPendientesCancelacion, 0) as solicitudesCancelacionPendientes" : ",\n       0 as solicitudesCancelacionPendientes";
$joinSolicitudesCancelacion = $tablaSolicitudesExiste ? "LEFT JOIN (\n    SELECT cita_id, COUNT(*) AS solicitudesPendientesCancelacion\n    FROM SolicitudReprogramacion\n    WHERE estatus = 'pendiente' AND tipo = 'cancelacion'\n    GROUP BY cita_id\n) sr_cancelacion ON sr_cancelacion.cita_id = ci.id\n" : '';


$zonaHoraria = new DateTimeZone('America/Mexico_City');
$fechaActual = new DateTime('now', $zonaHoraria);
$fechaInicioSeleccionada = $_GET['fecha_inicio'] ?? $fechaActual->format('Y-m-d');
$fechaFinSeleccionada = $_GET['fecha_fin'] ?? $fechaActual->format('Y-m-d');

$fechaInicioValidada = DateTime::createFromFormat('Y-m-d', (string) $fechaInicioSeleccionada, $zonaHoraria);
if ($fechaInicioValidada === false || $fechaInicioValidada->format('Y-m-d') !== (string) $fechaInicioSeleccionada) {
  $fechaInicioSeleccionada = $fechaActual->format('Y-m-d');
} else {
  $fechaInicioSeleccionada = $fechaInicioValidada->format('Y-m-d');
}

$fechaFinValidada = DateTime::createFromFormat('Y-m-d', (string) $fechaFinSeleccionada, $zonaHoraria);
if ($fechaFinValidada === false || $fechaFinValidada->format('Y-m-d') !== (string) $fechaFinSeleccionada) {
  $fechaFinSeleccionada = $fechaActual->format('Y-m-d');
} else {
  $fechaFinSeleccionada = $fechaFinValidada->format('Y-m-d');
}

if ($fechaInicioSeleccionada > $fechaFinSeleccionada) {
  $fechaTemporal = $fechaInicioSeleccionada;
  $fechaInicioSeleccionada = $fechaFinSeleccionada;
  $fechaFinSeleccionada = $fechaTemporal;
}

$estatusSeleccionado = null;
if (isset($_GET['estatus'])) {
  $estatusParam = trim((string) $_GET['estatus']);
  if ($estatusParam !== '' && ctype_digit($estatusParam)) {
    $estatusSeleccionado = (int) $estatusParam;
  }
}

$estatusDisponibles = [];
if ($resultadoEstatus = $conn->query("SELECT id, name FROM Estatus ORDER BY name")) {
  while ($estatus = $resultadoEstatus->fetch_assoc()) {
    $estatusDisponibles[] = [
      'id' => (int) $estatus['id'],
      'name' => (string) $estatus['name']
    ];
  }
  $resultadoEstatus->free();
}

if ($estatusSeleccionado === null) {
  $fechaInicioSeleccionada = $fechaActual->format('Y-m-d');
  $fechaFinSeleccionada = $fechaActual->format('Y-m-d');
}


            $fecha = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');

          $whereConditions = ["DATE(ci.Programado) BETWEEN ? AND ?"];
          if ($estatusSeleccionado === null) {
            $whereConditions[] = "(ci.FormaPago IS NULL OR ci.FormaPago = '' OR ci.FormaPago like 'Paquete%' OR ci.servicio_paciente_id IS NOT NULL)";
          } else {
            $whereConditions[] = "ci.Estatus = ?";
          }

          $sql = "SELECT ci.id,
       ci.IdNino AS paciente_id,
       n.name,
       us.name as Psicologo,
       ci.IdUsuario AS PsicologoId,
       ci.costo,
       ci.Programado,
       ci.Tiempo,
       DATE(ci.Programado) as Fecha,
       TIME(ci.Programado) as Hora,
        ci.Tipo,
        ci.FormaPago,
        ci.servicio_paciente_id,
        ci.servicio_sesion,
        sp.sesiones_total AS servicio_sesiones_total,
        sp.saldo_restante AS servicio_saldo_restante,
        es.name as Estatus,
       COALESCE(n.saldo_paquete, 0) AS saldo_paquete" . $selectSolicitudesReprogramacion . $selectSolicitudesCancelacion . "
FROM Cita ci
INNER JOIN nino n ON n.id = ci.IdNino
INNER JOIN Usuarios us ON us.id = ci.IdUsuario
INNER JOIN Estatus es ON es.id = ci.Estatus
LEFT JOIN ServiciosPaciente sp ON sp.id = ci.servicio_paciente_id
" . $joinSolicitudesReprogramacion . $joinSolicitudesCancelacion . "
WHERE " . implode("\n  AND ", $whereConditions) . "
ORDER BY ci.Programado ASC;";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  throw new RuntimeException('Error al preparar la consulta de citas: ' . $conn->error);
}
if ($estatusSeleccionado === null) {
  $stmt->bind_param('ss', $fechaInicioSeleccionada, $fechaFinSeleccionada);
} else {
  $stmt->bind_param('ssi', $fechaInicioSeleccionada, $fechaFinSeleccionada, $estatusSeleccionado);
}
$stmt->execute();
$result = $stmt->get_result();
          $hoy = $fechaActual->format('Y-m-d');
          // Verificar si hay resultados y generar la tabla HTML
          echo '<div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center mb-3">';
          if ($fechaInicioSeleccionada === $fechaFinSeleccionada) {
            $descripcionRango = 'Citas para ' . htmlspecialchars($fechaInicioSeleccionada, ENT_QUOTES, 'UTF-8');
          } else {
            $descripcionRango = 'Citas del ' . htmlspecialchars($fechaInicioSeleccionada, ENT_QUOTES, 'UTF-8') . ' al ' . htmlspecialchars($fechaFinSeleccionada, ENT_QUOTES, 'UTF-8');
          }
          echo '<h5 class="mb-2 mb-md-0">' . $descripcionRango . '</h5>';
          echo '<form method="get" class="row row-cols-1 row-cols-sm-auto g-2 align-items-end">';
          echo '<div class="col">';
          echo '<label class="form-label" for="fecha_inicio">Fecha inicio</label>';
          echo '<input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" value="' . htmlspecialchars($fechaInicioSeleccionada, ENT_QUOTES, 'UTF-8') . '">';
          echo '</div>';
          echo '<div class="col">';
          echo '<label class="form-label" for="fecha_fin">Fecha fin</label>';
          echo '<input type="date" id="fecha_fin" name="fecha_fin" class="form-control" value="' . htmlspecialchars($fechaFinSeleccionada, ENT_QUOTES, 'UTF-8') . '">';
          echo '</div>';
          echo '<div class="col">';
          echo '<label class="form-label" for="estatus">Estatus</label>';
          echo '<select id="estatus" name="estatus" class="form-select">';
          $selectedPendientes = $estatusSeleccionado === null ? ' selected' : '';
          echo '<option value=""' . $selectedPendientes . '>Pendientes de pago de hoy</option>';
          foreach ($estatusDisponibles as $estatus) {
            $selected = ($estatusSeleccionado !== null && $estatusSeleccionado === $estatus['id']) ? ' selected' : '';
            echo '<option value="' . (int) $estatus['id'] . '"' . $selected . '>' . htmlspecialchars($estatus['name'], ENT_QUOTES, 'UTF-8') . '</option>';
          }
          echo '</select>';
          echo '</div>';
          echo '<div class="col">';
          echo '<button type="submit" class="btn btn-primary">Filtrar</button>';
          echo '</div>';
          echo '</form>';
          echo '</div>';

          if ($result->num_rows > 0) {
            echo "<table border='1' id=\"myTable\" >
            <thead>
            <tr>
                <th>Fecha</th>
                <th>Id</th>
                <th>Paciente</th>
                <th>Psicólogo</th>
                <th>Costo</th>
                <th>Hora</th>
                <th>Tipo</th>
                <th>Forma de pago</th>
                <th>Estatus</th>
                <th>Solicitudes de reprogramación</th>
                <th>Solicitudes de cancelación</th>
                <th>Opciones</th>
            </tr>    </thead>
                        <tbody>";
            // Recorrer los resultados y mostrarlos en la tabla

            while ($row = $result->fetch_assoc()) {
              $pendientesReprogramacion = isset($row['solicitudesReprogramacionPendientes']) ? (int) $row['solicitudesReprogramacionPendientes'] : 0;
              $textoBadgeReprogramacion = $pendientesReprogramacion > 0 ? 'Pendiente (' . $pendientesReprogramacion . ')' : 'Sin solicitudes';
              $badgeClassReprogramacion = $pendientesReprogramacion > 0 ? 'badge bg-warning text-dark' : 'badge bg-secondary';

              $pendientesCancelacion = isset($row['solicitudesCancelacionPendientes']) ? (int) $row['solicitudesCancelacionPendientes'] : 0;
              $textoBadgeCancelacion = $pendientesCancelacion > 0 ? 'Pendiente (' . $pendientesCancelacion . ')' : 'Sin solicitudes';
              $badgeClassCancelacion = $pendientesCancelacion > 0 ? 'badge bg-warning text-dark' : 'badge bg-secondary';

              $reprogramarTexto = 'Reprogramar';
              $formaPagoRegistrada = isset($row['FormaPago']) ? trim((string) $row['FormaPago']) : '';
              $esCitaServicio = !empty($row['servicio_paciente_id']);
              $estatusActual = isset($row['Estatus']) ? strtolower((string) $row['Estatus']) : '';
              $botones = [];
              if ($row['Estatus'] == 'Creada' || $row['Estatus'] == 'Reprogramado') {
              $botones[] = '<button class="btn btn-primary btn-sm" onclick="Reprogramar(' . $row['id'] . ')">' . $reprogramarTexto . '</button>';

              $botones[] = '<button class="btn btn-danger btn-sm" onclick="actualizarCita(' . $row['id'] . ',1)">Cancelar</button>';

            }

              if ($formaPagoRegistrada !== '' && !$esCitaServicio) {
                $botones[] = '<span class="badge bg-success">Pago registrado</span>';
                if ($estatusActual !== 'finalizada' ) {
                  $botones[] = '<button class="btn btn-outline-success btn-sm" onclick="finalizarCita(' . $row['id'] . ')">Finalizar</button>';
                }
              } elseif ($row['Fecha'] === $hoy && ($row['Estatus'] == 'Creada' || $row['Estatus'] == 'Reprogramado')) {
                $pagoInfo = [
                  'idCita' => (int) $row['id'],
                  'estatus' => 4,
                  'costo' => (float) $row['costo'],
                  'pacienteId' => (int) $row['paciente_id'],
                  'saldo' => (float) $row['saldo_paquete'],
                  'programado' => $row['Programado'],
                  'tiempo' => isset($row['Tiempo']) ? (int) $row['Tiempo'] : 60,
                  'psicologoId' => (int) $row['PsicologoId'],
                  'tipo' => $row['Tipo'],
                  'pacienteNombre' => $row['name'],
                  'psicologoNombre' => $row['Psicologo'],
                  'servicioPacienteId' => $esCitaServicio ? (int) $row['servicio_paciente_id'] : null,
                  'servicioSesion' => $row['servicio_sesion'] !== null ? (int) $row['servicio_sesion'] : null,
                  'servicioSesionesTotal' => $row['servicio_sesiones_total'] !== null ? (int) $row['servicio_sesiones_total'] : null,
                  'servicioSaldoRestante' => $row['servicio_saldo_restante'] !== null ? (float) $row['servicio_saldo_restante'] : null
                ];
                $botones[] = '<button class="btn btn-success btn-sm pagar-cita-btn" data-pago="' . htmlspecialchars(json_encode($pagoInfo), ENT_QUOTES, 'UTF-8') . '">Pagar</button>';
              }

              echo '<tr>';
              echo '<td>' . $row['Fecha'] . '</td>';
              echo '<td>' . $row['id'] . '</td>';
              echo '<td><a href="#" class="inicio-open-patient-history" data-paciente-id="' . (int) $row['paciente_id'] . '" data-paciente-nombre="' . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '" title="Ver historial top 10">' . htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') . '</a></td>';
              echo '<td><a href="#" class="inicio-open-psych-availability" data-psicologo-id="' . (int) $row['PsicologoId'] . '" data-psicologo-nombre="' . htmlspecialchars((string) $row['Psicologo'], ENT_QUOTES, 'UTF-8') . '" title="Ver disponibilidad">' . htmlspecialchars((string) $row['Psicologo'], ENT_QUOTES, 'UTF-8') . '</a></td>';
              echo '<td>' . $row['costo'] . '</td>';
              echo '<td>' . $row['Hora'] . '</td>';
              echo '<td>' . $row['Tipo'] . '</td>';
              if ($esCitaServicio) {
                $formaPagoTexto = 'Servicio';
                if ($row['servicio_sesion'] !== null && $row['servicio_sesiones_total'] !== null) {
                  $formaPagoTexto .= ' ' . (int) $row['servicio_sesion'] . '/' . (int) $row['servicio_sesiones_total'];
                }
                if ($row['servicio_saldo_restante'] !== null) {
                  $formaPagoTexto .= ' - Restante $' . number_format((float) $row['servicio_saldo_restante'], 2);
                }
              } else {
                $formaPagoTexto = $formaPagoRegistrada !== '' ? $formaPagoRegistrada : 'Sin registrar';
              }
              echo '<td>' . htmlspecialchars($formaPagoTexto, ENT_QUOTES, 'UTF-8') . '</td>';
              echo '<td>' . $row['Estatus'] . '</td>';
              echo '<td><span class="' . $badgeClassReprogramacion . '">' . $textoBadgeReprogramacion . '</span></td>';
              echo '<td><span class="' . $badgeClassCancelacion . '">' . $textoBadgeCancelacion . '</span></td>';
              echo '<td>' . implode(' ', $botones) . '</td>';
              echo '</tr>';
            }
            echo "</tbody></table>";
          } else {
            echo "<p class='mb-0'>No se encontraron citas para la fecha seleccionada.</p>";
          }

          // Cerrar conexión
          $stmt->close();
          $conn->close();
          ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($puedeRegistrarPagoSinCita): ?>
<style>
  .floating-payment-no-appointment {
    position: fixed;
    right: 28px;
    bottom: 28px;
    z-index: 1050;
    width: 58px;
    height: 58px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
  }
  .floating-service-purchase {
    position: fixed;
    right: 28px;
    bottom: 96px;
    z-index: 1050;
    width: 58px;
    height: 58px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
  }
</style>
<button type="button" class="btn btn-info text-white rounded-circle shadow-lg floating-service-purchase" data-bs-toggle="modal" data-bs-target="#modalCompraServicioSinCita" aria-label="Compra de servicios sin cita" title="Compra de servicios sin cita">
  <i class="fas fa-notes-medical"></i>
</button>
<button type="button" class="btn btn-warning rounded-circle shadow-lg floating-payment-no-appointment" data-bs-toggle="modal" data-bs-target="#modalPagoSinCitaInicio" aria-label="Registrar pago sin cita" title="Registrar pago sin cita">
  <i class="fas fa-receipt"></i>
</button>
<div class="modal fade" id="modalCompraServicioSinCita" tabindex="-1" aria-labelledby="modalCompraServicioSinCitaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="accion" value="registrar_servicio_sin_cita">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCompraServicioSinCitaLabel"><i class="fas fa-notes-medical me-2"></i>Compra de servicios sin cita</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info py-2">
            Registra un servicio separado sin confirmar fechas. El adeudo queda en el servicio y después las citas se ligan automáticamente por tipo.
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="compraServicioBuscar">Buscar paciente</label>
              <input type="search" class="form-control" id="compraServicioBuscar" placeholder="Escribe el nombre del paciente">
            </div>
            <div class="col-12">
              <label class="form-label" for="compraServicioPaciente">Paciente</label>
              <select class="form-select" id="compraServicioPaciente" name="paciente_id" required>
                <option value="">Selecciona paciente</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="compraServicioPrecio">Servicio</label>
              <select class="form-select" id="compraServicioPrecio" name="precio_id" required>
                <option value="">Selecciona servicio</option>
                <?php foreach ($serviciosCompraSeparada as $servicioCompra): ?>
                  <?php
                    $sesionesCompra = (int) ($servicioCompra['sesiones_total'] ?? 1);
                    $etiquetaSesiones = $sesionesCompra === 0 ? 'sin cita' : ($sesionesCompra . ' cita' . ($sesionesCompra === 1 ? '' : 's'));
                  ?>
                  <option value="<?php echo (int) $servicioCompra['id']; ?>">
                    <?php echo htmlspecialchars((string) $servicioCompra['name'] . ' - $' . number_format((float) $servicioCompra['costo'], 2) . ' - ' . $etiquetaSesiones, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="compraServicioMetodo">Método</label>
              <select class="form-select" id="compraServicioMetodo" name="metodo_pago">
                <option value="">Sin pago inicial</option>
                <option value="Efectivo">Efectivo</option>
                <option value="Transferencia">Transferencia</option>
                <option value="Tarjeta">Tarjeta</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="compraServicioMonto">Pago inicial</label>
              <input type="number" min="0" step="0.01" class="form-control" id="compraServicioMonto" name="monto" value="0.00">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-info text-white">Registrar servicio</button>
        </div>
      </form>
    </div>
  </div>
</div>
<div class="modal fade" id="modalPagoSinCitaInicio" tabindex="-1" aria-labelledby="modalPagoSinCitaInicioLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="accion" value="registrar_pago_sin_cita">
        <div class="modal-header">
          <h5 class="modal-title" id="modalPagoSinCitaInicioLabel"><i class="fas fa-receipt me-2"></i>Pago sin cita</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning py-2">
            El pago se registra en el corte del dia sin ligarse a una cita. Abona adeudo y cualquier excedente queda como saldo disponible.
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="inicioPagoSinCitaBuscar">Buscar paciente</label>
              <input type="search" class="form-control" id="inicioPagoSinCitaBuscar" placeholder="Escribe el nombre del paciente">
            </div>
            <div class="col-12">
              <label class="form-label" for="inicioPagoSinCitaPaciente">Paciente a acreditar</label>
              <input type="hidden" id="inicioPagoSinCitaTutor" name="tutor_id" value="">
              <select class="form-select" id="inicioPagoSinCitaPaciente" name="paciente_id" required>
                <option value="">Selecciona paciente</option>
              </select>
              <div class="form-text" id="inicioPagoSinCitaSaldoTexto">El saldo estimado se mostrara al elegir paciente y monto.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="inicioPagoSinCitaMetodo">Metodo de pago</label>
              <select class="form-select" id="inicioPagoSinCitaMetodo" name="metodo_pago" required>
                <option value="">Selecciona metodo</option>
                <option value="Efectivo">Efectivo</option>
                <option value="Transferencia">Transferencia</option>
                <option value="Tarjeta">Tarjeta</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="inicioPagoSinCitaMonto">Monto pagado</label>
              <input type="number" min="0.01" step="0.01" class="form-control" id="inicioPagoSinCitaMonto" name="monto" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="inicioPagoSinCitaObservaciones">Observaciones</label>
              <input type="text" class="form-control" id="inicioPagoSinCitaObservaciones" name="observaciones" maxlength="255" placeholder="Opcional">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning">Registrar pago</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('inicioPagoSinCitaBuscar');
    var compraInput = document.getElementById('compraServicioBuscar');
    var compraSelect = document.getElementById('compraServicioPaciente');
    var tutorInput = document.getElementById('inicioPagoSinCitaTutor');
    var select = document.getElementById('inicioPagoSinCitaPaciente');
    var montoInput = document.getElementById('inicioPagoSinCitaMonto');
    var saldoTexto = document.getElementById('inicioPagoSinCitaSaldoTexto');
    var tutores = <?php echo json_encode($tutoresPagoSinCita, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var currency = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
    if (!input || !tutorInput || !select) {
      return;
    }

    function toNumber(value) {
      var parsed = Number.parseFloat(value || '0');
      return Number.isNaN(parsed) ? 0 : parsed;
    }

    function normalizeSearch(value) {
      return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    function renderPacientes(query) {
      var normalizedQuery = normalizeSearch(query);
      var currentValue = select.value;
      var hasCurrentValue = false;

      select.innerHTML = '<option value="">Selecciona paciente</option>';
      tutores.forEach(function (item) {
        var pacienteId = String(item.paciente_id || '');
        var pacienteNombre = String(item.paciente_nombre || 'Paciente sin nombre');
        var tutorNombre = String(item.tutor_nombre || 'Tutor sin nombre');
        var textoBusqueda = pacienteNombre + ' ' + tutorNombre;

        if (!pacienteId || (normalizedQuery !== '' && normalizeSearch(textoBusqueda).indexOf(normalizedQuery) === -1)) {
          return;
        }

        var option = document.createElement('option');
        option.value = pacienteId;
        option.dataset.tutorId = String(item.tutor_id || '');
        option.dataset.saldo = String(item.saldo_demo || 0);
        option.textContent = pacienteNombre + ' - tutor ' + tutorNombre + ' - saldo ' + currency.format(toNumber(item.saldo_demo));
        select.appendChild(option);

        if (pacienteId === currentValue) {
          hasCurrentValue = true;
        }
      });

      if (currentValue && hasCurrentValue) {
        select.value = currentValue;
      } else {
        tutorInput.value = '';
      }
      updateSaldoTexto();
    }

    function renderPacientesCompra(query) {
      if (!compraSelect) {
        return;
      }
      var normalizedQuery = normalizeSearch(query);
      var currentValue = compraSelect.value;
      var hasCurrentValue = false;
      compraSelect.innerHTML = '<option value="">Selecciona paciente</option>';
      tutores.forEach(function (item) {
        var pacienteId = String(item.paciente_id || '');
        var pacienteNombre = String(item.paciente_nombre || 'Paciente sin nombre');
        var tutorNombre = String(item.tutor_nombre || 'Tutor sin nombre');
        var textoBusqueda = pacienteNombre + ' ' + tutorNombre;
        if (!pacienteId || (normalizedQuery !== '' && normalizeSearch(textoBusqueda).indexOf(normalizedQuery) === -1)) {
          return;
        }
        var option = document.createElement('option');
        option.value = pacienteId;
        option.textContent = pacienteNombre + ' - tutor ' + tutorNombre;
        compraSelect.appendChild(option);
        if (pacienteId === currentValue) {
          hasCurrentValue = true;
        }
      });
      if (currentValue && hasCurrentValue) {
        compraSelect.value = currentValue;
      }
    }

    function updateSaldoTexto() {
      if (!saldoTexto) {
        return;
      }
      var option = select.options[select.selectedIndex];
      if (!option || !option.value) {
        saldoTexto.textContent = 'El saldo estimado se mostrara al elegir paciente y monto.';
        return;
      }
      var saldoActual = toNumber(option.dataset.saldo);
      var monto = montoInput ? Math.max(0, toNumber(montoInput.value)) : 0;
      saldoTexto.textContent = 'Saldo actual: ' + currency.format(saldoActual) + ' | Pago: +' + currency.format(monto) + ' | Saldo final: ' + currency.format(saldoActual + monto);
    }

    function updatePacienteSeleccionado() {
      var option = select.options[select.selectedIndex];
      tutorInput.value = option && option.value ? String(option.dataset.tutorId || '') : '';
      updateSaldoTexto();
    }

    input.addEventListener('input', function () {
      renderPacientes(input.value);
    });
    if (compraInput) {
      compraInput.addEventListener('input', function () {
        renderPacientesCompra(compraInput.value);
      });
    }
    select.addEventListener('change', updatePacienteSeleccionado);
    if (montoInput) {
      montoInput.addEventListener('input', updateSaldoTexto);
      montoInput.addEventListener('change', updateSaldoTexto);
    }
    renderPacientes('');
    renderPacientesCompra('');
  });
</script>
<?php endif; ?>

<style>
  .inicio-availability-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px;
  }
  .inicio-availability-day {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
  }
  .inicio-availability-day-header {
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
    padding: 10px 12px;
    font-weight: 700;
  }
  .inicio-availability-slot {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 10px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 12px;
  }
  .inicio-availability-slot.available {
    background: #f0fdf4;
    color: #166534;
  }
  .inicio-availability-slot.busy {
    background: #fff7ed;
    color: #9a3412;
  }
</style>

<div class="modal fade" id="modalInicioHistorialPaciente" tabindex="-1" aria-labelledby="modalInicioHistorialPacienteLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalInicioHistorialPacienteLabel">Historial de citas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="modalInicioHistorialPacienteBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalInicioDisponibilidadPsicologa" tabindex="-1" aria-labelledby="modalInicioDisponibilidadPsicologaLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalInicioDisponibilidadPsicologaLabel">Disponibilidad de psicóloga</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="modalInicioDisponibilidadPsicologaBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var historialPacientesInicio = <?php echo json_encode($historialPacientesInicio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var agendaPsicologosInicio = <?php echo json_encode($agendaPsicologosInicio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var currency = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function parseLocalDateTime(value) {
      var date = new Date(String(value || '').replace(' ', 'T'));
      return Number.isNaN(date.getTime()) ? null : date;
    }

    function pad2(value) {
      return String(value).padStart(2, '0');
    }

    function dateKey(date) {
      return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
    }

    function formatDateTime(value) {
      var date = parseLocalDateTime(value);
      return date ? date.toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' }) : String(value || '-');
    }

    function formatHour(hour) {
      var date = new Date(2000, 0, 1, hour, 0, 0);
      return date.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    function renderHistory(items) {
      if (!Array.isArray(items) || items.length === 0) {
        return '<div class="text-muted">Sin historial reciente para este paciente.</div>';
      }
      return '<div class="list-group list-group-flush">' + items.map(function (item) {
        return '<div class="list-group-item px-0">'
          + '<div class="d-flex justify-content-between gap-3">'
          + '<div><div class="fw-semibold"><i class="fas fa-user-md me-2 text-primary"></i>' + escapeHtml(item.psicologo || 'Sin psicóloga') + '</div>'
          + '<div class="small text-muted">#' + escapeHtml(item.id) + ' · ' + escapeHtml(item.tipo || '-') + ' · ' + formatDateTime(item.programado) + '</div></div>'
          + '<div class="text-end"><span class="badge bg-light text-dark border">' + escapeHtml(item.estatus || 'Sin estatus') + '</span>'
          + '<div class="small fw-semibold mt-1">' + currency.format(Number(item.costo || 0)) + '</div></div>'
          + '</div></div>';
      }).join('') + '</div>';
    }

    function renderAvailability(items) {
      var busyBySlot = {};
      (Array.isArray(items) ? items : []).forEach(function (item) {
        var date = parseLocalDateTime(item.programado);
        if (!date) {
          return;
        }
        var duration = Math.max(60, Number(item.tiempo || 60));
        var end = new Date(date.getTime() + duration * 60000);
        for (var cursor = new Date(date); cursor < end; cursor.setHours(cursor.getHours() + 1)) {
          busyBySlot[dateKey(cursor) + ' ' + pad2(cursor.getHours()) + ':00'] = item;
        }
      });

      var today = new Date();
      today.setHours(0, 0, 0, 0);
      var cards = [];
      for (var dayOffset = 0; dayOffset < 7; dayOffset += 1) {
        var day = new Date(today.getTime());
        day.setDate(today.getDate() + dayOffset);
        var dayOfWeek = day.getDay();
        if (dayOfWeek === 0) {
          continue;
        }
        var startHour = dayOfWeek === 6 ? 9 : 12;
        var endHour = dayOfWeek === 6 ? 14 : 20;
        var slots = [];
        for (var hour = startHour; hour < endHour; hour += 1) {
          var key = dateKey(day) + ' ' + pad2(hour) + ':00';
          var appointment = busyBySlot[key];
          if (appointment) {
            slots.push('<div class="inicio-availability-slot busy"><span><i class="fas fa-user-clock me-1"></i>' + formatHour(hour) + ' - ' + formatHour(hour + 1) + '</span><span class="text-end">' + escapeHtml(appointment.paciente || 'Ocupado') + '</span></div>');
          } else {
            slots.push('<div class="inicio-availability-slot available"><span><i class="fas fa-check-circle me-1"></i>' + formatHour(hour) + ' - ' + formatHour(hour + 1) + '</span><span>Disponible</span></div>');
          }
        }
        cards.push('<div class="inicio-availability-day"><div class="inicio-availability-day-header">' + escapeHtml(day.toLocaleDateString('es-MX', { weekday: 'long', day: '2-digit', month: 'short' })) + '</div>' + slots.join('') + '</div>');
      }
      return '<div class="d-flex flex-wrap gap-2 mb-3"><span class="badge bg-success">Disponible</span><span class="badge bg-warning text-dark">Ocupado</span></div><div class="inicio-availability-grid">' + cards.join('') + '</div>';
    }

    document.addEventListener('click', function (event) {
      var historyButton = event.target.closest ? event.target.closest('.inicio-open-patient-history') : null;
      if (historyButton) {
        var patientId = historyButton.dataset.pacienteId || '';
        var title = document.getElementById('modalInicioHistorialPacienteLabel');
        var body = document.getElementById('modalInicioHistorialPacienteBody');
        if (title) {
          title.innerHTML = '<i class="fas fa-history me-2"></i>Top 10 historial de ' + escapeHtml(historyButton.dataset.pacienteNombre || 'paciente');
        }
        if (body) {
          body.innerHTML = renderHistory(historialPacientesInicio[patientId] || []);
        }
        new bootstrap.Modal(document.getElementById('modalInicioHistorialPaciente')).show();
        return;
      }

      var availabilityButton = event.target.closest ? event.target.closest('.inicio-open-psych-availability') : null;
      if (availabilityButton) {
        var psicologoId = availabilityButton.dataset.psicologoId || '';
        var availabilityTitle = document.getElementById('modalInicioDisponibilidadPsicologaLabel');
        var availabilityBody = document.getElementById('modalInicioDisponibilidadPsicologaBody');
        if (availabilityTitle) {
          availabilityTitle.innerHTML = '<i class="far fa-calendar-alt me-2"></i>Disponibilidad de ' + escapeHtml(availabilityButton.dataset.psicologoNombre || 'psicóloga');
        }
        if (availabilityBody) {
          availabilityBody.innerHTML = renderAvailability(agendaPsicologosInicio[psicologoId] || []);
        }
        new bootstrap.Modal(document.getElementById('modalInicioDisponibilidadPsicologa')).show();
      }
    });
  });
</script>







<hr>
<div class="row ">
  <h2>Generar cita</h2>
  <div class="col-12 col-sm-6 col-md-6 col-xl-4 ">
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <h3>Cliente</h3>
        </div>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-12 col-lg-12">
            <form onsubmit="return searchNino();">
              <div class="form-group">
                <label for="nameSelect">Lista</label>
                <select class="form-select" name="nameSelect" id="nameSelect" onchange="updateResumen()">
                  <!-- Opciones se llenarán dinámicamente desde get_names.php -->
                </select>
                <hr>
                <label for="search">Buscar</label>
                <div class="input-group">
                  <!-- Campo de búsqueda -->
                  <input class="form-control" type="text" name="search" id="search" placeholder="Buscar por nombre">
                  <button class="btn btn-black btn-border" type="submit">Buscar</button>
                </div>
              </div>
            </form>
            <div id="results"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-6 col-xl-4">
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <h3>Fecha y tipo de cita</h3>
        </div>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-12 col-lg-12">
            <div class="form-group">
              <label for="idEmpleado">Psicólogos</label>
              <select name="empleado" id="idEmpleado" class="form-select" onchange="updateResumen()">
                <!-- Opciones se llenarán dinámicamente desde get_names.php -->
              </select>
            </div>
            <div class="form-group">
              <label for="costosSelect"> Tipo</label>
              <select name="costos" id="costosSelect" class="form-select" onchange="updateResumen()">
                <!-- Opciones se llenarán dinámicamente desde get_names.php -->
              </select>
            </div>
            <div class="form-group">
              <label for="paqueteSelect">Paquete</label>
              <select name="paquete" id="paqueteSelect" class="form-select" onchange="handlePaqueteChange()">
                <option value="">Sin paquete</option>
              </select>
            </div>
            <div class="form-group">
              <label for="citaDia"> Día de la cita</label>
              <input type="datetime-local" id="citaDia" name="citaDia" class="form-select" onchange="updateResumen()" step="3600">
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-6 col-xl-4">
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <h3>Resumen de Cita</h3>
        </div>
      </div>
      <div class="card-body">
        <form id="formCita" action="procesar_cita.php" method="POST" onsubmit="validarFormulario(event)">
          <div class="row">
            <div class="col-md-12 col-lg-12">
              <div class="form-group">
                <label for="resumenCliente">Cliente</label>
                <input type="text" name="resumenCliente" id="resumenCliente" class="form-control" readonly>
                <input type="text" name="sendIdCliente" id="sendIdCliente" class="form-control" hidden readonly>
              </div>
              <div class="form-group">
                <label for="resumenPsicologo">Psicólogo</label>
                <input type="text" name="resumenPsicologo" id="resumenPsicologo" class="form-control" readonly>
                <input type="text" name="sendIdPsicologo" id="sendIdPsicologo" class="form-control" hidden readonly>
              </div>
              <div class="form-group">
                <label for="resumenTipo">Tipo</label>
                <input type="text" name="resumenTipo" id="resumenTipo" class="form-control" readonly>
                <input type="hidden" name="sendIdPrecio" id="sendIdPrecio">
              </div>
              <div class="form-group">
                <label for="resumenCosto">Costo</label>
                <input type="number" name="resumenCosto" id="resumenCosto" class="form-control" min="0" step="0.01">
                <div class="alert alert-info py-2 mt-2 d-none" id="servicioActivoAviso"></div>
              </div>
              <div class="row g-2 d-none" id="grupoPagoInicialServicio">
                <div class="col-12 col-md-6">
                  <label for="servicioPagoInicial" class="form-label">Pago inicial del servicio</label>
                  <input type="number" name="servicio_pago_inicial" id="servicioPagoInicial" class="form-control" min="0" step="0.01" value="0.00">
                </div>
                <div class="col-12 col-md-6">
                  <label for="servicioMetodo" class="form-label">Método</label>
                  <select name="servicio_metodo" id="servicioMetodo" class="form-select">
                    <option value="">Sin pago</option>
                    <option value="Efectivo">Efectivo</option>
                    <option value="Transferencia">Transferencia</option>
                    <option value="Tarjeta">Tarjeta</option>
                  </select>
                </div>
                <div class="col-12"><div class="form-text">Para servicios, puedes registrar sin pago, pago inicial o liquidar el total.</div></div>
              </div>
              <div class="form-group">
                <label for="resumenPaquete">Paquete</label>
                <input type="text" name="resumenPaquete" id="resumenPaquete" class="form-control" value="Sin paquete" readonly>
                <input type="hidden" name="sendIdPaquete" id="sendIdPaquete">
              </div>
              <div class="form-group d-none" id="grupoMetodoPaquete">
                <label for="paqueteMetodo">Método del primer pago</label>
                <select name="paqueteMetodo" id="paqueteMetodo" class="form-select">
                  <option value="">Selecciona una opción</option>
                  <option value="Efectivo">Efectivo</option>
                  <option value="Transferencia">Transferencia</option>
                </select>
              </div>
              <div class="form-group d-none" id="grupoSaldoPaquete">
                <label for="resumenSaldoPaquete">Saldo adicional</label>
                <input type="text" id="resumenSaldoPaquete" class="form-control" readonly>
              </div>
              <div class="form-group">
                <label for="resumenFecha">Fecha de la Cita</label>
                <input type="datetime-local" name="resumenFecha" id="resumenFecha" class="form-control" readonly step="3600">
              </div>
              <div class="form-group">
                <label for="resumenTiempo">Tiempo (minutos)</label>
                <input type="number" name="resumenTiempo" id="resumenTiempo" class="form-control" min="1" step="1" value="60">
              </div>
              <button type="submit" class="btn btn-primary mt-3">Guardar Cita</button>
              <hr>
              <div class="card card-stats card-round" id="idResultado">
                <div class="card-body">
                  <div class="row">
                    <div class="col-2">
                      <div class="icon-big text-center">
                        <i class="icon-close text-danger"></i>
                      </div>
                    </div>
                    <div class="col-10 col-stats">
                      <div class="numbers">

                        <div id="resultado"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="modalEfectivoInicial" tabindex="-1" aria-labelledby="modalEfectivoInicialLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="accion" value="registrar_efectivo_inicial">
        <input type="hidden" name="fecha_efectivo" value="<?php echo htmlspecialchars($fechaCorteHoy, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEfectivoInicialLabel">Efectivo inicial del día</h5>
        </div>
        <div class="modal-body">
          <p class="mb-3">
            Ingresa el efectivo con el que inicia la jornada del <?php echo htmlspecialchars($fechaCorteHoyTexto, ENT_QUOTES, 'UTF-8'); ?>.
          </p>
          <div class="mb-3">
            <label for="efectivo_inicial_modal" class="form-label">Efectivo inicial</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" min="0" step="0.01" class="form-control" id="efectivo_inicial_modal" name="efectivo_inicial" required>
            </div>
            <div class="form-text">Este monto quedará bloqueado una vez guardado.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Guardar efectivo inicial</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalReunionesProgramadas" tabindex="-1" aria-labelledby="modalReunionesProgramadasLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalReunionesProgramadasLabel">Reuniones programadas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($reunionesProgramadasTotal > 0): ?>
          <div class="table-responsive">
            <table class="table table-striped table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Título</th>
                  <th>Inicio</th>
                  <th>Fin</th>
                  <th>Psicólogas</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($reunionesProgramadas as $reunion): ?>
                  <tr>
                    <td><?php echo (int) $reunion['id']; ?></td>
                    <td><?php echo htmlspecialchars($reunion['titulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($reunion['inicio'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($reunion['fin'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($reunion['psicologos'], ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted mb-0">No hay reuniones programadas por mostrar.</p>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="ModalTipoPago" tabindex="-1" aria-labelledby="ModalTipoPagoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ModalTipoPagoLabel">Seleccione el tipo de pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Paciente</label>
                        <p id="modalPacienteNombre" class="form-control-plaintext mb-0"></p>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label fw-semibold">Costo de la cita</label>
                            <p id="modalCostoCita" class="form-control-plaintext mb-0"></p>
                        </div>
                        <div class="col">
                            <label class="form-label fw-semibold">Saldo disponible</label>
                            <p id="modalSaldoActual" class="form-control-plaintext mb-0"></p>
                        </div>
                    </div>
                    <div class="alert alert-warning d-none" id="alertaSaldoInsuficiente" role="alert">
                        El monto asignado al saldo excede el saldo disponible del paciente.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pagos registrados</label>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="tablaPagos">
                                <thead>
                                    <tr>
                                        <th scope="col">Forma de pago</th>
                                        <th scope="col" style="width: 160px;">Monto</th>
                                        <th scope="col" style="width: 60px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="form-text">Registra una o varias formas de pago hasta cubrir el costo de la cita.</div>
                    </div>
                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="agregarPago">
                            Agregar forma de pago
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="agregarPagoSaldo">
                            Usar saldo disponible
                        </button>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="imprimirTicket" checked>
                        <label class="form-check-label" for="imprimirTicket">
                            Imprimir ticket y agregar a la cola de impresión
                        </label>
                    </div>
                    <p class="fw-semibold mb-0" id="resumenPagos"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmarPago">Confirmar</button>
                </div>
            </div>
        </div>
    </div>
<div class="modal fade" id="modalProximaCita" tabindex="-1" aria-labelledby="modalProximaCitaLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalProximaCitaLabel">Agendar próxima cita</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-success d-none" id="alertaPagoExitoso" role="alert"></div>
        <p class="mb-3">¿Deseas agendar una cita para la próxima semana en el mismo horario? Puedes ajustar los datos antes de confirmar.</p>
        <form id="formProximaCita">
          <input type="hidden" name="sendIdCliente" id="proximaSendIdCliente">
          <input type="hidden" name="sendIdPsicologo" id="proximaSendIdPsicologo">
          <input type="hidden" name="resumenTipo" id="proximaResumenTipo">
          <div class="mb-3">
            <label for="proximaCliente" class="form-label">Cliente</label>
            <input type="text" class="form-control" id="proximaCliente" readonly>
          </div>
          <div class="mb-3">
            <label for="proximaPsicologo" class="form-label">Psicólogo</label>
            <input type="text" class="form-control" id="proximaPsicologo" readonly>
          </div>
          <div class="mb-3">
            <label for="proximaFecha" class="form-label">Fecha sugerida</label>
            <input type="datetime-local" class="form-control" id="proximaFecha" name="resumenFecha" step="3600" required>
          </div>
          <div class="mb-3">
            <label for="proximaCosto" class="form-label">Costo</label>
            <input type="number" class="form-control" id="proximaCosto" name="resumenCosto" min="0" step="0.01" required>
          </div>
          <div class="mb-3">
            <label for="proximaTiempo" class="form-label">Tiempo (minutos)</label>
            <input type="number" class="form-control" id="proximaTiempo" name="resumenTiempo" min="1" step="1" value="60" required>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="omitirProximaCita">Omitir</button>
        <button type="button" class="btn btn-primary" id="confirmarProximaCita">Agendar cita</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="updateModalLabel">Actualizar Fecha de Cita</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="updateForm" method="post" action="update.php">
          <div class="mb-3">
            <label for="citaId" class="form-label">ID de la Cita</label>
            <input type="text" class="form-control" id="citaId" name="citaId" readonly>
          </div>
          <div class="mb-3">
            <label for="fechaProgramada" class="form-label">Nueva Fecha Programada</label>
            <input type="datetime-local" class="form-control" id="fechaProgramada" name="fechaProgramada" required step="3600">
          </div>
          <div class="alert alert-info" id="solicitudAviso" style="display:none;">
            La solicitud será enviada al coordinador para su aprobación.
          </div>
          <button type="submit" class="btn btn-primary" id="updateSubmitButton">Actualizar</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php
include 'Modulos/footer.php';
?>

<?php if ($requiereEfectivoInicial): ?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var modalElement = document.getElementById('modalEfectivoInicial');
    if (modalElement && typeof bootstrap !== 'undefined') {
      var modal = new bootstrap.Modal(modalElement, {backdrop: 'static', keyboard: false});
      modal.show();
    }
  });
</script>
<?php endif; ?>

<script>window.ES_VENTAS = <?php echo ($rolUsuario == 1) ? 'true' : 'false'; ?>;</script>
<script src="assets/js/citas.js?v=<?php echo time(); ?>"></script>
