<?php
// Datos de la conexión
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once 'conexion.php';
$conn = conectar();
session_start();

date_default_timezone_set('America/Mexico_City');
$fechaActual = date('Y-m-d H:i:s'); // Formato de fecha y hora actual

$idUsuario = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $citaId = $_POST['citaId'];
    $fechaProgramada = $_POST['fechaProgramada'];

    $stmt = $conn->prepare("UPDATE Cita SET Programado = ?, Estatus = 3 WHERE id = ?");
    $stmt->bind_param('si', $fechaProgramada, $citaId);
    $stmt->execute();
    $stmt->close();

    $stmtInsert = $conn->prepare("INSERT INTO HistorialEstatus(id, fecha, idEstatus, idCita, idUsuario) VALUES (null, ?, 3, ?, ?)");
    $stmtInsert->bind_param('sii', $fechaActual, $citaId, $idUsuario);
    $stmtInsert->execute();
    $stmtInsert->close();

    echo "Fecha de cita actualizada correctamente.";
    header("Location:index.php");
}

$conn->close();
?>
