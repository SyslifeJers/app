<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

$pacienteId = isset($_GET['paciente_id']) ? (int) $_GET['paciente_id'] : 0;
if ($pacienteId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Paciente invalido.']);
    exit;
}

$conn = conectar();
$conn->set_charset('utf8mb4');

$sql = "SELECT sp.id,
               sp.precio_id,
               sp.saldo_restante,
               sp.sesiones_total,
               sp.sesiones_completadas,
               p.name AS servicio_nombre,
               p.costo
        FROM ServiciosPaciente sp
        INNER JOIN Precios p ON p.id = sp.precio_id
        WHERE sp.nino_id = ?
          AND sp.estatus_id IN (2, 5)
          AND sp.sesiones_total <> 0
        ORDER BY sp.creado_en DESC, sp.id DESC
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'No fue posible consultar el servicio activo.']);
    exit;
}

$stmt->bind_param('i', $pacienteId);
$stmt->execute();
$result = $stmt->get_result();
$servicio = $result ? $result->fetch_assoc() : null;
$stmt->close();
$conn->close();

if (!$servicio) {
    echo json_encode(['success' => true, 'servicio' => null]);
    exit;
}

echo json_encode([
    'success' => true,
    'servicio' => [
        'id' => (int) $servicio['id'],
        'precio_id' => (int) $servicio['precio_id'],
        'nombre' => (string) $servicio['servicio_nombre'],
        'costo' => (float) $servicio['costo'],
        'saldo_restante' => (float) $servicio['saldo_restante'],
        'sesiones_total' => (int) $servicio['sesiones_total'],
        'sesiones_completadas' => (int) $servicio['sesiones_completadas'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
