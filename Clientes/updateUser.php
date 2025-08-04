<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$id = $_POST['id'];
$name = $_POST['name'];
$telefono = $_POST['telefono'];
$correo = $_POST['correo'];

$stmt = $conn->prepare("UPDATE `Clientes` SET `name`=?, `telefono`=?, `correo`=? WHERE `id` = ?");
$stmt->bind_param("sssi", $name, $telefono, $correo, $id);
$success = $stmt->execute();

echo json_encode(['success' => $success]);

$conn->close();
?>