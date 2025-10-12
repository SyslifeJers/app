<?php
// Datos de la conexión
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once 'conexion.php';
require_once __DIR__ . '/Modulos/logger.php';
require_once __DIR__ . '/Modulos/saldo_pacientes.php';
$conn = conectar();
session_start();

date_default_timezone_set('America/Mexico_City');
$fechaActual = date('Y-m-d H:i:s'); // Formato de fecha y hora actual

$idUsuario = $_SESSION['id'] ?? null;
$rolUsuario = $_SESSION['rol'] ?? null;

$ROL_VENTAS = 1;
$ROL_ADMIN = 3;
$ROL_COORDINADOR = 5;
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function finalizarRespuesta($success, $mensaje, $tipo = 'success', array $extra = [])
{
    global $isAjax;

    $_SESSION['cancelacion_mensaje'] = $mensaje;
    $_SESSION['cancelacion_tipo'] = $tipo;

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $mensaje,
            'tipo' => $tipo,
        ], $extra));
        exit;
    }

    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $citaId = isset($_POST['citaId']) ? (int) $_POST['citaId'] : 0;
    $estatus = isset($_POST['estatus']) ? (int) $_POST['estatus'] : 0;
    $formaPago = isset($_POST['formaPago']) ? trim((string) $_POST['formaPago']) : null;
    $pagosRegistrados = [];
    if (isset($_POST['pagos'])) {
        $pagosDecodificados = json_decode((string) $_POST['pagos'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($pagosDecodificados)) {
            foreach ($pagosDecodificados as $pagoDetalle) {
                $metodo = isset($pagoDetalle['metodo']) ? trim((string) $pagoDetalle['metodo']) : '';
                $monto = isset($pagoDetalle['monto']) ? (float) $pagoDetalle['monto'] : 0.0;
                if ($metodo !== '' && $monto >= 0) {
                    $pagosRegistrados[] = [
                        'metodo' => substr($metodo, 0, 50),
                        'monto' => $monto
                    ];
                }
            }
        }
    }
    $usarSaldo = isset($_POST['usarSaldo']) ? ((int) $_POST['usarSaldo'] === 1) : false;
    $montoPago = isset($_POST['montoPago']) ? (float) $_POST['montoPago'] : 0.0;

    if ($citaId <= 0 || $estatus <= 0) {
        finalizarRespuesta(false, 'La cita seleccionada no es válida.', 'danger');
    }

    $fechaProgramadaActual = null;
    $pacienteId = null;
    $costoCita = 0.0;
    if ($stmtDatosCita = $conn->prepare('SELECT Programado, IdNino, costo FROM Cita WHERE id = ?')) {
        $stmtDatosCita->bind_param('i', $citaId);
        $stmtDatosCita->execute();
        $stmtDatosCita->bind_result($fechaProgramadaActual, $pacienteId, $costoCita);
        $stmtDatosCita->fetch();
        $stmtDatosCita->close();
    }

    if (!$fechaProgramadaActual) {
        finalizarRespuesta(false, 'No fue posible localizar la cita seleccionada.', 'danger');
    }

    if ($rolUsuario === $ROL_VENTAS && $estatus === 1) {
        $tablaSolicitudes = $conn->query("SHOW TABLES LIKE 'SolicitudReprogramacion'");
        if (!($tablaSolicitudes instanceof mysqli_result) || $tablaSolicitudes->num_rows === 0) {
            if ($tablaSolicitudes instanceof mysqli_result) {
                $tablaSolicitudes->free();
            }
            finalizarRespuesta(false, 'El módulo de solicitudes no está disponible. Contacta al administrador.', 'danger');
        }
        $tablaSolicitudes->free();

        $totalPendientes = 0;
        if ($stmtPendiente = $conn->prepare("SELECT COUNT(*) FROM SolicitudReprogramacion WHERE cita_id = ? AND estatus = 'pendiente' AND tipo = 'cancelacion'")) {
            $stmtPendiente->bind_param('i', $citaId);
            $stmtPendiente->execute();
            $stmtPendiente->bind_result($totalPendientes);
            $stmtPendiente->fetch();
            $stmtPendiente->close();
        }

        if ($totalPendientes > 0) {
            finalizarRespuesta(false, 'Ya existe una solicitud de cancelación pendiente para esta cita.', 'warning');
        }

        if ($stmtSolicitud = $conn->prepare("INSERT INTO SolicitudReprogramacion (cita_id, fecha_anterior, nueva_fecha, estatus, solicitado_por, fecha_solicitud, tipo) VALUES (?, ?, ?, 'pendiente', ?, ?, 'cancelacion')")) {
            $stmtSolicitud->bind_param('issis', $citaId, $fechaProgramadaActual, $fechaProgramadaActual, $idUsuario, $fechaActual);
            $stmtSolicitud->execute();
            $solicitudId = $conn->insert_id;
            $stmtSolicitud->close();

            registrarLog(
                $conn,
                $idUsuario,
                'citas',
                'solicitud_cancelacion',
                sprintf('Solicitud de cancelación para la cita #%d programada el %s.', $citaId, $fechaProgramadaActual),
                'SolicitudReprogramacion',
                (string) $solicitudId
            );
            finalizarRespuesta(true, 'Se envió la solicitud de cancelación para aprobación.', 'success');
        } else {
            finalizarRespuesta(false, 'No fue posible registrar la solicitud de cancelación. Intenta nuevamente.', 'danger');
        }
    }

    $usaTransaccion = false;
    $detallesSaldo = [];
    $detallesPagos = [];

    if ($estatus === 4) {
        $usaTransaccion = true;
        $conn->begin_transaction();
    }

    try {
        if ($estatus === 4) {
            if (!$pacienteId) {
                throw new Exception('No fue posible identificar al paciente de la cita.');
            }

            $pagosProcesados = $pagosRegistrados;

            if (empty($pagosProcesados) && $usarSaldo) {
                $pagosProcesados[] = [
                    'metodo' => 'Saldo',
                    'monto' => (float) $costoCita
                ];
            }

            if (empty($pagosProcesados) && $formaPago !== null && $formaPago !== '' && $montoPago >= 0) {
                $pagosProcesados[] = [
                    'metodo' => $formaPago,
                    'monto' => $montoPago
                ];
            }

            if (empty($pagosProcesados)) {
                throw new Exception('Registra al menos una forma de pago válida.');
            }

            $totalPagos = 0.0;
            $totalSaldoUtilizado = 0.0;
            $totalPagosExternos = 0.0;
            $metodosResumen = [];
            $pagosValidados = [];

            foreach ($pagosProcesados as $pagoDetalle) {
                $metodo = isset($pagoDetalle['metodo']) ? trim((string) $pagoDetalle['metodo']) : '';
                $monto = isset($pagoDetalle['monto']) ? (float) $pagoDetalle['monto'] : 0.0;

              //  if ($metodo === '' || $monto <= 0) {
                //    throw new Exception('Cada forma de pago debe incluir un método y un monto mayor a cero.');
               // }

                if (strlen($metodo) > 50) {
                    $metodo = substr($metodo, 0, 50);
                }

                $totalPagos += $monto;
                if (strcasecmp($metodo, 'Saldo') === 0) {
                    $totalSaldoUtilizado += $monto;
                } else {
                    $totalPagosExternos += $monto;
                }

                $metodosResumen[] = $metodo;
                $pagosValidados[] = [
                    'metodo' => $metodo,
                    'monto' => $monto
                ];
            }

            if ($totalPagos + 0.0001 < (float) $costoCita) {
                throw new Exception('El monto total registrado es menor al costo de la cita.');
            }

            $saldoDisponible = obtenerSaldoPaciente($conn, (int) $pacienteId);
            if ($totalSaldoUtilizado > 0) {
                if ($saldoDisponible + 0.0001 < $totalSaldoUtilizado) {
                    throw new Exception('El saldo del paciente es insuficiente para cubrir los montos asignados.');
                }
                if (!ajustarSaldoPaciente($conn, (int) $pacienteId, -1 * $totalSaldoUtilizado)) {
                    throw new Exception('No fue posible actualizar el saldo del paciente.');
                }
                $detallesSaldo[] = sprintf('Se descontaron %s del saldo del paciente.', number_format($totalSaldoUtilizado, 2));
            }

            $montoNecesarioExternos = max(0, (float) $costoCita - $totalSaldoUtilizado);
            $excedente = max(0, $totalPagosExternos - $montoNecesarioExternos);
            if ($excedente > 0) {
                if (!ajustarSaldoPaciente($conn, (int) $pacienteId, $excedente)) {
                    throw new Exception('No fue posible almacenar el saldo restante del paciente.');
                }
                $detallesSaldo[] = sprintf('Se agregaron %s al saldo del paciente.', number_format($excedente, 2));
            }

            $stmtEliminarPagos = $conn->prepare('DELETE FROM CitaPagos WHERE cita_id = ?');
            if (!$stmtEliminarPagos) {
                throw new Exception('No fue posible preparar la limpieza de los pagos registrados previamente.');
            }
            $stmtEliminarPagos->bind_param('i', $citaId);
            $stmtEliminarPagos->execute();
            $stmtEliminarPagos->close();

            $insertQuery = $idUsuario !== null
                ? 'INSERT INTO CitaPagos (cita_id, metodo, monto, registrado_por) VALUES (?, ?, ?, ?)'
                : 'INSERT INTO CitaPagos (cita_id, metodo, monto, registrado_por) VALUES (?, ?, ?, NULL)';
            $stmtInsertPago = $conn->prepare($insertQuery);
            if (!$stmtInsertPago) {
                throw new Exception('No fue posible guardar el detalle de los pagos.');
            }

            foreach ($pagosValidados as $pagoValidado) {
                $metodo = $pagoValidado['metodo'];
                $monto = $pagoValidado['monto'];

                if ($idUsuario !== null) {
                    $stmtInsertPago->bind_param('isdi', $citaId, $metodo, $monto, $idUsuario);
                } else {
                    $stmtInsertPago->bind_param('isd', $citaId, $metodo, $monto);
                }

                if (!$stmtInsertPago->execute()) {
                    $stmtInsertPago->close();
                    throw new Exception('No fue posible guardar el detalle de los pagos.');
                }
            }

            $stmtInsertPago->close();

            $metodosUnicos = array_values(array_unique($metodosResumen));
            if (count($metodosUnicos) === 1) {
                $formaPago = $metodosUnicos[0];
            } elseif (!empty($metodosUnicos)) {
                $resumenMixto = 'Mixto (' . implode(', ', $metodosUnicos) . ')';
                $formaPago = substr($resumenMixto, 0, 50);
            }

            foreach ($pagosValidados as $pagoValidado) {
                $detallesPagos[] = sprintf('%s $%s', $pagoValidado['metodo'], number_format($pagoValidado['monto'], 2));
            }
        }

        if ($formaPago === null || $formaPago === '') {
            $stmt = $conn->prepare('UPDATE Cita SET FormaPago = NULL, Estatus = ? WHERE id = ?');
            $stmt->bind_param('ii', $estatus, $citaId);
        } else {
            $stmt = $conn->prepare('UPDATE Cita SET FormaPago = ?, Estatus = ? WHERE id = ?');
            $stmt->bind_param('sii', $formaPago, $estatus, $citaId);
        }
        $stmt->execute();
        $stmt->close();

        $stmtInsert = $conn->prepare('INSERT INTO HistorialEstatus(id, fecha, idEstatus, idCita, idUsuario) VALUES (null, ?, ?, ?, ?)');
        $stmtInsert->bind_param('siii', $fechaActual, $estatus, $citaId, $idUsuario);
        $stmtInsert->execute();
        $stmtInsert->close();

        if ($usaTransaccion) {
            $conn->commit();
        }
    } catch (Exception $e) {
        if ($usaTransaccion) {
            $conn->rollback();
        }
        finalizarRespuesta(false, $e->getMessage(), 'danger');
    }

    $accionLog = 'actualizar_estatus';
    $descripcionLog = sprintf('La cita #%d cambió su estatus a %d.', $citaId, $estatus);

    if ($estatus === 1) {
        $accionLog = 'cancelar';
        $descripcionLog = sprintf('La cita #%d fue cancelada.', $citaId);
    } elseif ($estatus === 4) {
        $accionLog = 'registrar_pago';
        $descripcionLog = sprintf('Se registró el pago de la cita #%d.', $citaId);
    }

    if ($formaPago !== null && $formaPago !== '') {
        $descripcionLog .= sprintf(' Forma de pago: %s.', $formaPago);
    }

    if (!empty($detallesPagos)) {
        $descripcionLog .= ' Pagos registrados: ' . implode('; ', $detallesPagos) . '.';
    }

    if (!empty($detallesSaldo)) {
        $descripcionLog .= ' ' . implode(' ', $detallesSaldo);
    }

    registrarLog(
        $conn,
        $idUsuario,
        'citas',
        $accionLog,
        $descripcionLog,
        'Cita',
        (string) $citaId
    );

    if ($estatus === 1) {
        $tablaSolicitudes = $conn->query("SHOW TABLES LIKE 'SolicitudReprogramacion'");
        if ($tablaSolicitudes instanceof mysqli_result && $tablaSolicitudes->num_rows > 0) {
            $tablaSolicitudes->free();

            $comentarioCancelacion = 'Solicitud cerrada automáticamente por cancelación de la cita.';
            $stmtCancelar = $conn->prepare("UPDATE SolicitudReprogramacion SET estatus = 'rechazada', aprobado_por = ?, fecha_respuesta = ?, comentarios = CASE WHEN comentarios IS NULL OR comentarios = '' THEN ? ELSE CONCAT(comentarios, '\n', ?) END WHERE cita_id = ? AND estatus = 'pendiente' AND tipo = 'reprogramacion'");
            $stmtCancelar->bind_param('isssi', $idUsuario, $fechaActual, $comentarioCancelacion, $comentarioCancelacion, $citaId);
            $stmtCancelar->execute();
            $stmtCancelar->close();

            $comentarioAprobacion = 'Solicitud de cancelación aprobada automáticamente al cancelar la cita.';
            if ($stmtCerrarCancelacion = $conn->prepare("UPDATE SolicitudReprogramacion SET estatus = 'aprobada', aprobado_por = ?, fecha_respuesta = ?, comentarios = CASE WHEN comentarios IS NULL OR comentarios = '' THEN ? ELSE CONCAT(comentarios, '\n', ?) END WHERE cita_id = ? AND estatus = 'pendiente' AND tipo = 'cancelacion'")) {
                $stmtCerrarCancelacion->bind_param('isssi', $idUsuario, $fechaActual, $comentarioAprobacion, $comentarioAprobacion, $citaId);
                $stmtCerrarCancelacion->execute();
                $stmtCerrarCancelacion->close();
            }
        } elseif ($tablaSolicitudes instanceof mysqli_result) {
            $tablaSolicitudes->free();
        }

        $tablaSolicitudesCancelacion = $conn->query("SHOW TABLES LIKE 'SolicitudCancelacion'");
        if ($tablaSolicitudesCancelacion instanceof mysqli_result && $tablaSolicitudesCancelacion->num_rows > 0) {
            $tablaSolicitudesCancelacion->free();

            $comentarioAprobacion = 'Solicitud de cancelación aprobada automáticamente al cancelar la cita.';
            if ($stmtCerrarCancelacionAntiguo = $conn->prepare("UPDATE SolicitudCancelacion SET estatus = 'aprobada', aprobado_por = ?, fecha_respuesta = ?, comentarios = CASE WHEN comentarios IS NULL OR comentarios = '' THEN ? ELSE CONCAT(comentarios, '\n', ?) END WHERE cita_id = ? AND estatus = 'pendiente'")) {
                $stmtCerrarCancelacionAntiguo->bind_param('isssi', $idUsuario, $fechaActual, $comentarioAprobacion, $comentarioAprobacion, $citaId);
                $stmtCerrarCancelacionAntiguo->execute();
                $stmtCerrarCancelacionAntiguo->close();
            }
        } elseif ($tablaSolicitudesCancelacion instanceof mysqli_result) {
            $tablaSolicitudesCancelacion->free();
        }
    }

    if ($estatus === 1) {
        finalizarRespuesta(true, 'Cita cancelada correctamente.', 'success');
    } elseif ($estatus === 4) {
        $mensajePago = 'Pago registrado correctamente.';
        if (!empty($detallesPagos)) {
            $mensajePago .= ' Pagos: ' . implode('; ', $detallesPagos) . '.';
        }
        if (!empty($detallesSaldo)) {
            $mensajePago .= ' ' . implode(' ', $detallesSaldo);
        }

        $extra = [];
        if ($isAjax) {
            $extra['cita'] = [
                'programado' => $fechaProgramadaActual,
                'pacienteId' => $pacienteId,
                'costo' => (float) $costoCita
            ];
        }

        finalizarRespuesta(true, $mensajePago, 'success', $extra);
    } else {
        finalizarRespuesta(true, 'Estatus de la cita actualizado correctamente.', 'success');
    }
}

$conn->close();
?>
