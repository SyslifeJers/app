<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

flujos_require_session();
$asignacionId = isset($_GET['id_asignacion']) ? (int)$_GET['id_asignacion'] : 0;
$ninoId = isset($_GET['id_nino']) ? (int)$_GET['id_nino'] : 0;

$conn = conectar();
$conn->set_charset('utf8mb4');

$conditions = [];
$types = '';
$params = [];
if ($asignacionId > 0) {
    $conditions[] = 'a.id_asignacion = ?';
    $types .= 'i';
    $params[] = $asignacionId;
}
if ($ninoId > 0) {
    $conditions[] = 'a.id_nino = ?';
    $types .= 'i';
    $params[] = $ninoId;
}
if ($conditions === []) {
    $conditions[] = 'a.activo = 1';
}

$sql = 'SELECT a.*, f.nombre AS flujo_nombre, n.name AS paciente
        FROM exp_flujo_asignaciones a
        INNER JOIN exp_flujos f ON f.id_flujo = a.id_flujo
        INNER JOIN nino n ON n.id = a.id_nino
        WHERE ' . implode(' AND ', $conditions) . '
        ORDER BY a.fecha_asignacion DESC';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible preparar el seguimiento.']);
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($assignments as &$assignment) {
    $id = (int)$assignment['id_asignacion'];
    $stmtTasks = $conn->prepare('SELECT * FROM exp_flujo_tareas_asignadas WHERE id_asignacion = ? AND activo = 1 ORDER BY orden ASC, fecha_limite ASC, id_tarea_asignada ASC');
    $stmtTasks->bind_param('i', $id);
    $stmtTasks->execute();
    $assignment['tareas'] = $stmtTasks->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtTasks->close();
}
unset($assignment);

$conn->close();
flujos_json_response(200, ['success' => true, 'data' => $assignments]);
