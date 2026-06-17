<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

$session = flujos_require_session();
flujos_require_admin_role($session);
$data = flujos_input();
$id = (int)($data['id_tarea_asignada'] ?? 0);
$responsable = flujos_normalize_enum((string)($data['responsable'] ?? ''), ['psicologa','administracion','paciente','escuela','otro'], 'psicologa');
$usuarioResponsable = isset($data['responsable_usuario_id']) && $data['responsable_usuario_id'] !== '' ? (int)$data['responsable_usuario_id'] : null;

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

$stmt = $conn->prepare('UPDATE exp_flujo_tareas_asignadas SET responsable = ?, responsable_usuario_id = ? WHERE id_tarea_asignada = ?');
if (!$stmt) {
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible preparar la reasignacion.']);
}
$stmt->bind_param('sii', $responsable, $usuarioResponsable, $id);
$ok = $stmt->execute();
$stmt->close();
if ($ok) {
    flujos_historial($conn, (int)$row['id_asignacion'], $id, 'reasignar_responsable', 'Responsable: ' . $responsable, $session['id']);
}
$conn->close();
flujos_json_response($ok ? 200 : 500, ['success' => $ok]);
