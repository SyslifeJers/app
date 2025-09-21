<?php
// Datos de la conexión
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once 'conexion.php';
$conn = conectar();
session_start();

date_default_timezone_set('America/Mexico_City');
$fechaActual = date('Y-m-d H:i:s'); // Formato de fecha y hora actual

$idUsuario = $_SESSION['id'] ?? null;
$rolUsuario = $_SESSION['rol'] ?? null;

$ROL_VENTAS = 1;
$ROL_ADMIN = 3;
$ROL_COORDINADOR = 4;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $citaId = isset($_POST['citaId']) ? (int) $_POST['citaId'] : 0;
    $estatus = isset($_POST['estatus']) ? (int) $_POST['estatus'] : 0;
    $formaPago = $_POST['formaPago'] ?? null;

    if ($citaId <= 0 || $estatus <= 0) {
        header('Location: index.php');
        exit;
    }

    if ($rolUsuario === $ROL_VENTAS) {
        $tablaCancelaciones = $conn->query("SHOW TABLES LIKE 'SolicitudCancelacion'");
        if (!($tablaCancelaciones instanceof mysqli_result) || $tablaCancelaciones->num_rows === 0) {
            if ($tablaCancelaciones instanceof mysqli_result) {
                $tablaCancelaciones->free();
            }
            $_SESSION['cancelacion_mensaje'] = 'El módulo de solicitudes de cancelación no está disponible. Contacta al administrador.';
            $_SESSION['cancelacion_tipo'] = 'danger';
            header('Location: index.php');
            exit;
        }
        $tablaCancelaciones->free();

        $totalPendientes = 0;
        if ($stmtPendiente = $conn->prepare("SELECT COUNT(*) FROM SolicitudCancelacion WHERE cita_id = ? AND estatus = 'pendiente'")) {
            $stmtPendiente->bind_param('i', $citaId);
            $stmtPendiente->execute();
            $stmtPendiente->bind_result($totalPendientes);
            $stmtPendiente->fetch();
            $stmtPendiente->close();
        }

        if ($totalPendientes > 0) {
            $_SESSION['cancelacion_mensaje'] = 'Ya existe una solicitud de cancelación pendiente para esta cita.';
            $_SESSION['cancelacion_tipo'] = 'warning';
            header('Location: index.php');
            exit;
        }

        if ($stmtSolicitud = $conn->prepare("INSERT INTO SolicitudCancelacion (cita_id, estatus, solicitado_por, fecha_solicitud) VALUES (?, 'pendiente', ?, ?)")) {
            $stmtSolicitud->bind_param('iis', $citaId, $idUsuario, $fechaActual);
            $stmtSolicitud->execute();
            $stmtSolicitud->close();
            $_SESSION['cancelacion_mensaje'] = 'Se envió la solicitud de cancelación para aprobación.';
            $_SESSION['cancelacion_tipo'] = 'success';
        } else {
            $_SESSION['cancelacion_mensaje'] = 'No fue posible registrar la solicitud de cancelación. Intenta nuevamente.';
            $_SESSION['cancelacion_tipo'] = 'danger';
        }

        header('Location: index.php');
        exit;
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

    if ($estatus === 1) {
        $tablaSolicitudes = $conn->query("SHOW TABLES LIKE 'SolicitudReprogramacion'");
        if ($tablaSolicitudes instanceof mysqli_result && $tablaSolicitudes->num_rows > 0) {
            $tablaSolicitudes->free();

            $comentarioCancelacion = 'Solicitud cerrada automáticamente por cancelación de la cita.';
            $stmtCancelar = $conn->prepare("UPDATE SolicitudReprogramacion SET estatus = 'rechazada', aprobado_por = ?, fecha_respuesta = ?, comentarios = CASE WHEN comentarios IS NULL OR comentarios = '' THEN ? ELSE CONCAT(comentarios, '\n', ?) END WHERE cita_id = ? AND estatus = 'pendiente'");
            $stmtCancelar->bind_param('isssi', $idUsuario, $fechaActual, $comentarioCancelacion, $comentarioCancelacion, $citaId);
            $stmtCancelar->execute();
            $stmtCancelar->close();
        } elseif ($tablaSolicitudes instanceof mysqli_result) {
            $tablaSolicitudes->free();
        }

        $tablaSolicitudesCancelacion = $conn->query("SHOW TABLES LIKE 'SolicitudCancelacion'");
        if ($tablaSolicitudesCancelacion instanceof mysqli_result && $tablaSolicitudesCancelacion->num_rows > 0) {
            $tablaSolicitudesCancelacion->free();

            $comentarioAprobacion = 'Solicitud de cancelación aprobada automáticamente al cancelar la cita.';
            if ($stmtCerrarCancelacion = $conn->prepare("UPDATE SolicitudCancelacion SET estatus = 'aprobada', aprobado_por = ?, fecha_respuesta = ?, comentarios = CASE WHEN comentarios IS NULL OR comentarios = '' THEN ? ELSE CONCAT(comentarios, '\n', ?) END WHERE cita_id = ? AND estatus = 'pendiente'")) {
                $stmtCerrarCancelacion->bind_param('isssi', $idUsuario, $fechaActual, $comentarioAprobacion, $comentarioAprobacion, $citaId);
                $stmtCerrarCancelacion->execute();
                $stmtCerrarCancelacion->close();
            }
        } elseif ($tablaSolicitudesCancelacion instanceof mysqli_result) {
            $tablaSolicitudesCancelacion->free();
        }
    }

    $_SESSION['cancelacion_mensaje'] = 'Cita cancelada correctamente.';
    $_SESSION['cancelacion_tipo'] = 'success';

    header('Location: index.php');
    exit;
}

$conn->close();
?>
