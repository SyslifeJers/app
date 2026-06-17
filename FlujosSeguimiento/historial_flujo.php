<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

flujos_require_session();
$id = isset($_GET['id_asignacion']) ? (int)$_GET['id_asignacion'] : 0;
if ($id <= 0) {
    flujos_json_response(422, ['success' => false, 'message' => 'id_asignacion es obligatorio.']);
}
$conn = conectar();
$conn->set_charset('utf8mb4');
$stmt = $conn->prepare('SELECT h.*, u.name AS usuario FROM exp_flujo_historial h LEFT JOIN Usuarios u ON u.id = h.usuario_id WHERE h.id_asignacion = ? ORDER BY h.fecha DESC');
$stmt->bind_param('i', $id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
flujos_json_response(200, ['success' => true, 'data' => $rows]);
