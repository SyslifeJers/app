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

if ($metodoHttp === 'GET') {
    $sql = "SELECT
                ad.id,
                ad.nino_id,
                n.name AS paciente_nombre,
                ad.psicologo_id,
                u.name AS psicologo_nombre,
                ad.total,
                ad.saldo_restante,
                ad.estatus_id,
                es.name AS estatus_nombre,
                ad.creado_en,
                (SELECT COALESCE(SUM(p.monto), 0) FROM AdeudosDiagnosticoPagos p WHERE p.adeudo_id = ad.id) AS total_pagado
            FROM AdeudosDiagnostico ad
            INNER JOIN nino n ON n.id = ad.nino_id
            LEFT JOIN Usuarios u ON u.id = ad.psicologo_id
            LEFT JOIN Estatus es ON es.id = ad.estatus_id
            ORDER BY ad.creado_en DESC
            LIMIT 500";

    $result = $conn->query($sql);
    if (!$result) {
        $conn->close();
        responder(500, ['success' => false, 'message' => 'No fue posible consultar adeudos.']);
    }

    $rows = [];
    while ($fila = $result->fetch_assoc()) {
        $rows[] = $fila;
    }
    $result->free();
    $conn->close();

    responder(200, ['success' => true, 'data' => $rows]);
}

if ($metodoHttp !== 'POST') {
    $conn->close();
    responder(405, ['success' => false, 'message' => 'Metodo no permitido.']);
}

$accion = isset($_POST['accion']) ? trim((string) $_POST['accion']) : '';
$usuarioId = (int) $_SESSION['id'];

