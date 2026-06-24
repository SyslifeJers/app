<?php
require_once __DIR__ . '/../conexion.php';

$conn = conectar();
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    $conn->close();
    header('Location: index.php');
    exit;
}

$stmtUso = $conn->prepare('SELECT (SELECT COUNT(*) FROM ServiciosPaciente WHERE precio_id = ?) + (SELECT COUNT(*) FROM Cita WHERE LOWER(TRIM(Tipo)) = LOWER(TRIM((SELECT name FROM Precios WHERE id = ?)))) AS total_uso');
if (!$stmtUso) {
    $conn->close();
    header('Location: index.php');
    exit;
}
$stmtUso->bind_param('ii', $id, $id);
$stmtUso->execute();
$stmtUso->bind_result($totalUso);
$stmtUso->fetch();
$stmtUso->close();

if ((int) $totalUso === 0) {
    $stmt = $conn->prepare('DELETE FROM Precios WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

$conn->close();
header('Location: index.php');
exit;
