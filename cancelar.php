<?php
// Datos de la conexión
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once 'conexion.php';
require_once __DIR__ . '/Modulos/logger.php';
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

    $fechaProgramadaActual = null;
    if ($stmtDatosCita = $conn->prepare('SELECT Programado FROM Cita WHERE id = ?')) {
        $stmtDatosCita->bind_param('i', $citaId);
        $stmtDatosCita->execute();
        $stmtDatosCita->bind_result($fechaProgramadaActual);
        $stmtDatosCita->fetch();
        $stmtDatosCita->close();
    }

    if (!$fechaProgramadaActual) {
        $_SESSION['cancelacion_mensaje'] = 'No fue posible localizar la cita seleccionada.';
        $_SESSION['cancelacion_tipo'] = 'danger';
        header('Location: index.php');
        exit;
    }

    if ($rolUsuario === $ROL_VENTAS && $estatus === 1) {
        $tablaSolicitudes = $conn->query("SHOW TABLES LIKE 'SolicitudReprogramacion'");
        if (!($tablaSolicitudes instanceof mysqli_result) || $tablaSolicitudes->num_rows === 0) {
            if ($tablaSolicitudes instanceof mysqli_result) {
                $tablaSolicitudes->free();
            }
            $_SESSION['cancelacion_mensaje'] = 'El módulo de solicitudes no está disponible. Contacta al administrador.';
            $_SESSION['cancelacion_tipo'] = 'danger';
            header('Location: index.php');
            exit;
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
            $_SESSION['cancelacion_mensaje'] = 'Ya existe una solicitud de cancelación pendiente para esta cita.';
            $_SESSION['cancelacion_tipo'] = 'warning';
            header('Location: index.php');
            exit;
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
        $_SESSION['cancelacion_mensaje'] = 'Cita cancelada correctamente.';
        $_SESSION['cancelacion_tipo'] = 'success';
    } elseif ($estatus === 4) {
        $_SESSION['cancelacion_mensaje'] = 'Pago registrado correctamente.';
        $_SESSION['cancelacion_tipo'] = 'success';
    } else {
        $_SESSION['cancelacion_mensaje'] = 'Estatus de la cita actualizado correctamente.';
        $_SESSION['cancelacion_tipo'] = 'success';
    }

    header('Location: index.php');
    exit;
}

$conn->close();
?>
