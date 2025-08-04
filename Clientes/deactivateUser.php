<?php
require_once __DIR__ . '/../conexion.php';
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
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to execute the query']);
}

$conn->close();
?>
