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

$stmt = $conn->prepare("SELECT activo FROM Usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    $new_activo = $user['activo'] == 1 ? 0 : 1;

    $stmt = $conn->prepare("UPDATE Usuarios SET activo = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_activo, $id);
    $success = $stmt->execute();

    if ($success) {
        registrarLog(
            $conn,
            $_SESSION['id'] ?? null,
            'usuarios',
            $new_activo === 1 ? 'activar' : 'desactivar',
            sprintf('Se %s el usuario #%d.', $new_activo === 1 ? 'activó' : 'desactivó', $id),
            'Usuario',
            (string) $id
        );
        echo json_encode(['success' => true, 'new_activo' => $new_activo]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to execute the update query']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'User not found']);
}

$conn->close();
?>
