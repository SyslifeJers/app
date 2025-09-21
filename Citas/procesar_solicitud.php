<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once '../conexion.php';
require_once __DIR__ . '/../Modulos/logger.php';
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

$stmtSolicitud = $conn->prepare('SELECT cita_id, nueva_fecha, estatus, tipo FROM SolicitudReprogramacion WHERE id = ?');
$stmtSolicitud->bind_param('i', $solicitudId);
$stmtSolicitud->execute();
$stmtSolicitud->bind_result($citaId, $nuevaFecha, $estatusActual, $tipoSolicitud);

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
    if ($tipoSolicitud === 'cancelacion') {
        if ($action === 'approve') {
            $stmtUpdate = $conn->prepare('UPDATE Cita SET Estatus = 1 WHERE id = ?');
            $stmtUpdate->bind_param('i', $citaId);
            $stmtUpdate->execute();
            if ($stmtUpdate->errno) {
                throw new Exception('No fue posible cancelar la cita.');
            }
            $stmtUpdate->close();

            $stmtHistorial = $conn->prepare('INSERT INTO HistorialEstatus(id, fecha, idEstatus, idCita, idUsuario) VALUES (null, ?, 1, ?, ?)');
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
            registrarLog(
                $conn,
                $idUsuario,
                'citas',
                'aprobar_solicitud_cancelacion',
                sprintf('La solicitud #%d de cancelación fue aprobada y la cita #%d se canceló.', $solicitudId, $citaId),
                'SolicitudReprogramacion',
                (string) $solicitudId
            );
            $_SESSION['solicitud_mensaje'] = 'Solicitud de cancelación aprobada y cita cancelada correctamente.';
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
            registrarLog(
                $conn,
                $idUsuario,
                'citas',
                'rechazar_solicitud_cancelacion',
                sprintf('La solicitud #%d de cancelación fue rechazada para la cita #%d.', $solicitudId, $citaId),
                'SolicitudReprogramacion',
                (string) $solicitudId
            );
            $_SESSION['solicitud_mensaje'] = 'Solicitud de cancelación rechazada correctamente.';
            $_SESSION['solicitud_tipo'] = 'info';
        }
    } else {
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
            registrarLog(
                $conn,
                $idUsuario,
                'citas',
                'aprobar_solicitud_reprogramacion',
                sprintf('La solicitud #%d de reprogramación fue aprobada y la cita #%d se reprogramó a %s.', $solicitudId, $citaId, $nuevaFecha),
                'SolicitudReprogramacion',
                (string) $solicitudId
            );
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
            registrarLog(
                $conn,
                $idUsuario,
                'citas',
                'rechazar_solicitud_reprogramacion',
                sprintf('La solicitud #%d de reprogramación fue rechazada para la cita #%d.', $solicitudId, $citaId),
                'SolicitudReprogramacion',
                (string) $solicitudId
            );
            $_SESSION['solicitud_mensaje'] = 'Solicitud rechazada correctamente.';
            $_SESSION['solicitud_tipo'] = 'info';
        }
    }
} catch (Exception $exception) {
    $conn->rollback();
    $_SESSION['solicitud_mensaje'] = 'Ocurrió un error al procesar la solicitud: ' . $exception->getMessage();
    $_SESSION['solicitud_tipo'] = 'danger';
}

$conn->close();
$destino = 'solicitudes.php';
if ($tipoSolicitud === 'cancelacion') {
    $destino .= '?tipo=cancelacion';
}
header('Location: ' . $destino);
exit;
