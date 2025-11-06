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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $citaId = isset($_POST['citaId']) ? (int) $_POST['citaId'] : 0;
    $fechaProgramada = $_POST['fechaProgramada'] ?? '';

    if ($citaId <= 0 || empty($fechaProgramada)) {
        $_SESSION['reprogramacion_mensaje'] = 'La información de la cita es inválida.';
        $_SESSION['reprogramacion_tipo'] = 'danger';
        header('Location: index.php');
        exit;
    }

    // Constantes de rol
    $ROL_VENTAS = 1;
    $ROL_RECEPCION = 2;
    $ROL_ADMIN = 3;
    $ROL_COORDINADOR = 5;

    $tablaSolicitudesDisponible = false;
    $tablaSolicitudes = $conn->query("SHOW TABLES LIKE 'SolicitudReprogramacion'");
    if ($tablaSolicitudes instanceof mysqli_result) {
        $tablaSolicitudesDisponible = $tablaSolicitudes->num_rows > 0;
        $tablaSolicitudes->free();
    }

    if ($rolUsuario === $ROL_VENTAS) {
        if (!$tablaSolicitudesDisponible) {
            $_SESSION['reprogramacion_mensaje'] = 'El módulo de solicitudes no está disponible. Contacta al administrador.';
            $_SESSION['reprogramacion_tipo'] = 'danger';
            header('Location: index.php');
            exit;
        }

        // Verificar si ya existe una solicitud pendiente para la cita
        $stmtPendiente = $conn->prepare("SELECT COUNT(*) FROM SolicitudReprogramacion WHERE cita_id = ? AND estatus = 'pendiente' AND tipo = 'reprogramacion'");
        $stmtPendiente->bind_param('i', $citaId);
        $stmtPendiente->execute();
        $stmtPendiente->bind_result($totalPendientes);
        $stmtPendiente->fetch();
        $stmtPendiente->close();

        if ($totalPendientes > 0) {
            $_SESSION['reprogramacion_mensaje'] = 'Ya existe una solicitud pendiente para esta cita.';
            $_SESSION['reprogramacion_tipo'] = 'warning';
            header('Location: index.php');
            exit;
        }

        // Obtener la fecha programada actual para almacenarla en la solicitud
        $stmtFechaActual = $conn->prepare('SELECT Programado FROM Cita WHERE id = ?');
        $stmtFechaActual->bind_param('i', $citaId);
        $stmtFechaActual->execute();
        $stmtFechaActual->bind_result($fechaAnterior);
        $stmtFechaActual->fetch();
        $stmtFechaActual->close();

        if (!$fechaAnterior) {
            $_SESSION['reprogramacion_mensaje'] = 'No fue posible localizar la cita seleccionada.';
            $_SESSION['reprogramacion_tipo'] = 'danger';
            header('Location: index.php');
            exit;
        }

        $stmtSolicitud = $conn->prepare("INSERT INTO SolicitudReprogramacion (cita_id, fecha_anterior, nueva_fecha, estatus, solicitado_por, fecha_solicitud, tipo) VALUES (?, ?, ?, 'pendiente', ?, ?, 'reprogramacion')");
        $stmtSolicitud->bind_param('issis', $citaId, $fechaAnterior, $fechaProgramada, $idUsuario, $fechaActual);
        $stmtSolicitud->execute();
        $solicitudId = $conn->insert_id;
        $stmtSolicitud->close();

        registrarLog(
            $conn,
            $idUsuario,
            'citas',
            'solicitud_reprogramacion',
            sprintf(
                'Solicitud de reprogramación para la cita #%d. Fecha anterior: %s. Nueva fecha solicitada: %s.',
                $citaId,
                $fechaAnterior,
                $fechaProgramada
            ),
            'SolicitudReprogramacion',
            (string) $solicitudId
        );

        $_SESSION['reprogramacion_mensaje'] = 'Se envió la solicitud de reprogramación para aprobación.';
        $_SESSION['reprogramacion_tipo'] = 'success';
        header('Location: index.php');
        exit;
    }

    $shouldRegistrarAutoSolicitud = $tablaSolicitudesDisponible
        && $rolUsuario === $ROL_RECEPCION
        && empty($_POST['solicitudId'])
        && $idUsuario !== null;

    $fechaAnteriorParaRegistro = null;
    if ($shouldRegistrarAutoSolicitud) {
        $stmtFechaAnterior = $conn->prepare('SELECT Programado FROM Cita WHERE id = ?');
        if ($stmtFechaAnterior) {
            $stmtFechaAnterior->bind_param('i', $citaId);
            $stmtFechaAnterior->execute();
            $stmtFechaAnterior->bind_result($fechaAnteriorParaRegistro);
            $stmtFechaAnterior->fetch();
            $stmtFechaAnterior->close();

            if (empty($fechaAnteriorParaRegistro)) {
                $shouldRegistrarAutoSolicitud = false;
            }
        } else {
            $shouldRegistrarAutoSolicitud = false;
        }
    }

    // Actualización directa para coordinadores, administradores u otros roles autorizados
    $stmt = $conn->prepare('UPDATE Cita SET Programado = ?, Estatus = 3 WHERE id = ?');
    $stmt->bind_param('si', $fechaProgramada, $citaId);
    $stmt->execute();
    $stmt->close();

    $stmtInsert = $conn->prepare('INSERT INTO HistorialEstatus(id, fecha, idEstatus, idCita, idUsuario) VALUES (null, ?, 3, ?, ?)');
    $stmtInsert->bind_param('sii', $fechaActual, $citaId, $idUsuario);
    $stmtInsert->execute();
    $stmtInsert->close();

    registrarLog(
        $conn,
        $idUsuario,
        'citas',
        'reprogramar',
        sprintf('La cita #%d fue reprogramada para %s.', $citaId, $fechaProgramada),
        'Cita',
        (string) $citaId
    );

    // Si la actualización proviene de una solicitud, marcarla como atendida automáticamente
    // cuando la aprueba un coordinador o administrador
    if (!empty($_POST['solicitudId']) && in_array($rolUsuario, [$ROL_COORDINADOR, $ROL_ADMIN], true)) {
        $solicitudId = (int) $_POST['solicitudId'];
        $stmtAtendida = $conn->prepare("UPDATE SolicitudReprogramacion SET estatus = 'aprobada', aprobado_por = ?, fecha_respuesta = ?, comentarios = IFNULL(comentarios, '') WHERE id = ?");
        $stmtAtendida->bind_param('isi', $idUsuario, $fechaActual, $solicitudId);
        $stmtAtendida->execute();
        $stmtAtendida->close();

        registrarLog(
            $conn,
            $idUsuario,
            'citas',
            'aprobar_solicitud_reprogramacion',
            sprintf('La solicitud #%d de reprogramación fue aprobada y aplicada a la cita #%d.', $solicitudId, $citaId),
            'SolicitudReprogramacion',
            (string) $solicitudId
        );
    }

    if ($shouldRegistrarAutoSolicitud) {
        $stmtRegistrarSolicitud = $conn->prepare("INSERT INTO SolicitudReprogramacion (cita_id, fecha_anterior, nueva_fecha, estatus, tipo, solicitado_por, comentarios, fecha_solicitud, aprobado_por, fecha_respuesta) VALUES (?, ?, ?, 'aprobada', 'reprogramacion', ?, NULL, ?, ?, ?)");
        if ($stmtRegistrarSolicitud) {
            $stmtRegistrarSolicitud->bind_param('issisis', $citaId, $fechaAnteriorParaRegistro, $fechaProgramada, $idUsuario, $fechaActual, $idUsuario, $fechaActual);
            if ($stmtRegistrarSolicitud->execute()) {
                $solicitudGeneradaId = $conn->insert_id;
                registrarLog(
                    $conn,
                    $idUsuario,
                    'citas',
                    'registrar_reprogramacion_recepcion',
                    sprintf('La recepción registró automáticamente la reprogramación de la cita #%d. Fecha anterior: %s. Nueva fecha: %s.', $citaId, $fechaAnteriorParaRegistro, $fechaProgramada),
                    'SolicitudReprogramacion',
                    (string) $solicitudGeneradaId
                );
            }
            $stmtRegistrarSolicitud->close();
        }
    }

    $_SESSION['reprogramacion_mensaje'] = 'Fecha de cita actualizada correctamente.';
    $_SESSION['reprogramacion_tipo'] = 'success';

    $redirectTo = isset($_POST['redirect_to']) ? trim((string) $_POST['redirect_to']) : '';
    $redirectInvalido = $redirectTo === ''
        || strpos($redirectTo, '://') !== false
        || strpos($redirectTo, '//') === 0
        || strpos($redirectTo, "\n") !== false
        || strpos($redirectTo, "\r") !== false
        || strpos($redirectTo, '..') !== false;

    if ($redirectInvalido) {
        $redirectTo = 'index.php';
    }

    header('Location: ' . $redirectTo);
    exit;
}

$conn->close();
?>
