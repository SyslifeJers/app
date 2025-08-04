<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM Usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
echo json_encode($user);

$conn->close();
?>