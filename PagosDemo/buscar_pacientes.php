<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
if (!in_array($rolUsuario, [1, 2, 3, 5], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para buscar pacientes.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

require_once __DIR__ . '/../conexion.php';

$conn = conectar();
$pacientes = [];

if ($q === '') {
    $stmt = $conn->prepare('SELECT n.id, n.name, COALESCE(n.saldo_paquete, 0) AS saldo_demo FROM nino n WHERE n.activo = 1 ORDER BY n.name ASC LIMIT 50');
} else {
    $like = '%' . $q . '%';
    $stmt = $conn->prepare('SELECT n.id, n.name, COALESCE(n.saldo_paquete, 0) AS saldo_demo FROM nino n WHERE n.activo = 1 AND n.name LIKE ? ORDER BY n.name ASC LIMIT 50');
}

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible preparar la búsqueda.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $conn->close();
    exit;
}

if ($q !== '') {
    $stmt->bind_param('s', $like);
}

if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pacientes[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'saldo_demo' => (float) $row['saldo_demo'],
        ];
    }
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $pacientes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
