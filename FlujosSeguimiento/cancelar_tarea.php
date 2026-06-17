<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

$session = flujos_require_session();
flujos_require_admin_role($session);
$data = flujos_input();
$id = (int)($data['id_tarea_asignada'] ?? 0);
$motivo = trim((string)($data['motivo'] ?? ''));

if ($id <= 0) {
    flujos_json_response(422, ['success' => false, 'message' => 'id_tarea_asignada es obligatorio.']);
}

$conn = conectar();
$conn->set_charset('utf8mb4');
$stmtGet = $conn->prepare('SELECT id_asignacion FROM exp_flujo_tareas_asignadas WHERE id_tarea_asignada = ? LIMIT 1');
$stmtGet->bind_param('i', $id);
$stmtGet->execute();
$row = $stmtGet->get_result()->fetch_assoc();
$stmtGet->close();
if (!$row) {
    $conn->close();
    flujos_json_response(404, ['success' => false, 'message' => 'Tarea no encontrada.']);
}
$asignacionId = (int)$row['id_asignacion'];

$stmt = $conn->prepare("UPDATE exp_flujo_tareas_asignadas SET estatus = 'cancelada', activo = 0 WHERE id_tarea_asignada = ? AND estatus <> 'cancelada'");
if (!$stmt) {
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible preparar la cancelacion.']);
}
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();
if ($ok) {
    flujos_historial($conn, $asignacionId, $id, 'cancelar_tarea', $motivo !== '' ? $motivo : 'Tarea cancelada.', $session['id']);
    flujos_actualizar_avance($conn, $asignacionId);
}
$conn->close();
flujos_json_response($ok ? 200 : 500, ['success' => $ok]);
