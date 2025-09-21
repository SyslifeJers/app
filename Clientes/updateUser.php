<?php
session_start();

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/logger.php';
$conn = conectar();

$id = $_POST['id'];
$name = $_POST['name'];
$telefono = $_POST['telefono'];
$correo = $_POST['correo'];

$stmt = $conn->prepare("UPDATE `Clientes` SET `name`=?, `telefono`=?, `correo`=? WHERE `id` = ?");
$stmt->bind_param("sssi", $name, $telefono, $correo, $id);
$success = $stmt->execute();

if ($success) {
    registrarLog(
        $conn,
        $_SESSION['id'] ?? null,
        'clientes',
        'actualizar',
        sprintf('Se actualizaron los datos del cliente #%d (%s).', $id, $name),
        'Cliente',
        (string) $id
    );
}

echo json_encode(['success' => $success]);

$conn->close();
?>