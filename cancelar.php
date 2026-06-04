<?php
// Datos de la conexión
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once 'conexion.php';
require_once __DIR__ . '/Modulos/logger.php';
require_once __DIR__ . '/Modulos/resumen_pagos.php';
require_once __DIR__ . '/Modulos/saldo_pacientes.php';
$conn = conectar();
session_start();

date_default_timezone_set('America/Mexico_City');
$fechaActual = date('Y-m-d H:i:s'); // Formato de fecha y hora actual

$idUsuario = $_SESSION['id'] ?? null;
$rolUsuario = $_SESSION['rol'] ?? null;

$ROL_VENTAS = 1;
$ROL_RECEPCION = 2;
$ROL_ADMIN = 3;
$ROL_COORDINADOR = 5;
$ROL_PRACTICANTE = 6;
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function obtenerIdEstatus(mysqli $conn, string $nombre, int $valorPredeterminado): int
{
    $estatusId = null;
    $nombreNormalizado = strtolower(trim($nombre));

    $stmt = $conn->prepare('SELECT id FROM Estatus WHERE TRIM(LOWER(name)) = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $nombreNormalizado);
        if ($stmt->execute()) {
            $stmt->bind_result($estatusEncontrado);
            if ($stmt->fetch()) {
                $estatusId = (int) $estatusEncontrado;
            }
        }
        $stmt->close();
    }

    return $estatusId ?? $valorPredeterminado;
}

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
    if ($rolUsuario === $ROL_PRACTICANTE) {
        finalizarRespuesta(false, 'No tienes permisos para cancelar o finalizar citas.', 'danger');
    }
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
    $imprimirTicket = isset($_POST['imprimirTicket']) ? ((int) $_POST['imprimirTicket'] === 1) : false;

    $estatusCancelada = obtenerIdEstatus($conn, 'Cancelada', 1);
    $estatusFinalizada = obtenerIdEstatus($conn, 'Finalizada', 4);
    $esCancelacion = $estatus === $estatusCancelada;
    $esRegistroPago = $estatus === $estatusFinalizada;

    if ($citaId <= 0 || $estatus <= 0) {
        finalizarRespuesta(false, 'La cita seleccionada no es válida.', 'danger');
    }

    if ($esRegistroPago) {
        $conn->begin_transaction();
    }

    $fechaProgramadaActual = null;
    $pacienteId = null;
    $costoCita = 0.0;
    $psicologoIdCita = null;
    $pacienteNombreCita = '';
    $psicologoNombreCita = '';
    $estatusActualCita = null;
    $formaPagoActual = null;
    $consultaDatosCita = $esRegistroPago
        ? 'SELECT ci.Programado, ci.IdNino, ci.costo, ci.IdUsuario, n.name, u.name, ci.Estatus, ci.FormaPago FROM Cita ci INNER JOIN nino n ON n.id = ci.IdNino INNER JOIN Usuarios u ON u.id = ci.IdUsuario WHERE ci.id = ? FOR UPDATE'
        : 'SELECT ci.Programado, ci.IdNino, ci.costo, ci.IdUsuario, n.name, u.name, ci.Estatus, ci.FormaPago FROM Cita ci INNER JOIN nino n ON n.id = ci.IdNino INNER JOIN Usuarios u ON u.id = ci.IdUsuario WHERE ci.id = ?';
    if ($stmtDatosCita = $conn->prepare($consultaDatosCita)) {
        $stmtDatosCita->bind_param('i', $citaId);
        $stmtDatosCita->execute();
        $stmtDatosCita->bind_result($fechaProgramadaActual, $pacienteId, $costoCita, $psicologoIdCita, $pacienteNombreCita, $psicologoNombreCita, $estatusActualCita, $formaPagoActual);
        $stmtDatosCita->fetch();
        $stmtDatosCita->close();
    }

    if (!$fechaProgramadaActual) {
        if ($esRegistroPago) {
            $conn->rollback();
        }
        finalizarRespuesta(false, 'No fue posible localizar la cita seleccionada.', 'danger');
    }

    $tablaSolicitudesDisponible = false;
    $tablaSolicitudes = $conn->query("SHOW TABLES LIKE 'SolicitudReprogramacion'");
    if ($tablaSolicitudes instanceof mysqli_result) {
        $tablaSolicitudesDisponible = $tablaSolicitudes->num_rows > 0;
        $tablaSolicitudes->free();
    }

    $usaTransaccion = false;
    $detallesSaldo = [];
    $detallesPagos = [];
    $ticketEncolado = false;

    $isRecepcion = $rolUsuario === $ROL_RECEPCION;
    $shouldRegistrarAutoCancelacion = false;
    if ($tablaSolicitudesDisponible && $isRecepcion && $esCancelacion && $idUsuario !== null) {
        $totalPendientesRecepcion = 0;
        if ($stmtPendientesRecepcion = $conn->prepare("SELECT COUNT(*) FROM SolicitudReprogramacion WHERE cita_id = ? AND estatus = 'pendiente' AND tipo = 'cancelacion'")) {
            $stmtPendientesRecepcion->bind_param('i', $citaId);
            $stmtPendientesRecepcion->execute();
            $stmtPendientesRecepcion->bind_result($totalPendientesRecepcion);
            $stmtPendientesRecepcion->fetch();
            $stmtPendientesRecepcion->close();
        }
        $shouldRegistrarAutoCancelacion = $totalPendientesRecepcion === 0;
    }

    if ($esRegistroPago) {
        $usaTransaccion = true;
    }

    try {
        if ($esRegistroPago) {
            if (!$pacienteId) {
                throw new Exception('No fue posible identificar al paciente de la cita.');
            }

            $estatusActualCita = (int) $estatusActualCita;
            if ($estatusActualCita === $estatusFinalizada) {
                throw new Exception('El pago de la cita ya fue registrado previamente.');
            }

            if ($estatusActualCita === $estatusCancelada) {
                throw new Exception('No se puede registrar el pago de una cita cancelada.');
            }

            if ($formaPagoActual !== null && trim((string) $formaPagoActual) !== '') {
                throw new Exception('La cita ya tiene un pago registrado.');
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

            $saldoDisponible = obtenerSaldoPaciente($conn, (int) $pacienteId);
            if ($totalSaldoUtilizado > 0) {
                $ajusteSaldo = $saldoDisponible < -0.0001 ? $totalSaldoUtilizado : -1 * $totalSaldoUtilizado;
                if ($saldoDisponible >= -0.0001 && $saldoDisponible + 0.0001 < $totalSaldoUtilizado) {
                    throw new Exception('El saldo del paciente es insuficiente para cubrir los montos asignados.');
                }
                if (!ajustarSaldoPaciente($conn, (int) $pacienteId, $ajusteSaldo)) {
                    throw new Exception('No fue posible actualizar el saldo del paciente.');
                }
                $detallesSaldo[] = $saldoDisponible < -0.0001
                    ? sprintf('Se abonaron %s al adeudo del paciente.', number_format($totalSaldoUtilizado, 2))
                    : sprintf('Se descontaron %s del saldo del paciente.', number_format($totalSaldoUtilizado, 2));
            }

            $montoNecesarioExternos = max(0, (float) $costoCita - $totalSaldoUtilizado);
            $excedente = max(0, $totalPagosExternos - $montoNecesarioExternos);
            if ($excedente > 0) {
                if (!ajustarSaldoPaciente($conn, (int) $pacienteId, $excedente)) {
                    throw new Exception('No fue posible almacenar el saldo restante del paciente.');
                }
                $detallesSaldo[] = sprintf('Se agregaron %s al saldo del paciente.', number_format($excedente, 2));
            }

            $faltante = max(0, (float) $costoCita - $totalPagos);
            if ($faltante > 0) {
                if (!ajustarSaldoPaciente($conn, (int) $pacienteId, -1 * $faltante)) {
                    throw new Exception('No fue posible registrar el saldo pendiente del paciente.');
                }
                $detallesSaldo[] = sprintf('Quedó un saldo pendiente de %s para próximas citas.', number_format($faltante, 2));
            }

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

                registrarResumenPago($conn, [
                    'origen' => 'cita',
                    'referencia_id' => $citaId,
                    'cita_id' => $citaId,
                    'paciente_id' => $pacienteId,
                    'paciente_nombre' => $pacienteNombreCita,
                    'psicologo_id' => $psicologoIdCita,
                    'psicologo_nombre' => $psicologoNombreCita,
                    'monto' => $monto,
                    'metodo_pago' => $metodo,
                    'fecha_pago' => $fechaActual,
                    'fecha_corte' => substr($fechaActual, 0, 10),
                    'registrado_por' => $idUsuario,
                    'observaciones' => 'Pago registrado al finalizar cita.',
                ]);
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

            if ($imprimirTicket) {
                $stmtAgregarTicket = $conn->prepare('INSERT INTO colaTickets (id_cita) VALUES (?)');
                if (!$stmtAgregarTicket) {
                    throw new Exception('No fue posible preparar la cola de impresión.');
                }
                $stmtAgregarTicket->bind_param('i', $citaId);
                if (!$stmtAgregarTicket->execute()) {
                    $stmtAgregarTicket->close();
                    throw new Exception('No fue posible agregar el ticket a la cola de impresión.');
                }
                $stmtAgregarTicket->close();
                $ticketEncolado = true;
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

    if ($esCancelacion) {
        $accionLog = 'cancelar';
        $descripcionLog = sprintf('La cita #%d fue cancelada.', $citaId);
    } elseif ($esRegistroPago) {
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

    if ($shouldRegistrarAutoCancelacion) {
        $stmtRegistrarCancelacion = $conn->prepare("INSERT INTO SolicitudReprogramacion (cita_id, fecha_anterior, nueva_fecha, estatus, tipo, solicitado_por, comentarios, fecha_solicitud, aprobado_por, fecha_respuesta) VALUES (?, ?, ?, 'aprobada', 'cancelacion', ?, NULL, ?, ?, ?)");
        if ($stmtRegistrarCancelacion) {
            $stmtRegistrarCancelacion->bind_param('issisis', $citaId, $fechaProgramadaActual, $fechaProgramadaActual, $idUsuario, $fechaActual, $idUsuario, $fechaActual);
            if ($stmtRegistrarCancelacion->execute()) {
                $solicitudCancelacionId = $conn->insert_id;
                registrarLog(
                    $conn,
                    $idUsuario,
                    'citas',
                    'registrar_cancelacion_recepcion',
                    sprintf('La recepción registró automáticamente la cancelación de la cita #%d programada el %s.', $citaId, $fechaProgramadaActual),
                    'SolicitudReprogramacion',
                    (string) $solicitudCancelacionId
                );
            }
            $stmtRegistrarCancelacion->close();
        }
    }

    if ($esCancelacion) {
        if ($tablaSolicitudesDisponible) {
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

    if ($esCancelacion) {
        finalizarRespuesta(true, 'Cita cancelada correctamente.', 'success');
    } elseif ($esRegistroPago) {
        $mensajePago = 'Pago registrado correctamente.';
        if (!empty($detallesPagos)) {
            $mensajePago .= ' Pagos: ' . implode('; ', $detallesPagos) . '.';
        }
        if (!empty($detallesSaldo)) {
            $mensajePago .= ' ' . implode(' ', $detallesSaldo);
        }
        if ($ticketEncolado) {
            $mensajePago .= ' Ticket agregado a la cola de impresión.';
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
