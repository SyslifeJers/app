<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

try {
    $conn = conectar();

    // Validar parámetro
    if (!isset($_POST['id_cita']) || !is_numeric($_POST['id_cita'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Parámetro id_cita inválido'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idCita = (int) $_POST['id_cita'];

    // Preparar DELETE
    $stmt = $conn->prepare(
        "DELETE FROM colaTickets WHERE id_cita = ?"
    );
    $stmt->bind_param("i", $idCita);
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
