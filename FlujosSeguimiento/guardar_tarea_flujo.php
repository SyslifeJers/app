<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

$session = flujos_require_session();
flujos_require_admin_role($session);
$data = flujos_input();

$idTarea = (int)($data['id_tarea'] ?? 0);
$idFlujo = (int)($data['id_flujo'] ?? 0);
$nombre = trim((string)($data['nombre'] ?? ''));
$descripcion = trim((string)($data['descripcion'] ?? ''));
$orden = (int)($data['orden'] ?? 0);
$tipo = flujos_normalize_enum((string)($data['tipo_tarea'] ?? 'seguimiento'), ['recordatorio','subir_documento','llamada','seguimiento','otro'], 'seguimiento');
$responsable = flujos_normalize_enum((string)($data['responsable'] ?? 'psicologa'), ['psicologa','administracion','paciente','escuela','otro'], 'psicologa');
$obligatoria = isset($data['obligatoria']) ? (int)((bool)$data['obligatoria']) : 1;
$diasVencimiento = isset($data['dias_vencimiento']) && $data['dias_vencimiento'] !== '' ? max(0, (int)$data['dias_vencimiento']) : null;
$requiereDocumento = isset($data['requiere_documento']) ? (int)((bool)$data['requiere_documento']) : 0;
$activo = isset($data['activo']) ? (int)((bool)$data['activo']) : 1;

if ($idFlujo <= 0 || $nombre === '') {
    flujos_json_response(422, ['success' => false, 'message' => 'id_flujo y nombre son obligatorios.']);
}

$conn = conectar();
$conn->set_charset('utf8mb4');

if ($idTarea > 0) {
    $stmt = $conn->prepare('UPDATE exp_flujo_tareas SET id_flujo = ?, nombre = ?, descripcion = ?, orden = ?, tipo_tarea = ?, responsable = ?, obligatoria = ?, dias_vencimiento = ?, requiere_documento = ?, activo = ? WHERE id_tarea = ?');
    if (!$stmt) {
        flujos_json_response(500, ['success' => false, 'message' => 'No fue posible preparar la tarea.']);
    }
    $stmt->bind_param('ississiiiii', $idFlujo, $nombre, $descripcion, $orden, $tipo, $responsable, $obligatoria, $diasVencimiento, $requiereDocumento, $activo, $idTarea);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    flujos_json_response($ok ? 200 : 500, ['success' => $ok, 'id_tarea' => $idTarea]);
}

$stmt = $conn->prepare('INSERT INTO exp_flujo_tareas (id_flujo, nombre, descripcion, orden, tipo_tarea, responsable, obligatoria, dias_vencimiento, requiere_documento, activo, usuario_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible preparar la tarea.']);
}
$stmt->bind_param('ississiiiii', $idFlujo, $nombre, $descripcion, $orden, $tipo, $responsable, $obligatoria, $diasVencimiento, $requiereDocumento, $activo, $session['id']);
$ok = $stmt->execute();
$idTarea = (int)$conn->insert_id;
$stmt->close();
$conn->close();

flujos_json_response($ok ? 201 : 500, ['success' => $ok, 'id_tarea' => $idTarea]);
