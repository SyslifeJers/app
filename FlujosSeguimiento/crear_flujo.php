<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

$session = flujos_require_session();
flujos_require_admin_role($session);
$data = flujos_input();
$nombre = trim((string)($data['nombre'] ?? ''));
$descripcion = trim((string)($data['descripcion'] ?? ''));
$activo = isset($data['activo']) ? (int)((bool)$data['activo']) : 1;

if ($nombre === '') {
    flujos_json_response(422, ['success' => false, 'message' => 'El nombre del flujo es obligatorio.']);
}

$conn = conectar();
$conn->set_charset('utf8mb4');
$stmt = $conn->prepare('INSERT INTO exp_flujos (nombre, descripcion, activo, usuario_creacion) VALUES (?, ?, ?, ?)');
if (!$stmt) {
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible preparar el registro.']);
}
$stmt->bind_param('ssii', $nombre, $descripcion, $activo, $session['id']);
$ok = $stmt->execute();
$id = (int)$conn->insert_id;
$stmt->close();
$conn->close();

flujos_json_response($ok ? 201 : 500, ['success' => $ok, 'id_flujo' => $id]);
