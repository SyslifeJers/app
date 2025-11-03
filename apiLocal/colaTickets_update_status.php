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
    $id = isset($payload['id']) ? (int) $payload['id'] : 0;
    $estado = $payload['estado'] ?? null;

    if ($id <= 0 || !is_string($estado)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Los parámetros id y estado son obligatorios.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $estado = strtolower(trim($estado));
    $estadosPermitidos = ['pendiente', 'en_proceso', 'impreso', 'error', 'cancelado'];
    if (!in_array($estado, $estadosPermitidos, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Estado inválido para la cola de tickets.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mensajeError = null;
    $mensajeErrorPresente = array_key_exists('mensaje_error', $payload);
    if ($mensajeErrorPresente) {
        $mensajeError = $payload['mensaje_error'];
        if ($mensajeError !== null) {
            $mensajeError = (string) $mensajeError;
            if (strlen($mensajeError) > 255) {
                $mensajeError = substr($mensajeError, 0, 255);
            }
        }
    }

    $incrementarIntentos = !empty($payload['incrementar_intentos']);

    $conn = conectar();

    $set = ['estado = ?'];
    $types = 's';
    $params = [$estado];

    if ($incrementarIntentos) {
        $set[] = 'intentos = intentos + 1';
    }

    if ($mensajeErrorPresente) {
        if ($mensajeError === null) {
            $set[] = 'mensaje_error = NULL';
        } else {
            $set[] = 'mensaje_error = ?';
            $types .= 's';
            $params[] = $mensajeError;
        }
    }

    $types .= 'i';
    $params[] = $id;

    $sql = 'UPDATE colaTickets SET ' . implode(', ', $set) . ' WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta.');
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $filasAfectadas = $stmt->affected_rows;

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'updated' => $filasAfectadas
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
