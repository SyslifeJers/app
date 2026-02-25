<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/logger.php';

function responder(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['id'])) {
    responder(401, ['success' => false, 'message' => 'No autenticado.']);
}

$conn = conectar();
if (!$conn) {
    responder(500, ['success' => false, 'message' => 'No fue posible conectar con la base de datos.']);
}
$conn->set_charset('utf8mb4');



$metodoHttp = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodoHttp !== 'POST') {
    $conn->close();
    responder(405, ['success' => false, 'message' => 'Metodo no permitido.']);
}

$accion = isset($_POST['accion']) ? trim((string) $_POST['accion']) : '';
$usuarioId = (int) $_SESSION['id'];
$fechaActual = date('Y-m-d H:i:s');

function parseFechaLocal(string $valor): ?string
{
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $valor) ?: DateTime::createFromFormat('Y-m-d H:i:s', $valor);
    if (!$dt) {
        return null;
    }
    return $dt->format('Y-m-d H:i:s');
}

function validarMetodo(string $metodo): bool
{
    $permitidos = ['Efectivo', 'Transferencia', 'Tarjeta'];
    return in_array($metodo, $permitidos, true);
}

if ($accion === 'crear') {
    $ninoId = isset($_POST['nino_id']) ? (int) $_POST['nino_id'] : 0;
    $psicologoId = isset($_POST['psicologo_id']) ? (int) $_POST['psicologo_id'] : 0;
    $fechaSql = isset($_POST['fecha']) ? parseFechaLocal((string) $_POST['fecha']) : null;
    $sesionesTotal = isset($_POST['sesiones_total']) ? (int) $_POST['sesiones_total'] : 0;
    $total = isset($_POST['total']) ? (float) $_POST['total'] : 0.0;
    $pagoInicial = isset($_POST['pago_inicial']) ? (float) $_POST['pago_inicial'] : 0.0;
    $metodo = isset($_POST['metodo']) ? trim((string) $_POST['metodo']) : '';

    if ($ninoId <= 0 || $psicologoId <= 0 || $fechaSql === null || $sesionesTotal <= 0 || !is_finite($total) || $total <= 0) {
        $conn->close();
        responder(422, ['success' => false, 'message' => 'Datos invalidos para crear el diagnostico.']);
    }

    if (!is_finite($pagoInicial) || $pagoInicial < 0) {
        $conn->close();
        responder(422, ['success' => false, 'message' => 'El pago inicial no es valido.']);
    }

    if ($pagoInicial > 0.009) {
        if ($pagoInicial - 0.0001 > $total) {
            $conn->close();
            responder(422, ['success' => false, 'message' => 'El pago inicial no puede ser mayor al total.']);
        }
        if ($metodo === '' || !validarMetodo($metodo)) {
            $conn->close();
            responder(422, ['success' => false, 'message' => 'Selecciona un metodo de pago valido.']);
        }
    } else {
        $pagoInicial = 0.0;
        $metodo = '';
    }

    $saldoRestante = max(0.0, $total - $pagoInicial);

    $conn->begin_transaction();
    try {
        $estatusDiagnostico = 2;
        $stmtDiag = $conn->prepare('INSERT INTO Diagnosticos (nino_id, psicologo_id, cita_inicial_id, total, pago_inicial, saldo_restante, sesiones_total, sesiones_completadas, estatus_id, creado_por) VALUES (?, ?, NULL, ?, ?, ?, ?, 0, ?, ?)');
        if (!$stmtDiag) {
            throw new RuntimeException('No fue posible preparar el diagnostico.');
        }
        $stmtDiag->bind_param('iidddiii', $ninoId, $psicologoId, $total, $pagoInicial, $saldoRestante, $sesionesTotal, $estatusDiagnostico, $usuarioId);
        if (!$stmtDiag->execute()) {
            $stmtDiag->close();
            throw new RuntimeException('No fue posible guardar el diagnostico.');
        }
        $diagnosticoId = (int) $conn->insert_id;
        $stmtDiag->close();

        if ($pagoInicial > 0.009) {
            $stmtPago = $conn->prepare('INSERT INTO DiagnosticoPagos (diagnostico_id, metodo, monto, registrado_por) VALUES (?, ?, ?, ?)');
            if (!$stmtPago) {
                throw new RuntimeException('No fue posible preparar el pago inicial.');
            }
            $stmtPago->bind_param('isdi', $diagnosticoId, $metodo, $pagoInicial, $usuarioId);
            if (!$stmtPago->execute()) {
                $stmtPago->close();
                throw new RuntimeException('No fue posible guardar el pago inicial.');
            }
            $stmtPago->close();
        }

        // Evitar duplicados de cita a la misma hora para el paciente.
        $check = $conn->prepare('SELECT COUNT(*) FROM Cita WHERE IdNino = ? AND Programado = ?');
        if (!$check) {
            throw new RuntimeException('No fue posible validar la disponibilidad de la cita.');
        }
        $check->bind_param('is', $ninoId, $fechaSql);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();
        if ((int) $count > 0) {
            throw new RuntimeException('Ya existe una cita registrada para este paciente en esa fecha y hora.');
        }

        $estatusCita = 2;
        $tipoCita = 'Diagnostico';
        $costo = 0.0;
        $formaPago = 'Diagnostico';
        $sesionNumero = 1;

        $sqlCita = 'INSERT INTO Cita (IdNino, IdUsuario, idGenerado, fecha, costo, Programado, Estatus, Tipo, paquete_id, diagnostico_id, diagnostico_sesion, FormaPago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)';
        $stmtCita = $conn->prepare($sqlCita);
        if (!$stmtCita) {
            throw new RuntimeException('No fue posible preparar la cita.');
        }
        $stmtCita->bind_param('iiisdsisiss', $ninoId, $psicologoId, $usuarioId, $fechaActual, $costo, $fechaSql, $estatusCita, $tipoCita, $diagnosticoId, $sesionNumero, $formaPago);
        if (!$stmtCita->execute()) {
            $stmtCita->close();
            throw new RuntimeException('No fue posible guardar la cita.');
        }
        $citaId = (int) $conn->insert_id;
        $stmtCita->close();

        $stmtUpd = $conn->prepare('UPDATE Diagnosticos SET cita_inicial_id = ? WHERE id = ?');
        if ($stmtUpd) {
            $stmtUpd->bind_param('ii', $citaId, $diagnosticoId);
            $stmtUpd->execute();
            $stmtUpd->close();
        }

        registrarLog(
            $conn,
            $usuarioId,
            'diagnostico',
            'crear',
            sprintf('Se creo el diagnostico #%d para el paciente %d. Sesiones: %d. Total: %.2f. Pago inicial: %.2f.', $diagnosticoId, $ninoId, $sesionesTotal, $total, $pagoInicial),
            'Diagnosticos',
            (string) $diagnosticoId
        );

        $conn->commit();
        $conn->close();
        responder(200, ['success' => true, 'diagnostico_id' => $diagnosticoId, 'cita_id' => $citaId]);
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->close();
        responder(400, ['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($accion === 'reprogramar') {
    $citaId = isset($_POST['cita_id']) ? (int) $_POST['cita_id'] : 0;
    $fechaSql = isset($_POST['fecha']) ? parseFechaLocal((string) $_POST['fecha']) : null;
    if ($citaId <= 0 || $fechaSql === null) {
        $conn->close();
        responder(422, ['success' => false, 'message' => 'Datos invalidos para reprogramar.']);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT diagnostico_id FROM Cita WHERE id = ? FOR UPDATE');
        if (!$stmt) {
            throw new RuntimeException('No fue posible preparar la consulta de la cita.');
        }
        $stmt->bind_param('i', $citaId);
        $stmt->execute();
        $stmt->bind_result($diagnosticoId);
        if (!$stmt->fetch()) {
            $stmt->close();
            throw new RuntimeException('Cita no encontrada.');
        }
        $stmt->close();

        if ($diagnosticoId === null) {
            throw new RuntimeException('La cita no pertenece a un diagnostico.');
        }

        $estatusReprogramado = 3;
        $stmtUpd = $conn->prepare('UPDATE Cita SET Programado = ?, Estatus = ? WHERE id = ?');
        if (!$stmtUpd) {
            throw new RuntimeException('No fue posible preparar la reprogramacion.');
        }
        $stmtUpd->bind_param('sii', $fechaSql, $estatusReprogramado, $citaId);
        if (!$stmtUpd->execute()) {
            $stmtUpd->close();
            throw new RuntimeException('No fue posible reprogramar la cita.');
        }
        $stmtUpd->close();

        $stmtHist = $conn->prepare('INSERT INTO HistorialEstatus (id, fecha, idEstatus, idCita, idUsuario) VALUES (NULL, ?, ?, ?, ?)');
        if ($stmtHist) {
            $stmtHist->bind_param('siii', $fechaActual, $estatusReprogramado, $citaId, $usuarioId);
            $stmtHist->execute();
            $stmtHist->close();
        }

        registrarLog(
            $conn,
            $usuarioId,
            'diagnostico',
            'reprogramar',
            sprintf('Se reprogramo la cita #%d del diagnostico #%d para %s.', $citaId, (int) $diagnosticoId, $fechaSql),
            'Cita',
            (string) $citaId
        );

        $conn->commit();
        $conn->close();
        responder(200, ['success' => true]);
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->close();
        responder(400, ['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($accion === 'pagar') {
    $diagnosticoId = isset($_POST['diagnostico_id']) ? (int) $_POST['diagnostico_id'] : 0;
    $metodo = isset($_POST['metodo']) ? trim((string) $_POST['metodo']) : '';
    $monto = isset($_POST['monto']) ? (float) $_POST['monto'] : 0.0;

    if ($diagnosticoId <= 0 || !is_finite($monto) || $monto <= 0 || $metodo === '' || !validarMetodo($metodo)) {
        $conn->close();
        responder(422, ['success' => false, 'message' => 'Datos invalidos para registrar el pago.']);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT saldo_restante, sesiones_total, sesiones_completadas FROM Diagnosticos WHERE id = ? FOR UPDATE');
        if (!$stmt) {
            throw new RuntimeException('No fue posible preparar la consulta del diagnostico.');
        }
        $stmt->bind_param('i', $diagnosticoId);
        $stmt->execute();
        $stmt->bind_result($saldoRestante, $sesionesTotal, $sesionesCompletadas);
        if (!$stmt->fetch()) {
            $stmt->close();
            throw new RuntimeException('Diagnostico no encontrado.');
        }
        $stmt->close();

        $saldoRestante = (float) $saldoRestante;
        if ($saldoRestante <= 0.009) {
            throw new RuntimeException('Este diagnostico ya no tiene adeudo.');
        }

        $montoAplicado = min($monto, $saldoRestante);
        $nuevoRestante = max(0.0, $saldoRestante - $montoAplicado);

        $stmtPago = $conn->prepare('INSERT INTO DiagnosticoPagos (diagnostico_id, metodo, monto, registrado_por) VALUES (?, ?, ?, ?)');
        if (!$stmtPago) {
            throw new RuntimeException('No fue posible preparar el pago.');
        }
        $stmtPago->bind_param('isdi', $diagnosticoId, $metodo, $montoAplicado, $usuarioId);
        if (!$stmtPago->execute()) {
            $stmtPago->close();
            throw new RuntimeException('No fue posible guardar el pago.');
        }
        $stmtPago->close();

        $sesionesTotal = (int) $sesionesTotal;
        $sesionesCompletadas = (int) $sesionesCompletadas;

        $estatusNuevo = 2;
        if ($sesionesCompletadas >= $sesionesTotal) {
            $estatusNuevo = $nuevoRestante > 0.009 ? 5 : 6;
        }

        $stmtUpd = $conn->prepare('UPDATE Diagnosticos SET saldo_restante = ?, estatus_id = ? WHERE id = ?');
        if (!$stmtUpd) {
            throw new RuntimeException('No fue posible actualizar el diagnostico.');
        }
        $stmtUpd->bind_param('dii', $nuevoRestante, $estatusNuevo, $diagnosticoId);
        if (!$stmtUpd->execute()) {
            $stmtUpd->close();
            throw new RuntimeException('No fue posible actualizar el diagnostico.');
        }
        $stmtUpd->close();

        registrarLog(
            $conn,
            $usuarioId,
            'diagnostico',
            'pagar',
            sprintf('Se registro un pago de %.2f (%s) al diagnostico #%d. Restante: %.2f.', $montoAplicado, $metodo, $diagnosticoId, $nuevoRestante),
            'Diagnosticos',
            (string) $diagnosticoId
        );

        $conn->commit();
        $conn->close();
        responder(200, ['success' => true]);
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->close();
        responder(400, ['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($accion === 'agendar') {
    $diagnosticoId = isset($_POST['diagnostico_id']) ? (int) $_POST['diagnostico_id'] : 0;
    $fechaSql = isset($_POST['fecha']) ? parseFechaLocal((string) $_POST['fecha']) : null;
    if ($diagnosticoId <= 0 || $fechaSql === null) {
        $conn->close();
        responder(422, ['success' => false, 'message' => 'Datos invalidos para agendar.']);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT nino_id, psicologo_id, sesiones_total, sesiones_completadas FROM Diagnosticos WHERE id = ? FOR UPDATE');
        if (!$stmt) {
            throw new RuntimeException('No fue posible preparar la consulta del diagnostico.');
        }
        $stmt->bind_param('i', $diagnosticoId);
        $stmt->execute();
        $stmt->bind_result($ninoId, $psicologoId, $sesionesTotal, $sesionesCompletadas);
        if (!$stmt->fetch()) {
            $stmt->close();
            throw new RuntimeException('Diagnostico no encontrado.');
        }
        $stmt->close();

        $ninoId = (int) $ninoId;
        $psicologoId = $psicologoId !== null ? (int) $psicologoId : 0;
        $sesionesTotal = (int) $sesionesTotal;
        $sesionesCompletadas = (int) $sesionesCompletadas;

        if ($psicologoId <= 0) {
            throw new RuntimeException('El diagnostico no tiene psicologo asignado.');
        }

        $siguienteSesion = $sesionesCompletadas + 1;
        if ($siguienteSesion > $sesionesTotal) {
            throw new RuntimeException('Este diagnostico ya no tiene sesiones pendientes.');
        }

        $checkSesion = $conn->prepare('SELECT COUNT(*) FROM Cita WHERE diagnostico_id = ? AND diagnostico_sesion = ? AND Estatus IN (2, 3)');
        if ($checkSesion) {
            $checkSesion->bind_param('ii', $diagnosticoId, $siguienteSesion);
            $checkSesion->execute();
            $checkSesion->bind_result($totalSesion);
            $checkSesion->fetch();
            $checkSesion->close();
            if ((int) $totalSesion > 0) {
                throw new RuntimeException('Ya existe una cita programada para la siguiente sesion.');
            }
        }

        // Evitar duplicados.
        $check = $conn->prepare('SELECT COUNT(*) FROM Cita WHERE IdNino = ? AND Programado = ?');
        if (!$check) {
            throw new RuntimeException('No fue posible validar la disponibilidad.');
        }
        $check->bind_param('is', $ninoId, $fechaSql);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();
        if ((int) $count > 0) {
            throw new RuntimeException('Ya existe una cita registrada para este paciente en esa fecha y hora.');
        }

        $estatusCita = 2;
        $tipoCita = 'Diagnostico';
        $costo = 0.0;
        $formaPago = 'Diagnostico';

        $sqlCita = 'INSERT INTO Cita (IdNino, IdUsuario, idGenerado, fecha, costo, Programado, Estatus, Tipo, paquete_id, diagnostico_id, diagnostico_sesion, FormaPago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)';
        $stmtCita = $conn->prepare($sqlCita);
        if (!$stmtCita) {
            throw new RuntimeException('No fue posible preparar la cita.');
        }
        $stmtCita->bind_param('iiisdsisiss', $ninoId, $psicologoId, $usuarioId, $fechaActual, $costo, $fechaSql, $estatusCita, $tipoCita, $diagnosticoId, $siguienteSesion, $formaPago);
        if (!$stmtCita->execute()) {
            $stmtCita->close();
            throw new RuntimeException('No fue posible guardar la cita.');
        }
        $citaId = (int) $conn->insert_id;
        $stmtCita->close();

        registrarLog(
            $conn,
            $usuarioId,
            'diagnostico',
            'agendar',
            sprintf('Se agendo la cita #%d (sesion %d) para el diagnostico #%d en %s.', $citaId, $siguienteSesion, $diagnosticoId, $fechaSql),
            'Diagnosticos',
            (string) $diagnosticoId
        );

        $conn->commit();
        $conn->close();
        responder(200, ['success' => true]);
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->close();
        responder(400, ['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($accion === 'finalizar') {
    $citaId = isset($_POST['cita_id']) ? (int) $_POST['cita_id'] : 0;
    $pagoMonto = isset($_POST['pago_monto']) ? (float) $_POST['pago_monto'] : 0.0;
    $pagoMetodo = isset($_POST['pago_metodo']) ? trim((string) $_POST['pago_metodo']) : '';
    $proximaFechaSql = isset($_POST['proxima_fecha']) ? parseFechaLocal((string) $_POST['proxima_fecha']) : null;

    if ($citaId <= 0) {
        $conn->close();
        responder(422, ['success' => false, 'message' => 'Cita no valida.']);
    }

    if (!is_finite($pagoMonto) || $pagoMonto < 0) {
        $conn->close();
        responder(422, ['success' => false, 'message' => 'El pago no es valido.']);
    }

    if ($pagoMonto > 0.009) {
        if ($pagoMetodo === '' || !validarMetodo($pagoMetodo)) {
            $conn->close();
            responder(422, ['success' => false, 'message' => 'Selecciona un metodo valido para el pago.']);
        }
    } else {
        $pagoMonto = 0.0;
        $pagoMetodo = '';
    }

    $conn->begin_transaction();
    try {
        $stmtCita = $conn->prepare('SELECT diagnostico_id, diagnostico_sesion, IdNino, IdUsuario, Estatus FROM Cita WHERE id = ? FOR UPDATE');
        if (!$stmtCita) {
            throw new RuntimeException('No fue posible preparar la consulta de la cita.');
        }
        $stmtCita->bind_param('i', $citaId);
        $stmtCita->execute();
        $stmtCita->bind_result($diagnosticoId, $sesionNumero, $ninoId, $psicologoId, $estatusActual);
        if (!$stmtCita->fetch()) {
            $stmtCita->close();
            throw new RuntimeException('Cita no encontrada.');
        }
        $stmtCita->close();

        if ($diagnosticoId === null) {
            throw new RuntimeException('La cita no pertenece a un diagnostico.');
        }
        $diagnosticoId = (int) $diagnosticoId;
        $sesionNumero = (int) $sesionNumero;

        if ((int) $estatusActual === 4) {
            throw new RuntimeException('Esta sesion ya esta finalizada.');
        }

        $stmtDiag = $conn->prepare('SELECT sesiones_total, sesiones_completadas, saldo_restante, estatus_id FROM Diagnosticos WHERE id = ? FOR UPDATE');
        if (!$stmtDiag) {
            throw new RuntimeException('No fue posible consultar el diagnostico.');
        }
        $stmtDiag->bind_param('i', $diagnosticoId);
        $stmtDiag->execute();
        $stmtDiag->bind_result($sesionesTotal, $sesionesCompletadas, $saldoRestante, $estatusDiagnostico);
        if (!$stmtDiag->fetch()) {
            $stmtDiag->close();
            throw new RuntimeException('Diagnostico no encontrado.');
        }
        $stmtDiag->close();

        $sesionesTotal = (int) $sesionesTotal;
        $sesionesCompletadas = (int) $sesionesCompletadas;
        $saldoRestante = (float) $saldoRestante;

        $estatusFinalizada = 4;
        $stmtUpdCita = $conn->prepare('UPDATE Cita SET Estatus = ? WHERE id = ?');
        if (!$stmtUpdCita) {
            throw new RuntimeException('No fue posible finalizar la cita.');
        }
        $stmtUpdCita->bind_param('ii', $estatusFinalizada, $citaId);
        if (!$stmtUpdCita->execute()) {
            $stmtUpdCita->close();
            throw new RuntimeException('No fue posible finalizar la cita.');
        }
        $stmtUpdCita->close();

        $stmtHist = $conn->prepare('INSERT INTO HistorialEstatus (id, fecha, idEstatus, idCita, idUsuario) VALUES (NULL, ?, ?, ?, ?)');
        if ($stmtHist) {
            $stmtHist->bind_param('siii', $fechaActual, $estatusFinalizada, $citaId, $usuarioId);
            $stmtHist->execute();
            $stmtHist->close();
        }

        $nuevoCompletadas = max($sesionesCompletadas, $sesionNumero);
        if ($nuevoCompletadas > $sesionesTotal) {
            $nuevoCompletadas = $sesionesTotal;
        }

        if ($pagoMonto > 0.009 && $saldoRestante > 0.009) {
            $montoAplicado = min($pagoMonto, $saldoRestante);
            $stmtPago = $conn->prepare('INSERT INTO DiagnosticoPagos (diagnostico_id, metodo, monto, registrado_por) VALUES (?, ?, ?, ?)');
            if (!$stmtPago) {
                throw new RuntimeException('No fue posible registrar el pago.');
            }
            $stmtPago->bind_param('isdi', $diagnosticoId, $pagoMetodo, $montoAplicado, $usuarioId);
            if (!$stmtPago->execute()) {
                $stmtPago->close();
                throw new RuntimeException('No fue posible registrar el pago.');
            }
            $stmtPago->close();
            $saldoRestante = max(0.0, $saldoRestante - $montoAplicado);
        }

        // Crear proxima cita si faltan sesiones.
        $creoProxima = false;
        if ($nuevoCompletadas < $sesionesTotal) {
            if ($proximaFechaSql === null) {
                throw new RuntimeException('Ingresa la proxima cita para continuar con el diagnostico.');
            }

            $siguienteSesion = $nuevoCompletadas + 1;

            $checkSesion = $conn->prepare('SELECT COUNT(*) FROM Cita WHERE diagnostico_id = ? AND diagnostico_sesion = ? AND Estatus IN (2, 3)');
            if ($checkSesion) {
                $checkSesion->bind_param('ii', $diagnosticoId, $siguienteSesion);
                $checkSesion->execute();
                $checkSesion->bind_result($totalSesion);
                $checkSesion->fetch();
                $checkSesion->close();
                if ((int) $totalSesion > 0) {
                    throw new RuntimeException('Ya existe una cita programada para la siguiente sesion.');
                }
            }

            $check = $conn->prepare('SELECT COUNT(*) FROM Cita WHERE IdNino = ? AND Programado = ?');
            if (!$check) {
                throw new RuntimeException('No fue posible validar la disponibilidad.');
            }
            $check->bind_param('is', $ninoId, $proximaFechaSql);
            $check->execute();
            $check->bind_result($count);
            $check->fetch();
            $check->close();
            if ((int) $count > 0) {
                throw new RuntimeException('Ya existe una cita registrada para este paciente en esa fecha y hora.');
            }

            $estatusCita = 2;
            $tipoCita = 'Diagnostico';
            $costo = 0.0;
            $formaPago = 'Diagnostico';

            $sqlCita = 'INSERT INTO Cita (IdNino, IdUsuario, idGenerado, fecha, costo, Programado, Estatus, Tipo, paquete_id, diagnostico_id, diagnostico_sesion, FormaPago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)';
            $stmtNueva = $conn->prepare($sqlCita);
            if (!$stmtNueva) {
                throw new RuntimeException('No fue posible preparar la proxima cita.');
            }
            $stmtNueva->bind_param('iiisdsisiss', $ninoId, $psicologoId, $usuarioId, $fechaActual, $costo, $proximaFechaSql, $estatusCita, $tipoCita, $diagnosticoId, $siguienteSesion, $formaPago);
            if (!$stmtNueva->execute()) {
                $stmtNueva->close();
                throw new RuntimeException('No fue posible guardar la proxima cita.');
            }
            $stmtNueva->close();
            $creoProxima = true;
        }

        $estatusNuevo = 2;
        if ($nuevoCompletadas >= $sesionesTotal) {
            $estatusNuevo = $saldoRestante > 0.009 ? 5 : 6;
        }

        $stmtUpdDiag = $conn->prepare('UPDATE Diagnosticos SET sesiones_completadas = ?, saldo_restante = ?, estatus_id = ? WHERE id = ?');
        if (!$stmtUpdDiag) {
            throw new RuntimeException('No fue posible actualizar el diagnostico.');
        }
        $stmtUpdDiag->bind_param('idii', $nuevoCompletadas, $saldoRestante, $estatusNuevo, $diagnosticoId);
        if (!$stmtUpdDiag->execute()) {
            $stmtUpdDiag->close();
            throw new RuntimeException('No fue posible actualizar el diagnostico.');
        }
        $stmtUpdDiag->close();

        registrarLog(
            $conn,
            $usuarioId,
            'diagnostico',
            'finalizar',
            sprintf('Se finalizo la sesion %d/%d (cita #%d) del diagnostico #%d. Restante: %.2f. Proxima: %s.', $sesionNumero, $sesionesTotal, $citaId, $diagnosticoId, $saldoRestante, $creoProxima ? 'si' : 'no'),
            'Diagnosticos',
            (string) $diagnosticoId
        );

        $conn->commit();
        $conn->close();
        responder(200, ['success' => true]);
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->close();
        responder(400, ['success' => false, 'message' => $e->getMessage()]);
    }
}

$conn->close();
responder(400, ['success' => false, 'message' => 'Accion no valida.']);
