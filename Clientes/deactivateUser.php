<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/logger.php';
$conn = conectar();

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;

if (!in_array($rolUsuario, [3, 5], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permisos para actualizar el estado del cliente.'], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID is required']);
    $conn->close();
    exit;
}

$stmt = $conn->prepare('SELECT activo FROM Clientes WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Client not found']);
    $conn->close();
    exit;
}

$nuevoActivo = $row['activo'] == 1 ? 0 : 1;

$stmt = $conn->prepare('UPDATE Clientes SET activo = ? WHERE id = ?');
$stmt->bind_param('ii', $nuevoActivo, $id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    registrarLog(
        $conn,
        $_SESSION['id'] ?? null,
        'clientes',
        $nuevoActivo === 1 ? 'activar' : 'desactivar',
        sprintf('Se %s el cliente #%d.', $nuevoActivo === 1 ? 'activó' : 'desactivó', $id),
        'Cliente',
        (string) $id
    );
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to execute the query']);
}

$conn->close();
?>
