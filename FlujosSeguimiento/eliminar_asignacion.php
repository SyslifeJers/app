<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

$session = flujos_require_session();
flujos_require_admin_role($session);
$data = flujos_input();
$id = (int)($data['id_asignacion'] ?? 0);

if ($id <= 0) {
    flujos_json_response(422, ['success' => false, 'message' => 'id_asignacion es obligatorio.']);
}

$conn = conectar();
$conn->set_charset('utf8mb4');

try {
    $stmt = $conn->prepare("SELECT
            SUM(CASE WHEN ta.estatus <> 'pendiente' THEN 1 ELSE 0 END) AS tareas_modificadas,
            (SELECT COUNT(*) FROM exp_flujo_documentos d INNER JOIN exp_flujo_tareas_asignadas td ON td.id_tarea_asignada = d.id_tarea_asignada WHERE td.id_asignacion = ?) AS documentos,
            (SELECT COUNT(*) FROM exp_flujo_historial h WHERE h.id_asignacion = ? AND h.accion <> 'asignar_flujo') AS historial_cambios
        FROM exp_flujo_asignaciones a
        LEFT JOIN exp_flujo_tareas_asignadas ta ON ta.id_asignacion = a.id_asignacion AND ta.activo = 1
        WHERE a.id_asignacion = ? AND a.activo = 1
        GROUP BY a.id_asignacion
        LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException('No fue posible validar la asignacion.');
    }
    $stmt->bind_param('iii', $id, $id, $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new RuntimeException('Asignacion no encontrada o ya eliminada.');
    }

    $tareasModificadas = (int)($row['tareas_modificadas'] ?? 0);
    $documentos = (int)($row['documentos'] ?? 0);
    $historialCambios = (int)($row['historial_cambios'] ?? 0);
    if ($tareasModificadas > 0 || $documentos > 0 || $historialCambios > 0) {
        flujos_json_response(409, ['success' => false, 'message' => 'No se puede eliminar: la asignacion ya tiene cambios, historial o documentos.']);
    }

    $conn->begin_transaction();
    $stmtTasks = $conn->prepare("UPDATE exp_flujo_tareas_asignadas SET activo = 0, estatus = 'cancelada' WHERE id_asignacion = ? AND activo = 1");
    if (!$stmtTasks) {
        throw new RuntimeException('No fue posible eliminar tareas.');
    }
    $stmtTasks->bind_param('i', $id);
    $stmtTasks->execute();
    $stmtTasks->close();

    $stmtAsignacion = $conn->prepare("UPDATE exp_flujo_asignaciones SET activo = 0, estatus = 'cancelado', fecha_cierre = NOW(), usuario_cierre = ? WHERE id_asignacion = ? AND activo = 1");
    if (!$stmtAsignacion) {
        throw new RuntimeException('No fue posible eliminar la asignacion.');
    }
    $stmtAsignacion->bind_param('ii', $session['id'], $id);
    $stmtAsignacion->execute();
    $stmtAsignacion->close();
    $conn->commit();
    $conn->close();
    flujos_json_response(200, ['success' => true]);
} catch (Throwable $e) {
    if ($conn->errno === 0) {
        // no-op; mysqli does not expose transaction state reliably in PHP 7 hosts.
    }
    $conn->rollback();
    $conn->close();
    flujos_json_response(500, ['success' => false, 'message' => $e->getMessage()]);
}
