<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/logger.php';

function responderCancelacionReservacion(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['id'])) {
    responderCancelacionReservacion(401, ['success' => false, 'message' => 'No autenticado.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    responderCancelacionReservacion(405, ['success' => false, 'message' => 'Método no permitido.']);
}

$reservacionId = isset($_POST['reservacionId']) ? (int) $_POST['reservacionId'] : 0;
if ($reservacionId <= 0) {
    responderCancelacionReservacion(422, ['success' => false, 'message' => 'La reservación continua es obligatoria.']);
}

$conn = conectar();
if (!($conn instanceof mysqli) || $conn->connect_errno) {
    responderCancelacionReservacion(500, ['success' => false, 'message' => 'No fue posible conectar con la base de datos.']);
}
$conn->set_charset('utf8mb4');
$conn->begin_transaction();

try {
    $stmt = $conn->prepare('SELECT id, paciente_id, psicologo_id, activo FROM ReservacionContinua WHERE id = ? FOR UPDATE');
    if ($stmt === false) {
        throw new RuntimeException('No fue posible consultar la reservación continua.');
    }
    $stmt->bind_param('i', $reservacionId);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $reservacion = $resultado ? $resultado->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($reservacion)) {
        throw new RuntimeException('La reservación continua no existe.');
    }

    if ((int) ($reservacion['activo'] ?? 0) !== 1) {
        throw new RuntimeException('La reservación continua ya fue cancelada.');
    }

    $stmtUpdate = $conn->prepare('UPDATE ReservacionContinua SET activo = 0 WHERE id = ?');
    if ($stmtUpdate === false) {
        throw new RuntimeException('No fue posible cancelar la reservación continua.');
    }
    $stmtUpdate->bind_param('i', $reservacionId);
    if (!$stmtUpdate->execute()) {
        $stmtUpdate->close();
        throw new RuntimeException('No fue posible cancelar la reservación continua.');
    }
    $stmtUpdate->close();

    registrarLog(
        $conn,
        $_SESSION['id'],
        'reservaciones_continuas',
        'cancelar',
        sprintf('La reservación continua #%d fue cancelada lógicamente.', $reservacionId),
        'ReservacionContinua',
        (string) $reservacionId
    );

    $conn->commit();
    $conn->close();
    responderCancelacionReservacion(200, ['success' => true, 'message' => 'Reservación continua cancelada correctamente.']);
} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    responderCancelacionReservacion(400, ['success' => false, 'message' => $e->getMessage()]);
}
