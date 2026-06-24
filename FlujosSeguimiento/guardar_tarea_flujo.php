<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

$session = flujos_require_session();
flujos_require_admin_role($session);
$data = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($_POST) ? $_POST : flujos_input();

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
$tipoEntrega = flujos_normalize_enum((string)($data['tipo_entrega'] ?? 'sin_entrega'), ['sin_entrega','subir_documento','documento_plantilla','evaluacion_formulario'], 'sin_entrega');
$idExamen = isset($data['id_examen']) && $data['id_examen'] !== '' ? max(0, (int)$data['id_examen']) : null;
$activo = isset($data['activo']) ? (int)((bool)$data['activo']) : 1;

if ($idFlujo <= 0 || $nombre === '') {
    flujos_json_response(422, ['success' => false, 'message' => 'id_flujo y nombre son obligatorios.']);
}

$conn = conectar();
$conn->set_charset('utf8mb4');

if ($tipoEntrega === 'evaluacion_formulario') {
    if ($idExamen === null || $idExamen <= 0) {
        $conn->close();
        flujos_json_response(422, ['success' => false, 'message' => 'Selecciona el ID de examen para la evaluacion formulario.']);
    }
    $stmtExamen = $conn->prepare('SELECT id_examen FROM exp_examenes WHERE id_examen = ? LIMIT 1');
    if (!$stmtExamen) {
        $conn->close();
        flujos_json_response(500, ['success' => false, 'message' => 'No fue posible validar el examen.']);
    }
    $stmtExamen->bind_param('i', $idExamen);
    $stmtExamen->execute();
    $examenExiste = $stmtExamen->get_result()->fetch_assoc();
    $stmtExamen->close();
    if (!$examenExiste) {
        $conn->close();
        flujos_json_response(422, ['success' => false, 'message' => 'El examen indicado no existe.']);
    }
}

if ($tipoEntrega === 'documento_plantilla') {
    $requiereDocumento = 1;
}

function flujos_guardar_plantilla_tarea(int $tareaId, array $file, int $usuarioId): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No fue posible recibir la plantilla.');
    }
    if ((int)($file['size'] ?? 0) > 12 * 1024 * 1024) {
        throw new RuntimeException('La plantilla excede 12 MB.');
    }
    $original = (string)($file['name'] ?? 'plantilla');
    $info = pathinfo($original);
    $ext = strtolower((string)($info['extension'] ?? ''));
    if (!in_array($ext, ['docx','doc','pdf'], true)) {
        throw new RuntimeException('Solo se permiten plantillas DOCX, DOC o PDF.');
    }
    $base = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($info['filename'] ?? 'plantilla'));
    $filename = trim($base, '_') . '_' . time() . '.' . $ext;
    $dir = __DIR__ . '/../uploads/flujos/plantillas/' . $tareaId;
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $target = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!@move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException('No fue posible guardar la plantilla.');
    }
    $terapiaDir = __DIR__ . '/../../Terapia/uploads/flujos/plantillas/' . $tareaId;
    if (is_dir(__DIR__ . '/../../Terapia')) {
        if (!is_dir($terapiaDir)) {
            @mkdir($terapiaDir, 0777, true);
        }
        @copy($target, rtrim($terapiaDir, '/\\') . DIRECTORY_SEPARATOR . $filename);
    }
    return [
        'ruta' => '/uploads/flujos/plantillas/' . $tareaId . '/' . $filename,
        'nombre' => $original,
        'mime' => (string)($file['type'] ?? ''),
        'tamano' => (int)($file['size'] ?? 0),
        'usuario' => $usuarioId,
    ];
}

if ($idTarea > 0) {
    $plantilla = null;
    try {
        $plantilla = isset($_FILES['plantilla']) ? flujos_guardar_plantilla_tarea($idTarea, $_FILES['plantilla'], $session['id']) : null;
    } catch (Throwable $e) {
        $conn->close();
        flujos_json_response(422, ['success' => false, 'message' => $e->getMessage()]);
    }
    if ($plantilla) {
        $stmt = $conn->prepare('UPDATE exp_flujo_tareas SET id_flujo = ?, nombre = ?, descripcion = ?, orden = ?, tipo_tarea = ?, responsable = ?, obligatoria = ?, dias_vencimiento = ?, requiere_documento = ?, tipo_entrega = ?, id_examen = ?, plantilla_ruta = ?, plantilla_nombre_original = ?, plantilla_mime = ?, plantilla_tamano = ?, plantilla_fecha_subida = NOW(), plantilla_usuario_id = ?, activo = ? WHERE id_tarea = ?');
    } else {
        $stmt = $conn->prepare('UPDATE exp_flujo_tareas SET id_flujo = ?, nombre = ?, descripcion = ?, orden = ?, tipo_tarea = ?, responsable = ?, obligatoria = ?, dias_vencimiento = ?, requiere_documento = ?, tipo_entrega = ?, id_examen = ?, activo = ? WHERE id_tarea = ?');
    }
    if (!$stmt) {
        flujos_json_response(500, ['success' => false, 'message' => 'No fue posible preparar la tarea.']);
    }
    if ($plantilla) {
        $stmt->bind_param('ississiiisisssiiii', $idFlujo, $nombre, $descripcion, $orden, $tipo, $responsable, $obligatoria, $diasVencimiento, $requiereDocumento, $tipoEntrega, $idExamen, $plantilla['ruta'], $plantilla['nombre'], $plantilla['mime'], $plantilla['tamano'], $plantilla['usuario'], $activo, $idTarea);
    } else {
        $stmt->bind_param('ississiiisiii', $idFlujo, $nombre, $descripcion, $orden, $tipo, $responsable, $obligatoria, $diasVencimiento, $requiereDocumento, $tipoEntrega, $idExamen, $activo, $idTarea);
    }
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    flujos_json_response($ok ? 200 : 500, ['success' => $ok, 'id_tarea' => $idTarea]);
}

$stmt = $conn->prepare('INSERT INTO exp_flujo_tareas (id_flujo, nombre, descripcion, orden, tipo_tarea, responsable, obligatoria, dias_vencimiento, requiere_documento, tipo_entrega, id_examen, activo, usuario_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible preparar la tarea.']);
}
$stmt->bind_param('ississiiisiii', $idFlujo, $nombre, $descripcion, $orden, $tipo, $responsable, $obligatoria, $diasVencimiento, $requiereDocumento, $tipoEntrega, $idExamen, $activo, $session['id']);
$ok = $stmt->execute();
$idTarea = (int)$conn->insert_id;
$stmt->close();

if ($ok && isset($_FILES['plantilla'])) {
    try {
        $plantilla = flujos_guardar_plantilla_tarea($idTarea, $_FILES['plantilla'], $session['id']);
        if ($plantilla) {
            $stmt = $conn->prepare('UPDATE exp_flujo_tareas SET plantilla_ruta = ?, plantilla_nombre_original = ?, plantilla_mime = ?, plantilla_tamano = ?, plantilla_fecha_subida = NOW(), plantilla_usuario_id = ? WHERE id_tarea = ?');
            if ($stmt) {
                $stmt->bind_param('sssiii', $plantilla['ruta'], $plantilla['nombre'], $plantilla['mime'], $plantilla['tamano'], $plantilla['usuario'], $idTarea);
                $stmt->execute();
                $stmt->close();
            }
        }
    } catch (Throwable $e) {
        $conn->close();
        flujos_json_response(422, ['success' => false, 'message' => $e->getMessage()]);
    }
}
$conn->close();

flujos_json_response($ok ? 201 : 500, ['success' => $ok, 'id_tarea' => $idTarea]);
