<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

try {
    $conn = conectar();

    // Validar parámetro



    // Preparar DELETE
    $stmt = $conn->prepare(
        "DELETE FROM colaTickets "
    );
    $stmt->execute();

    $filasAfectadas = $stmt->affected_rows;

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'deleted_rows' => $filasAfectadas,
        'message' => $filasAfectadas > 0
            ? 'Tickets eliminados correctamente'
            : 'No se encontraron tickets para la cita indicada'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
