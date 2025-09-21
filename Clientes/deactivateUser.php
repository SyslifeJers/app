<?php
session_start();

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/logger.php';
$conn = conectar();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID is required']);
    exit;
}

$id = $_GET['id'];

$stmt = $conn->prepare("SELECT activo FROM Clientes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Client not found']);
    exit;
}

$nuevoActivo = $row['activo'] == 1 ? 0 : 1;

$stmt = $conn->prepare("UPDATE Clientes SET activo = ? WHERE id = ?");
$stmt->bind_param("ii", $nuevoActivo, $id);
$success = $stmt->execute();

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
