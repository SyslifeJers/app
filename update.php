<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once 'conexion.php';
require_once __DIR__ . '/Modulos/conflictos_agenda.php';
require_once __DIR__ . '/Modulos/logger.php';

session_start();

$conn = conectar();
date_default_timezone_set('America/Mexico_City');
$fechaActual = date('Y-m-d H:i:s');

$idUsuario = $_SESSION['id'] ?? null;
$rolUsuario = $_SESSION['rol'] ?? null;
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function finalizarReprogramacion(bool $success, string $mensaje, string $tipo = 'success', array $extra = []): void
{
    global $isAjax;

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $mensaje,
            'tipo' => $tipo,
        ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $_SESSION['reprogramacion_mensaje'] = $mensaje;
    $_SESSION['reprogramacion_tipo'] = $tipo;

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $conn->close();
    finalizarReprogramacion(false, 'Método no permitido.', 'danger');
}

$citaId = isset($_POST['citaId']) ? (int) $_POST['citaId'] : 0;
$fechaProgramada = $_POST['fechaProgramada'] ?? '';
$forzar = isset($_POST['forzar']) && (int) $_POST['forzar'] === 1;

if ($citaId <= 0 || empty($fechaProgramada)) {
    $conn->close();
    finalizarReprogramacion(false, 'La información de la cita es inválida.', 'danger');
}

$ROL_VENTAS = 1;
$ROL_RECEPCION = 2;
$ROL_ADMIN = 3;
$ROL_COORDINADOR = 5;
$ROL_PRACTICANTE = 6;

if ($rolUsuario === $ROL_PRACTICANTE) {
    $conn->close();
    finalizarReprogramacion(false, 'No tienes permisos para reprogramar citas.', 'danger');
}

$tablaSolicitudesDisponible = false;
$tablaSolicitudes = $conn->query("SHOW TABLES LIKE 'SolicitudReprogramacion'");
if ($tablaSolicitudes instanceof mysqli_result) {
    $tablaSolicitudesDisponible = $tablaSolicitudes->num_rows > 0;
    $tablaSolicitudes->free();
}

$conn->begin_transaction();

try {
    $fechaAnteriorParaRegistro = null;
    $psicologoIdCita = 0;
    $tiempoCita = 60;
    $pacienteIdCita = 0;

    $stmtCita = $conn->prepare('SELECT Programado, IdUsuario, COALESCE(Tiempo, 60), IdNino FROM Cita WHERE id = ? FOR UPDATE');
    if (!$stmtCita) {
        throw new RuntimeException('No fue posible localizar la cita.');
    }
    $stmtCita->bind_param('i', $citaId);
    $stmtCita->execute();
    $stmtCita->bind_result($fechaAnteriorParaRegistro, $psicologoIdCita, $tiempoCita, $pacienteIdCita);
    if (!$stmtCita->fetch()) {
        $stmtCita->close();
        throw new RuntimeException('La cita seleccionada no existe.');
    }
    $stmtCita->close();

    $shouldRegistrarAutoSolicitud = $tablaSolicitudesDisponible
        && $rolUsuario === $ROL_RECEPCION
        && empty($_POST['solicitudId'])
        && $idUsuario !== null
        && !empty($fechaAnteriorParaRegistro);

    $conflictoAgenda = obtenerConflictoAgendaPsicologo($conn, (int) $psicologoIdCita, $fechaProgramada, (int) $tiempoCita, $citaId, (int) $pacienteIdCita);
    if ($conflictoAgenda !== null && !$forzar) {
        $conn->rollback();
        finalizarReprogramacion(
            false,
            'La psicóloga seleccionada ya tiene una cita en ese horario.',
            'warning',
            construirPayloadConflictoAgenda($conflictoAgenda)
        );
    }

    $forzada = ($conflictoAgenda !== null && $forzar) ? 1 : 0;

    $stmt = $conn->prepare('UPDATE Cita SET Programado = ?, Estatus = 3, forzada = ? WHERE id = ?');
    if (!$stmt) {
        throw new RuntimeException('No fue posible preparar la reprogramación.');
    }
    $stmt->bind_param('sii', $fechaProgramada, $forzada, $citaId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('No fue posible actualizar la cita.');
    }
    $stmt->close();

    $stmtInsert = $conn->prepare('INSERT INTO HistorialEstatus(id, fecha, idEstatus, idCita, idUsuario) VALUES (null, ?, 3, ?, ?)');
    if ($stmtInsert) {
        $stmtInsert->bind_param('sii', $fechaActual, $citaId, $idUsuario);
        $stmtInsert->execute();
        $stmtInsert->close();
    }

    registrarLog(
        $conn,
        $idUsuario,
        'citas',
        'reprogramar',
        sprintf('La cita #%d fue reprogramada para %s%s.', $citaId, $fechaProgramada, $forzada === 1 ? ' con conflicto forzado' : ''),
        'Cita',
        (string) $citaId
    );

    if (!empty($_POST['solicitudId']) && in_array($rolUsuario, [$ROL_COORDINADOR, $ROL_ADMIN], true)) {
        $solicitudId = (int) $_POST['solicitudId'];
        $stmtAtendida = $conn->prepare("UPDATE SolicitudReprogramacion SET estatus = 'aprobada', aprobado_por = ?, fecha_respuesta = ?, comentarios = IFNULL(comentarios, '') WHERE id = ?");
        if ($stmtAtendida) {
            $stmtAtendida->bind_param('isi', $idUsuario, $fechaActual, $solicitudId);
            $stmtAtendida->execute();
            $stmtAtendida->close();
        }

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

    $conn->commit();
    $conn->close();
    finalizarReprogramacion(true, $forzada === 1 ? 'Fecha de cita actualizada correctamente como forzada.' : 'Fecha de cita actualizada correctamente.', 'success', ['forzada' => $forzada === 1]);
} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    finalizarReprogramacion(false, $e->getMessage(), 'danger');
}
