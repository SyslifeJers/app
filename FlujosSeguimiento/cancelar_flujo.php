<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

$session = flujos_require_session();
flujos_require_admin_role($session);
$data = flujos_input();
$id = (int)($data['id_asignacion'] ?? 0);
$motivo = trim((string)($data['motivo'] ?? ''));

if ($id <= 0) {
    flujos_json_response(422, ['success' => false, 'message' => 'id_asignacion es obligatorio.']);
}

$conn = conectar();
$conn->set_charset('utf8mb4');
$fechaCierre = fechaMexicoActual();
$stmt = $conn->prepare("UPDATE exp_flujo_asignaciones SET estatus = 'cancelado', activo = 0, fecha_cierre = ?, usuario_cierre = ? WHERE id_asignacion = ? AND estatus <> 'cancelado'");
if (!$stmt) {
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible preparar la cancelacion.']);
}
$stmt->bind_param('sii', $fechaCierre, $session['id'], $id);
$ok = $stmt->execute();
$stmt->close();
if ($ok) {
    $stmtTasks = $conn->prepare("UPDATE exp_flujo_tareas_asignadas SET estatus = 'cancelada', activo = 0 WHERE id_asignacion = ? AND estatus NOT IN ('completada','negada','cancelada')");
    if ($stmtTasks) {
        $stmtTasks->bind_param('i', $id);
        $stmtTasks->execute();
        $stmtTasks->close();
    }
    flujos_historial($conn, $id, null, 'cancelar_flujo', $motivo !== '' ? $motivo : 'Flujo cancelado.', $session['id']);
}
$conn->close();
flujos_json_response($ok ? 200 : 500, ['success' => $ok]);
