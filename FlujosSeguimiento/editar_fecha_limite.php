<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

$session = flujos_require_session();
flujos_require_admin_role($session);
$data = flujos_input();
$id = (int)($data['id_tarea_asignada'] ?? 0);
$fecha = trim((string)($data['fecha_limite'] ?? ''));

if ($id <= 0 || ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))) {
    flujos_json_response(422, ['success' => false, 'message' => 'Tarea o fecha invalida.']);
}
$fechaValue = $fecha !== '' ? $fecha : null;

$conn = conectar();
$conn->set_charset('utf8mb4');
$stmtGet = $conn->prepare('SELECT id_asignacion, fecha_limite FROM exp_flujo_tareas_asignadas WHERE id_tarea_asignada = ? LIMIT 1');
$stmtGet->bind_param('i', $id);
$stmtGet->execute();
$row = $stmtGet->get_result()->fetch_assoc();
$stmtGet->close();
if (!$row) {
    $conn->close();
    flujos_json_response(404, ['success' => false, 'message' => 'Tarea no encontrada.']);
}

$stmt = $conn->prepare('UPDATE exp_flujo_tareas_asignadas SET fecha_limite = ? WHERE id_tarea_asignada = ?');
if (!$stmt) {
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible preparar la fecha limite.']);
}
$stmt->bind_param('si', $fechaValue, $id);
$ok = $stmt->execute();
$stmt->close();
if ($ok) {
    flujos_historial($conn, (int)$row['id_asignacion'], $id, 'editar_fecha_limite', 'Fecha limite: ' . ($fechaValue ?? 'sin fecha'), $session['id']);
}
$conn->close();
flujos_json_response($ok ? 200 : 500, ['success' => $ok]);
