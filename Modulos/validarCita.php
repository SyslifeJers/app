<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

// Datos del formulario
$idPsicologo = $_POST['IdUsuario'];
$nuevaFecha = $_POST['resumenFecha'];

// Convertir la fecha a formato datetime
$nuevaFechaDatetime = new DateTime($nuevaFecha);

// Calcular el rango de fechas
$fechaInicio = $nuevaFechaDatetime->modify('-1 hour')->format('Y-m-d H:i:s');
$fechaFin = $nuevaFechaDatetime->modify('+1 hour')->format('Y-m-d H:i:s');

// Consulta para verificar si hay citas en el rango
$sql = "SELECT Cita.Programado, nino.name 
        FROM Cita
        INNER JOIN nino ON Cita.IdNino = nino.id 
        WHERE Cita.IdUsuario = ? AND Cita.Programado BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $idPsicologo, $fechaInicio, $fechaFin);
$stmt->execute();
$stmt->bind_result($fecha, $nombre);

// Almacenar resultados
$citasAfectadas = [];
while ($stmt->fetch()) {
    $citasAfectadas[] = array('fecha' => $fecha, 'name' => $nombre);
}

if (count($citasAfectadas) > 0) {
    // Hay superposición de citas
    echo json_encode(array('success' => false, 'message' => 'Ya existe una cita en el rango de 1 hora', 'citas' => $citasAfectadas));
} else {
    // No hay superposición
    echo json_encode(array('success' => true, 'message' => 'La cita es válida.'));
}

$stmt->close();
$conn->close();
?>