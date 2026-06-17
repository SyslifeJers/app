<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

$session = flujos_require_session();
flujos_require_admin_role($session);
$data = flujos_input();
$id = (int)($data['id_flujo'] ?? 0);
$nombre = trim((string)($data['nombre'] ?? ''));
$descripcion = trim((string)($data['descripcion'] ?? ''));
$activo = isset($data['activo']) ? (int)((bool)$data['activo']) : 1;

if ($id <= 0 || $nombre === '') {
    flujos_json_response(422, ['success' => false, 'message' => 'id_flujo y nombre son obligatorios.']);
}

$conn = conectar();
$conn->set_charset('utf8mb4');
$stmt = $conn->prepare('UPDATE exp_flujos SET nombre = ?, descripcion = ?, activo = ? WHERE id_flujo = ?');
if (!$stmt) {
    flujos_json_response(500, ['success' => false, 'message' => 'No fue posible preparar la actualizacion.']);
}
$stmt->bind_param('ssii', $nombre, $descripcion, $activo, $id);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

flujos_json_response($ok ? 200 : 500, ['success' => $ok]);
