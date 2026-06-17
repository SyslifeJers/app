<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

flujos_require_session();
$conn = conectar();
$conn->set_charset('utf8mb4');

$estatus = trim((string)($_GET['estatus'] ?? ''));
$ninoId = isset($_GET['id_nino']) ? (int)$_GET['id_nino'] : 0;
$conditions = ['a.activo = 1'];
$types = '';
$params = [];
if ($estatus !== '') {
    $conditions[] = 'a.estatus = ?';
    $types .= 's';
    $params[] = $estatus;
}
if ($ninoId > 0) {
    $conditions[] = 'a.id_nino = ?';
    $types .= 'i';
    $params[] = $ninoId;
}

$sql = 'SELECT a.*, f.nombre AS flujo_nombre, n.name AS paciente,
               SUM(CASE WHEN ta.estatus IN (\'pendiente\',\'en_proceso\') AND ta.fecha_limite < CURDATE() THEN 1 ELSE 0 END) AS tareas_vencidas,
               CASE
                   WHEN SUM(CASE WHEN ta.estatus <> \'pendiente\' THEN 1 ELSE 0 END) = 0
                    AND NOT EXISTS (
                        SELECT 1
                        FROM exp_flujo_documentos d
                        INNER JOIN exp_flujo_tareas_asignadas td ON td.id_tarea_asignada = d.id_tarea_asignada
                        WHERE td.id_asignacion = a.id_asignacion
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM exp_flujo_historial h
                        WHERE h.id_asignacion = a.id_asignacion AND h.accion <> \'asignar_flujo\'
                    )
                   THEN 1 ELSE 0
               END AS puede_eliminar
        FROM exp_flujo_asignaciones a
        INNER JOIN exp_flujos f ON f.id_flujo = a.id_flujo
        INNER JOIN nino n ON n.id = a.id_nino
        LEFT JOIN exp_flujo_tareas_asignadas ta ON ta.id_asignacion = a.id_asignacion AND ta.activo = 1
        WHERE ' . implode(' AND ', $conditions) . '
        GROUP BY a.id_asignacion, f.nombre, n.name
        ORDER BY a.fecha_asignacion DESC';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible consultar asignaciones.']);
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
flujos_json_response(200, ['success' => true, 'data' => $rows]);
