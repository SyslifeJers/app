<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

flujos_require_session();
$flujoId = isset($_GET['id_flujo']) ? (int)$_GET['id_flujo'] : 0;
if ($flujoId <= 0) {
    flujos_json_response(422, ['success' => false, 'message' => 'id_flujo es obligatorio.']);
}

$conn = conectar();
$conn->set_charset('utf8mb4');
$stmt = $conn->prepare('SELECT * FROM exp_flujo_tareas WHERE id_flujo = ? ORDER BY orden ASC, id_tarea ASC');
if (!$stmt) {
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible consultar tareas.']);
}
$stmt->bind_param('i', $flujoId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
flujos_json_response(200, ['success' => true, 'data' => $rows]);
