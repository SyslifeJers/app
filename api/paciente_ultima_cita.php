<?php
header('Content-Type: application/json; charset=utf-8');

session_start();

require_once __DIR__ . '/../conexion.php';

function responderUltimaCita(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['id'])) {
    responderUltimaCita(401, ['success' => false, 'message' => 'No autenticado.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    responderUltimaCita(405, ['success' => false, 'message' => 'Método no permitido.']);
}

$pacienteId = isset($_GET['paciente_id']) ? (int) $_GET['paciente_id'] : 0;
if ($pacienteId <= 0) {
    responderUltimaCita(422, ['success' => false, 'message' => 'Selecciona un paciente válido.']);
}

$conn = conectar();
if (!($conn instanceof mysqli) || $conn->connect_errno) {
    responderUltimaCita(500, ['success' => false, 'message' => 'No fue posible conectar con la base de datos.']);
}
$conn->set_charset('utf8mb4');

$sql = "SELECT ci.id,
               ci.IdUsuario AS psicologo_id,
               ci.Programado,
               ci.Tiempo,
               ci.Tipo,
               n.name AS paciente,
               u.name AS psicologo,
               es.name AS estatus
        FROM Cita ci
        INNER JOIN nino n ON n.id = ci.IdNino
        INNER JOIN Usuarios u ON u.id = ci.IdUsuario
        LEFT JOIN Estatus es ON es.id = ci.Estatus
        WHERE ci.IdNino = ?
          AND (es.name IS NULL OR TRIM(LOWER(es.name)) <> 'cancelada')
        ORDER BY ci.Programado DESC
        LIMIT 1";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    $conn->close();
    responderUltimaCita(500, ['success' => false, 'message' => 'No fue posible preparar la consulta.']);
}

$stmt->bind_param('i', $pacienteId);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    responderUltimaCita(500, ['success' => false, 'message' => 'No fue posible consultar la última cita.']);
}

$resultado = $stmt->get_result();
$cita = $resultado ? $resultado->fetch_assoc() : null;
$stmt->close();
$conn->close();

if (!is_array($cita)) {
    responderUltimaCita(404, ['success' => false, 'message' => 'El paciente no tiene citas registradas.']);
}

responderUltimaCita(200, [
    'success' => true,
    'cita' => [
        'id' => (int) $cita['id'],
        'paciente_id' => $pacienteId,
        'paciente' => $cita['paciente'],
        'psicologo_id' => (int) $cita['psicologo_id'],
        'psicologo' => $cita['psicologo'],
        'programado' => $cita['Programado'],
        'tiempo' => max(1, (int) ($cita['Tiempo'] ?? 60)),
        'tipo' => $cita['Tipo'],
        'estatus' => $cita['estatus'],
    ],
]);
