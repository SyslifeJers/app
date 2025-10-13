<?php
session_start();

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/logger.php';
$conn = conectar();

$id = $_POST['id'];
$name = $_POST['name'];
$user = $_POST['user'];
$pass = $_POST['pass'];
$telefono = $_POST['telefono'];
$correo = $_POST['correo'];
$IdRol = $_POST['editRol'];
$colorId = isset($_POST['color_id']) && $_POST['color_id'] !== '' ? (int) $_POST['color_id'] : 0;

$stmt = $conn->prepare("UPDATE Usuarios SET name = ?, user = ?, pass = ?, telefono = ?, correo = ?, IdRol = ?, color_id = NULLIF(?, 0) WHERE id = ?");
$stmt->bind_param("sssssiii", $name, $user, $pass, $telefono, $correo, $IdRol, $colorId, $id);
$success = $stmt->execute();

if ($success) {
    registrarLog(
        $conn,
        $_SESSION['id'] ?? null,
        'usuarios',
        'actualizar',
        sprintf('Se actualizaron los datos del usuario #%d (%s).', $id, $user),
        'Usuario',
        (string) $id
    );
}

echo json_encode(['success' => $success]);

$conn->close();
?>