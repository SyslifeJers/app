<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$id = $_POST['id'];
$name = $_POST['name'];
$user = $_POST['user'];
$pass = $_POST['pass'];
$telefono = $_POST['telefono'];
$correo = $_POST['correo'];
$IdRol = $_POST['editRol'];

$stmt = $conn->prepare("UPDATE Usuarios SET name = ?, user = ?, pass = ?, telefono = ?, correo = ?, IdRol = ? WHERE id = ?");
$stmt->bind_param("sssssii", $name, $user, $pass, $telefono, $correo, $IdRol, $id);
$success = $stmt->execute();

echo json_encode(['success' => $success]);

$conn->close();
?>