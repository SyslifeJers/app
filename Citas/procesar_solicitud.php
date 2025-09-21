<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once '../conexion.php';
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['token'])) {
    header('Location: /login.php');
    exit;
}

$rolUsuario = $_SESSION['rol'] ?? 0;
if (!in_array($rolUsuario, [3, 4], true)) {
    $_SESSION['solicitud_mensaje'] = 'No tienes permisos para procesar solicitudes.';
    $_SESSION['solicitud_tipo'] = 'danger';
    header('Location: solicitudes.php');
    exit;
}

$conn = conectar();

date_default_timezone_set('America/Mexico_City');
$fechaActual = date('Y-m-d H:i:s');
$idUsuario = $_SESSION['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['solicitud_mensaje'] = 'Solicitud inválida.';
    $_SESSION['solicitud_tipo'] = 'danger';
    header('Location: solicitudes.php');
    exit;
}

$solicitudId = isset($_POST['solicitud_id']) ? (int) $_POST['solicitud_id'] : 0;
$action = $_POST['action'] ?? '';
$comentarios = trim($_POST['comentarios'] ?? '');

if ($solicitudId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    $_SESSION['solicitud_mensaje'] = 'Parámetros incompletos.';
    $_SESSION['solicitud_tipo'] = 'danger';
    header('Location: solicitudes.php');
    exit;
}

$stmtSolicitud = $conn->prepare('SELECT cita_id, nueva_fecha, estatus FROM SolicitudReprogramacion WHERE id = ?');
$stmtSolicitud->bind_param('i', $solicitudId);
$stmtSolicitud->execute();
$stmtSolicitud->bind_result($citaId, $nuevaFecha, $estatusActual);

if (!$stmtSolicitud->fetch()) {
    $stmtSolicitud->close();
    $_SESSION['solicitud_mensaje'] = 'No se encontró la solicitud especificada.';
    $_SESSION['solicitud_tipo'] = 'danger';
    header('Location: solicitudes.php');
    exit;
}
$stmtSolicitud->close();

if ($estatusActual !== 'pendiente') {
    $_SESSION['solicitud_mensaje'] = 'La solicitud ya fue atendida.';
    $_SESSION['solicitud_tipo'] = 'warning';
    header('Location: solicitudes.php');
    exit;
}

$conn->begin_transaction();

try {
    if ($action === 'approve') {
        $stmtUpdate = $conn->prepare('UPDATE Cita SET Programado = ?, Estatus = 3 WHERE id = ?');
        $stmtUpdate->bind_param('si', $nuevaFecha, $citaId);
        $stmtUpdate->execute();
        if ($stmtUpdate->errno) {
            throw new Exception('No fue posible actualizar la cita.');
        }
        $stmtUpdate->close();

        $stmtHistorial = $conn->prepare('INSERT INTO HistorialEstatus(id, fecha, idEstatus, idCita, idUsuario) VALUES (null, ?, 3, ?, ?)');
        $stmtHistorial->bind_param('sii', $fechaActual, $citaId, $idUsuario);
        $stmtHistorial->execute();
        if ($stmtHistorial->errno) {
            throw new Exception('No se pudo registrar el historial de la cita.');
        }
        $stmtHistorial->close();

        $comentariosFinal = $comentarios !== '' ? $comentarios : '';
        $stmtFinalizar = $conn->prepare("UPDATE SolicitudReprogramacion SET estatus = 'aprobada', aprobado_por = ?, fecha_respuesta = ?, comentarios = ? WHERE id = ?");
        $stmtFinalizar->bind_param('issi', $idUsuario, $fechaActual, $comentariosFinal, $solicitudId);
        $stmtFinalizar->execute();
        if ($stmtFinalizar->errno) {
            throw new Exception('No se pudo actualizar la solicitud.');
        }
        $stmtFinalizar->close();

        $conn->commit();
        $_SESSION['solicitud_mensaje'] = 'Solicitud aprobada y cita reprogramada correctamente.';
        $_SESSION['solicitud_tipo'] = 'success';
    } else {
        $comentariosFinal = $comentarios !== '' ? $comentarios : '';
        $stmtRechazar = $conn->prepare("UPDATE SolicitudReprogramacion SET estatus = 'rechazada', aprobado_por = ?, fecha_respuesta = ?, comentarios = ? WHERE id = ?");
        $stmtRechazar->bind_param('issi', $idUsuario, $fechaActual, $comentariosFinal, $solicitudId);
        $stmtRechazar->execute();
        if ($stmtRechazar->errno) {
            throw new Exception('No se pudo rechazar la solicitud.');
        }
        $stmtRechazar->close();

        $conn->commit();
        $_SESSION['solicitud_mensaje'] = 'Solicitud rechazada correctamente.';
        $_SESSION['solicitud_tipo'] = 'info';
    }
} catch (Exception $exception) {
    $conn->rollback();
    $_SESSION['solicitud_mensaje'] = 'Ocurrió un error al procesar la solicitud: ' . $exception->getMessage();
    $_SESSION['solicitud_tipo'] = 'danger';
}

$conn->close();
header('Location: solicitudes.php');
exit;
