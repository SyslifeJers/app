<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

$session = flujos_require_session();
flujos_require_admin_role($session);

$taskId = isset($_POST['id_tarea_asignada']) ? (int)$_POST['id_tarea_asignada'] : 0;
$comentario = trim((string)($_POST['comentario'] ?? 'Documento corregido por administracion.'));

if ($taskId <= 0 || !isset($_FILES['file'])) {
    flujos_json_response(422, ['success' => false, 'message' => 'Tarea y archivo son obligatorios.']);
}

if ((int)($_FILES['file']['size'] ?? 0) > 12 * 1024 * 1024) {
    flujos_json_response(422, ['success' => false, 'message' => 'El archivo excede 12 MB.']);
}

$original = (string)($_FILES['file']['name'] ?? 'documento');
$info = pathinfo($original);
$ext = strtolower((string)($info['extension'] ?? ''));
if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'], true)) {
    flujos_json_response(422, ['success' => false, 'message' => 'Solo se permiten PDF, JPG, PNG, DOC o DOCX.']);
}

$conn = conectar();
$conn->set_charset('utf8mb4');

$stmtTask = $conn->prepare('SELECT id_asignacion FROM exp_flujo_tareas_asignadas WHERE id_tarea_asignada = ? AND activo = 1 LIMIT 1');
if (!$stmtTask) {
    $conn->close();
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible validar la tarea.']);
}
$stmtTask->bind_param('i', $taskId);
$stmtTask->execute();
$task = $stmtTask->get_result()->fetch_assoc();
$stmtTask->close();
if (!$task) {
    $conn->close();
    flujos_json_response(404, ['success' => false, 'message' => 'Tarea no encontrada.']);
}

$base = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($info['filename'] ?? 'documento'));
$filename = trim($base, '_') . '_admin_' . time() . '.' . $ext;
$dir = __DIR__ . '/../uploads/flujos/tareas/' . $taskId;
if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
}
$target = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
if (!@move_uploaded_file((string)$_FILES['file']['tmp_name'], $target)) {
    $conn->close();
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible guardar el archivo.']);
}

$terapiaDir = __DIR__ . '/../../Terapia/uploads/flujos/tareas/' . $taskId;
if (is_dir(__DIR__ . '/../../Terapia')) {
    if (!is_dir($terapiaDir)) {
        @mkdir($terapiaDir, 0777, true);
    }
    @copy($target, rtrim($terapiaDir, '/\\') . DIRECTORY_SEPARATOR . $filename);
}

$ruta = '/uploads/flujos/tareas/' . $taskId . '/' . $filename;
$mime = (string)($_FILES['file']['type'] ?? '');
$size = (int)($_FILES['file']['size'] ?? 0);
$stmt = $conn->prepare('INSERT INTO exp_flujo_documentos (id_tarea_asignada, ruta, nombre_original, mime, tamano, comentario, usuario_subida) VALUES (?, ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    $conn->close();
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible registrar el documento.']);
}
$stmt->bind_param('isssisi', $taskId, $ruta, $original, $mime, $size, $comentario, $session['id']);
$ok = $stmt->execute();
$stmt->close();
if ($ok) {
    flujos_historial($conn, (int)$task['id_asignacion'], $taskId, 'subir_documento_admin', 'Documento corregido adjunto: ' . $original, $session['id']);
}
$conn->close();
flujos_json_response($ok ? 201 : 500, ['success' => $ok, 'ruta' => $ruta]);
