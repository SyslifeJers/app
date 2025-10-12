<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['id'])) {
    $_SESSION['solicitud_saldo_mensaje'] = 'No autenticado.';
    $_SESSION['solicitud_saldo_tipo'] = 'danger';
    header('Location: solicitudes_saldo.php');
    exit;
}

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
if (!in_array($rolUsuario, [3, 5], true)) {
    $_SESSION['solicitud_saldo_mensaje'] = 'No tienes permisos para procesar solicitudes de ajuste.';
    $_SESSION['solicitud_saldo_tipo'] = 'danger';
    header('Location: solicitudes_saldo.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['solicitud_saldo_mensaje'] = 'Método no permitido.';
    $_SESSION['solicitud_saldo_tipo'] = 'danger';
    header('Location: solicitudes_saldo.php');
    exit;
}

$solicitudId = isset($_POST['solicitud_id'])
    ? filter_var($_POST['solicitud_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : null;
$accion = isset($_POST['accion']) ? trim((string) $_POST['accion']) : '';
$comentario = isset($_POST['comentario']) ? trim((string) $_POST['comentario']) : '';

if ($solicitudId === null || !in_array($accion, ['aprobar', 'rechazar'], true)) {
    $_SESSION['solicitud_saldo_mensaje'] = 'Parámetros incompletos.';
    $_SESSION['solicitud_saldo_tipo'] = 'danger';
    header('Location: solicitudes_saldo.php');
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/saldo_pacientes.php';
require_once __DIR__ . '/../Modulos/logger.php';

$conn = conectar();
if (!$conn) {
    $_SESSION['solicitud_saldo_mensaje'] = 'No fue posible conectar con la base de datos.';
    $_SESSION['solicitud_saldo_tipo'] = 'danger';
    header('Location: solicitudes_saldo.php');
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        'SELECT s.id, s.nino_id, s.solicitado_por, s.monto, s.saldo_anterior, s.saldo_solicitado, s.comentario, s.estatus, s.fecha_solicitud, n.name AS paciente_nombre, n.saldo_paquete AS saldo_actual, c.name AS tutor_nombre
         FROM SolicitudAjusteSaldo s
         INNER JOIN nino n ON n.id = s.nino_id
         LEFT JOIN Clientes c ON c.id = n.idtutor
         WHERE s.id = ? FOR UPDATE'
    );

    if ($stmt === false) {
        throw new RuntimeException('No fue posible preparar la consulta de la solicitud.');
    }

    $stmt->bind_param('i', $solicitudId);

    if (!$stmt->execute()) {
        throw new RuntimeException('No fue posible obtener la solicitud especificada.');
    }

    $resultado = $stmt->get_result();
    $solicitud = $resultado ? $resultado->fetch_assoc() : null;
    $stmt->close();

    if (!$solicitud) {
        throw new RuntimeException('No se encontró la solicitud solicitada.');
    }

    if ($solicitud['estatus'] !== 'pendiente') {
        throw new RuntimeException('La solicitud ya fue procesada previamente.');
    }

    $usuarioId = (int) $_SESSION['id'];
    $pacienteId = (int) $solicitud['nino_id'];
    $saldoDeseado = (float) $solicitud['saldo_solicitado'];
    $saldoActual = (float) $solicitud['saldo_actual'];
    $diferencia = $saldoDeseado - $saldoActual;

    if ($accion === 'aprobar') {
        if (abs($diferencia) >= 0.01) {
            if (!ajustarSaldoPaciente($conn, $pacienteId, $diferencia)) {
                throw new RuntimeException('No fue posible ajustar el saldo del paciente.');
            }
        }

        $stmtActualizar = $conn->prepare(
            "UPDATE SolicitudAjusteSaldo SET estatus = 'aprobada', aprobado_por = ?, fecha_resolucion = NOW(), respuesta = ? WHERE id = ?"
        );

        if ($stmtActualizar === false) {
            throw new RuntimeException('No fue posible actualizar la solicitud.');
        }

        $stmtActualizar->bind_param('isi', $usuarioId, $comentario, $solicitudId);

        if (!$stmtActualizar->execute()) {
            throw new RuntimeException('No fue posible guardar la resolución.');
        }

        $stmtActualizar->close();

        $descripcion = sprintf(
            'Se aprobó la solicitud #%d para ajustar el saldo del paciente %s (tutor: %s). Saldo anterior: %s. Saldo solicitado: %s. Comentario final: %s',
            $solicitudId,
            $solicitud['paciente_nombre'],
            $solicitud['tutor_nombre'] ?? 'Sin tutor',
            number_format((float) $solicitud['saldo_anterior'], 2),
            number_format($saldoDeseado, 2),
            $comentario !== '' ? $comentario : 'Sin comentarios'
        );

        registrarLog(
            $conn,
            $usuarioId,
            'pacientes',
            'aprobar_ajuste_saldo',
            $descripcion,
            'Paciente',
            (string) $pacienteId
        );

        $_SESSION['solicitud_saldo_mensaje'] = 'Solicitud aprobada y saldo actualizado correctamente.';
        $_SESSION['solicitud_saldo_tipo'] = 'success';
    } else {
        $stmtRechazar = $conn->prepare(
            "UPDATE SolicitudAjusteSaldo SET estatus = 'rechazada', aprobado_por = ?, fecha_resolucion = NOW(), respuesta = ? WHERE id = ?"
        );

        if ($stmtRechazar === false) {
            throw new RuntimeException('No fue posible actualizar la solicitud.');
        }

        $stmtRechazar->bind_param('isi', $usuarioId, $comentario, $solicitudId);

        if (!$stmtRechazar->execute()) {
            throw new RuntimeException('No fue posible registrar el rechazo.');
        }

        $stmtRechazar->close();

        $descripcion = sprintf(
            'Se rechazó la solicitud #%d para ajustar el saldo del paciente %s. Comentario final: %s',
            $solicitudId,
            $solicitud['paciente_nombre'],
            $comentario !== '' ? $comentario : 'Sin comentarios'
        );

        registrarLog(
            $conn,
            $usuarioId,
            'pacientes',
            'rechazar_ajuste_saldo',
            $descripcion,
            'Paciente',
            (string) $pacienteId
        );

        $_SESSION['solicitud_saldo_mensaje'] = 'Solicitud rechazada correctamente.';
        $_SESSION['solicitud_saldo_tipo'] = 'info';
    }

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    $_SESSION['solicitud_saldo_mensaje'] = 'Ocurrió un error al procesar la solicitud: ' . $exception->getMessage();
    $_SESSION['solicitud_saldo_tipo'] = 'danger';
} finally {
    $conn->close();
}

header('Location: solicitudes_saldo.php');
exit;
