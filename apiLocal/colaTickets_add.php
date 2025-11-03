<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

function obtenerEntrada(): array
{
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $decoded = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

try {
    $payload = obtenerEntrada();
    $idCita = isset($payload['id_cita']) ? (int) $payload['id_cita'] : 0;

    if ($idCita <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'El parámetro id_cita es obligatorio.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $estado = $payload['estado'] ?? 'pendiente';
    $estado = is_string($estado) ? strtolower($estado) : 'pendiente';

    $estadosPermitidos = ['pendiente', 'en_proceso', 'impreso', 'error', 'cancelado'];
    if (!in_array($estado, $estadosPermitidos, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Estado inválido para la cola de tickets.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conn = conectar();

    $sql = "INSERT INTO colaTickets (id_cita, estado) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta.');
    }

    $stmt->bind_param('is', $idCita, $estado);
    $stmt->execute();

    $idInsertado = $stmt->insert_id;

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'id' => $idInsertado
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
