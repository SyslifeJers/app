<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

try {
    $conn = conectar();

    $sql = "SELECT ct.id, ct.id_cita, ct.estado, ct.intentos, ct.mensaje_error, ct.creado_en, ct.actualizado_en,
                   c.Programado AS cita_programada
            FROM colaTickets ct
            LEFT JOIN Cita c ON c.id = ct.id_cita
            ORDER BY ct.creado_en ASC";

    $result = $conn->query($sql);

    $tickets = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $tickets
    ], JSON_UNESCAPED_UNICODE);

    $conn->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
