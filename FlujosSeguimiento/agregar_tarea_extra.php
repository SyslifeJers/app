<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

$session = flujos_require_session();
flujos_require_admin_role($session);
$data = flujos_input();
$asignacionId = (int)($data['id_asignacion'] ?? 0);
$ninoId = (int)($data['id_nino'] ?? 0);
$nombre = trim((string)($data['nombre'] ?? ''));
$descripcion = trim((string)($data['descripcion'] ?? ''));
$tipo = flujos_normalize_enum((string)($data['tipo_tarea'] ?? 'seguimiento'), ['recordatorio','subir_documento','llamada','seguimiento','otro'], 'seguimiento');
$responsable = flujos_normalize_enum((string)($data['responsable'] ?? 'psicologa'), ['psicologa','administracion','paciente','escuela','otro'], 'psicologa');
$responsableUsuarioId = isset($data['responsable_usuario_id']) ? (int)$data['responsable_usuario_id'] : null;
$obligatoria = isset($data['obligatoria']) ? (int)((bool)$data['obligatoria']) : 0;
$requiereDocumento = isset($data['requiere_documento']) ? (int)((bool)$data['requiere_documento']) : 0;
$fechaLimite = trim((string)($data['fecha_limite'] ?? '')) ?: null;

if ($asignacionId <= 0 || $ninoId <= 0 || $nombre === '') {
    flujos_json_response(422, ['success' => false, 'message' => 'id_asignacion, id_nino y nombre son obligatorios.']);
}

$conn = conectar();
$conn->set_charset('utf8mb4');
$stmt = $conn->prepare('INSERT INTO exp_flujo_tareas_asignadas (id_asignacion, id_nino, nombre, descripcion, tipo_tarea, responsable, responsable_usuario_id, obligatoria, requiere_documento, fecha_limite, es_extra) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
if (!$stmt) {
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible preparar la tarea extraordinaria.']);
}
$stmt->bind_param('iissssiiis', $asignacionId, $ninoId, $nombre, $descripcion, $tipo, $responsable, $responsableUsuarioId, $obligatoria, $requiereDocumento, $fechaLimite);
$ok = $stmt->execute();
$tareaId = (int)$conn->insert_id;
$stmt->close();
if ($ok) {
    flujos_historial($conn, $asignacionId, $tareaId, 'agregar_tarea_extra', 'Tarea extraordinaria agregada.', $session['id']);
    flujos_actualizar_avance($conn, $asignacionId);
}
$conn->close();

flujos_json_response($ok ? 201 : 500, ['success' => $ok, 'id_tarea_asignada' => $tareaId]);
