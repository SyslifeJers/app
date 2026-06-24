<?php
require_once __DIR__ . '/../conexion.php';

$conn = conectar();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$costo = isset($_POST['costo']) ? (float) $_POST['costo'] : -1;
$sesionesTotal = isset($_POST['sesiones_total']) ? (int) $_POST['sesiones_total'] : 1;
$requiereCita = isset($_POST['requiere_cita']) ? 1 : 0;
$permiteSinFecha = isset($_POST['permite_sin_fecha']) ? 1 : 0;

if ($id <= 0 || $name === '' || $costo < 0 || $sesionesTotal < 0) {
    $conn->close();
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare('UPDATE Precios SET name = ?, costo = ?, sesiones_total = ?, requiere_cita = ?, permite_sin_fecha = ? WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('sdiiii', $name, $costo, $sesionesTotal, $requiereCita, $permiteSinFecha, $id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
header('Location: index.php');
exit;