if ($accion === 'registrar_pago') {
    $adeudoId = isset($_POST['adeudo_id']) ? (int) $_POST['adeudo_id'] : 0;
    $metodoPago = isset($_POST['metodo']) ? trim((string) $_POST['metodo']) : '';
    $monto = isset($_POST['monto']) ? (float) $_POST['monto'] : 0.0;

    if ($adeudoId <= 0 || $metodoPago === '' || !is_finite($monto) || $monto <= 0) {
        $conn->close();
        responder(422, ['success' => false, 'message' => 'Parametros invalidos para registrar el pago.']);
    }

    $metodosPermitidos = ['Efectivo', 'Transferencia', 'Tarjeta'];
    if (!in_array($metodoPago, $metodosPermitidos, true)) {
        $conn->close();
        responder(422, ['success' => false, 'message' => 'Selecciona un metodo de pago valido.']);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT nino_id, saldo_restante FROM AdeudosDiagnostico WHERE id = ? FOR UPDATE');
        if (!$stmt) {
            throw new RuntimeException('No fue posible preparar la consulta del adeudo.');
        }
        $stmt->bind_param('i', $adeudoId);
        $stmt->execute();
        $stmt->bind_result($ninoId, $saldoRestante);
        if (!$stmt->fetch()) {
            $stmt->close();
            throw new RuntimeException('Adeudo no encontrado.');
        }
        $stmt->close();

        $saldoRestante = (float) $saldoRestante;
        if ($saldoRestante <= 0.009) {
            throw new RuntimeException('Este adeudo ya esta cubierto.');
        }

        $montoAplicado = min($monto, $saldoRestante);
        $nuevoRestante = max(0.0, $saldoRestante - $montoAplicado);
        $nuevoEstatus = $nuevoRestante > 0.009 ? 5 : 6;

        $stmtPago = $conn->prepare('INSERT INTO AdeudosDiagnosticoPagos (adeudo_id, metodo, monto, registrado_por) VALUES (?, ?, ?, ?)');
        if (!$stmtPago) {
            throw new RuntimeException('No fue posible preparar el pago.');
        }
        $stmtPago->bind_param('isdi', $adeudoId, $metodoPago, $montoAplicado, $usuarioId);
        if (!$stmtPago->execute()) {
            $stmtPago->close();
            throw new RuntimeException('No fue posible guardar el pago.');
        }
        $stmtPago->close();

        $stmtUpdate = $conn->prepare('UPDATE AdeudosDiagnostico SET saldo_restante = ?, estatus_id = ? WHERE id = ?');
        if (!$stmtUpdate) {
            throw new RuntimeException('No fue posible actualizar el adeudo.');
        }
        $stmtUpdate->bind_param('dii', $nuevoRestante, $nuevoEstatus, $adeudoId);
        if (!$stmtUpdate->execute()) {
            $stmtUpdate->close();
            throw new RuntimeException('No fue posible actualizar el adeudo.');
        }
        $stmtUpdate->close();

        registrarLog(
            $conn,
            $usuarioId,
            'adeudos',
            'registrar_pago',
            sprintf('Se registro un pago de %.2f (%s) al adeudo de diagnostico #%d. Restante: %.2f.', $montoAplicado, $metodoPago, $adeudoId, $nuevoRestante),
            'AdeudoDiagnostico',
            (string) $adeudoId
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

if ($accion === 'agendar_cita') {
    $adeudoId = isset($_POST['adeudo_id']) ? (int) $_POST['adeudo_id'] : 0;
    $fecha = isset($_POST['fecha']) ? trim((string) $_POST['fecha']) : '';
    $psicologoId = isset($_POST['psicologo_id']) ? (int) $_POST['psicologo_id'] : 0;

    if ($adeudoId <= 0 || $psicologoId <= 0 || $fecha === '') {
        $conn->close();
        responder(422, ['success' => false, 'message' => 'Parametros invalidos para agendar la cita.']);
    }

    $fechaDt = DateTime::createFromFormat('Y-m-d\TH:i', $fecha) ?: DateTime::createFromFormat('Y-m-d H:i:s', $fecha);
    if (!$fechaDt) {
        $conn->close();
        responder(422, ['success' => false, 'message' => 'La fecha no es valida.']);
    }
    $fechaSql = $fechaDt->format('Y-m-d H:i:s');

    $conn->begin_transaction();
    try {
        $stmtAdeudo = $conn->prepare('SELECT nino_id FROM AdeudosDiagnostico WHERE id = ? FOR UPDATE');
        if (!$stmtAdeudo) {
            throw new RuntimeException('No fue posible preparar la consulta del adeudo.');
        }
        $stmtAdeudo->bind_param('i', $adeudoId);
        $stmtAdeudo->execute();
        $stmtAdeudo->bind_result($ninoId);
        if (!$stmtAdeudo->fetch()) {
            $stmtAdeudo->close();
            throw new RuntimeException('Adeudo no encontrado.');
        }
        $stmtAdeudo->close();

        $estatusCreada = 2;
        $fechaActual = date('Y-m-d H:i:s');
        $costo = 0.0;
        $tipo = 'Diagnostico';

        $stmtCita = $conn->prepare('INSERT INTO Cita (IdNino, IdUsuario, idGenerado, fecha, costo, Programado, Estatus, Tipo, paquete_id, adeudo_diagnostico_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)');
        if (!$stmtCita) {
            throw new RuntimeException('No fue posible preparar la cita.');
        }
        $stmtCita->bind_param('iiisdsisi', $ninoId, $psicologoId, $usuarioId, $fechaActual, $costo, $fechaSql, $estatusCreada, $tipo, $adeudoId);
        if (!$stmtCita->execute()) {
            $stmtCita->close();
            throw new RuntimeException('No fue posible guardar la cita.');
        }
        $citaId = (int) $conn->insert_id;
        $stmtCita->close();

        registrarLog(
            $conn,
            $usuarioId,
            'adeudos',
            'agendar_cita',
            sprintf('Se agendo la cita #%d para el adeudo de diagnostico #%d en %s.', $citaId, $adeudoId, $fechaSql),
            'AdeudoDiagnostico',
            (string) $adeudoId
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
