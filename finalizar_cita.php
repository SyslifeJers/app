<?php
require_once 'conexion.php';
require_once __DIR__ . '/Modulos/logger.php';

session_start();

header('Content-Type: application/json');

date_default_timezone_set('America/Mexico_City');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
    exit;
}

$citaId = isset($_POST['citaId']) ? (int) $_POST['citaId'] : 0;
if ($citaId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Identificador de cita no válido.'
    ]);
    exit;
}

$conn = conectar();

$idUsuario = $_SESSION['id'] ?? null;
$fechaActual = date('Y-m-d H:i:s');

try {
    $conn->begin_transaction();

    $formaPago = null;
    if ($stmtDatos = $conn->prepare('SELECT FormaPago FROM Cita WHERE id = ? FOR UPDATE')) {
        $stmtDatos->bind_param('i', $citaId);
        $stmtDatos->execute();
        $stmtDatos->bind_result($formaPago);
        if (!$stmtDatos->fetch()) {
            $stmtDatos->close();
            throw new Exception('La cita seleccionada no existe.');
        }
        $stmtDatos->close();
    } else {
        throw new Exception('No fue posible obtener la información de la cita.');
    }

    if ($formaPago === null || $formaPago === '') {
        throw new Exception('Registra un pago antes de finalizar la cita.');
    }

    $estatusFinalizada = null;
    if ($stmtEstatus = $conn->prepare("SELECT id FROM Estatus WHERE LOWER(name) = 'finalizada' LIMIT 1")) {
        $stmtEstatus->execute();
        $stmtEstatus->bind_result($estatusFinalizada);
        if (!$stmtEstatus->fetch()) {
            $estatusFinalizada = null;
        }
        $stmtEstatus->close();
    }

    if ($estatusFinalizada === null) {
        $estatusFinalizada = 4;
    }

    if ($stmtActualizar = $conn->prepare('UPDATE Cita SET Estatus = ? WHERE id = ?')) {
        $stmtActualizar->bind_param('ii', $estatusFinalizada, $citaId);
        if (!$stmtActualizar->execute()) {
            $stmtActualizar->close();
            throw new Exception('No fue posible actualizar la cita.');
        }
        $stmtActualizar->close();
    } else {
        throw new Exception('No fue posible actualizar la cita.');
    }

    if ($idUsuario === null) {
        throw new Exception('No se pudo identificar al usuario que finaliza la cita.');
    }

    if ($stmtHistorial = $conn->prepare('INSERT INTO HistorialEstatus(id, fecha, idEstatus, idCita, idUsuario) VALUES (NULL, ?, ?, ?, ?)')) {
        $stmtHistorial->bind_param('siii', $fechaActual, $estatusFinalizada, $citaId, $idUsuario);
        $stmtHistorial->execute();
        $stmtHistorial->close();
    } else {
        throw new Exception('No fue posible guardar el historial de la cita.');
    }

    registrarLog(
        $conn,
        $idUsuario,
        'citas',
        'finalizar',
        sprintf('La cita #%d fue finalizada.', $citaId),
        'Cita',
        (string) $citaId
    );

    $conn->commit();

    echo json_encode([
        'success' => true
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
