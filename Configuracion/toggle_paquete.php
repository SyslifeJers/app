<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: paquetes.php');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$activoActual = isset($_POST['activo']) ? (int) $_POST['activo'] : 0;

if ($id <= 0) {
    $_SESSION['paquetes_mensaje'] = 'Identificador de paquete inválido.';
    $_SESSION['paquetes_tipo'] = 'danger';
    header('Location: paquetes.php');
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/logger.php';

$conn = conectar();
$conn->set_charset('utf8');

$nuevoEstado = $activoActual === 1 ? 0 : 1;
$stmt = $conn->prepare('UPDATE Paquetes SET activo = ? WHERE id = ?');
if ($stmt === false) {
    $_SESSION['paquetes_mensaje'] = 'No fue posible preparar el cambio de estado.';
    $_SESSION['paquetes_tipo'] = 'danger';
    header('Location: paquetes.php');
    exit;
}

$stmt->bind_param('ii', $nuevoEstado, $id);

if ($stmt->execute()) {
    $_SESSION['paquetes_mensaje'] = $nuevoEstado === 1 ? 'Paquete activado correctamente.' : 'Paquete desactivado correctamente.';
    $_SESSION['paquetes_tipo'] = 'success';

    registrarLog(
        $conn,
        $_SESSION['id'] ?? null,
        'paquetes',
        'actualizar',
        sprintf('El paquete #%d cambió su estado a %s.', $id, $nuevoEstado === 1 ? 'activo' : 'inactivo'),
        'Paquete',
        (string) $id
    );
} else {
    $_SESSION['paquetes_mensaje'] = 'No fue posible actualizar el paquete: ' . $stmt->error;
    $_SESSION['paquetes_tipo'] = 'danger';
}

$stmt->close();
$conn->close();

header('Location: paquetes.php');
exit;
