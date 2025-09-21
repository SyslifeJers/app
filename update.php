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
    $ROL_COORDINADOR = 4;

    if ($rolUsuario === $ROL_VENTAS) {
        $tablaSolicitudes = $conn->query("SHOW TABLES LIKE 'SolicitudReprogramacion'");
        if (!$tablaSolicitudes || $tablaSolicitudes->num_rows === 0) {
            if ($tablaSolicitudes instanceof mysqli_result) {
                $tablaSolicitudes->free();
            }
            $_SESSION['reprogramacion_mensaje'] = 'El módulo de solicitudes no está disponible. Contacta al administrador.';
            $_SESSION['reprogramacion_tipo'] = 'danger';
            header('Location: index.php');
            exit;
        }
        $tablaSolicitudes->free();

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
    if (!empty($_POST['solicitudId']) && $rolUsuario === $ROL_COORDINADOR) {
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

    $_SESSION['reprogramacion_mensaje'] = 'Fecha de cita actualizada correctamente.';
    $_SESSION['reprogramacion_tipo'] = 'success';
    header('Location: index.php');
    exit;
}

$conn->close();
?>
