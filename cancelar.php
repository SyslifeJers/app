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
    $estatus = $_POST['estatus'];
    $formaPago = $_POST['formaPago'];

    // Actualizar la cita con la nueva fecha programada y la forma de pago
    $stmt = $conn->prepare("UPDATE Cita SET FormaPago = ?, Estatus = ? WHERE id = ?");
    $stmt->bind_param('sii', $formaPago, $estatus, $citaId);
    $stmt->execute();
    $stmt->close();

    // Insertar en el historial de estatus
    $stmtInsert = $conn->prepare("INSERT INTO HistorialEstatus(id, fecha, idEstatus, idCita, idUsuario) VALUES (null, ?, 3, ?, ?)");
    $stmtInsert->bind_param('sii', $fechaActual, $citaId, $idUsuario);
    $stmtInsert->execute();
    $stmtInsert->close();

    echo "Fecha de cita y forma de pago actualizadas correctamente.";
    header("Location: index.php");
    exit;
}

$conn->close();
?>
