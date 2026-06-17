<?php
declare(strict_types=1);
require_once __DIR__ . '/flujos_lib.php';

$session = flujos_require_session();
flujos_require_admin_role($session);
$data = flujos_input();
$flujoId = (int)($data['id_flujo'] ?? 0);
$ninoId = (int)($data['id_nino'] ?? 0);
$psicologoId = (int)($data['psicologo_id'] ?? 0);
$observaciones = trim((string)($data['observaciones'] ?? ''));

if ($flujoId <= 0 || $ninoId <= 0 || $psicologoId <= 0) {
    flujos_json_response(422, ['success' => false, 'message' => 'Flujo, paciente y responsable son obligatorios.']);
}

$conn = conectar();
$conn->set_charset('utf8mb4');
try {
    $stmtResponsable = $conn->prepare("SELECT u.id FROM Usuarios u INNER JOIN Rol r ON r.id = u.IdRol WHERE u.id = ? AND u.activo = 1 AND (LOWER(r.name) LIKE '%psicolog%' OR LOWER(r.name) LIKE '%admin%' OR r.id IN (1, 3, 5)) LIMIT 1");
    if (!$stmtResponsable) {
        throw new RuntimeException('No fue posible validar el responsable.');
    }
    $stmtResponsable->bind_param('i', $psicologoId);
    $stmtResponsable->execute();
    $responsableExiste = $stmtResponsable->get_result()->fetch_assoc();
    $stmtResponsable->close();
    if (!$responsableExiste) {
        flujos_json_response(422, ['success' => false, 'message' => 'Selecciona un responsable valido.']);
    }

    $asignacionId = flujos_asignar($conn, $flujoId, $ninoId, $psicologoId, $observaciones, $session['id']);
    $conn->close();
    flujos_json_response(201, ['success' => true, 'id_asignacion' => $asignacionId]);
} catch (Throwable $e) {
    $conn->close();
    flujos_json_response(500, ['success' => false, 'message' => $e->getMessage()]);
}
