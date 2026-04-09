<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

// Datos del formulario
$idPsicologo = $_POST['IdUsuario'];
$nuevaFecha = $_POST['resumenFecha'];
$tiempoRaw = $_POST['resumenTiempo'] ?? 60;
$tiempo = (int) $tiempoRaw;
if ($tiempo <= 0) {
    $tiempo = 60;
}

// Convertir la fecha a formato datetime
$inicioNuevo = new DateTime($nuevaFecha);
$finNuevo = clone $inicioNuevo;
$finNuevo->modify('+' . $tiempo . ' minutes');

$inicioSql = $inicioNuevo->format('Y-m-d H:i:s');
$finSql = $finNuevo->format('Y-m-d H:i:s');

// Consulta para verificar si hay citas que se empalmen.
// Regla de empalme: existente.inicio < nuevo.fin && existente.fin > nuevo.inicio
$sql = "SELECT Cita.Programado, nino.name
        FROM Cita
        INNER JOIN nino ON Cita.IdNino = nino.id
        WHERE Cita.IdUsuario = ?
          AND Cita.Estatus <> 1
          AND Cita.Programado < ?
          AND DATE_ADD(Cita.Programado, INTERVAL COALESCE(Cita.Tiempo, 60) MINUTE) > ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $idPsicologo, $finSql, $inicioSql);
$stmt->execute();
$stmt->bind_result($fecha, $nombre);

// Almacenar resultados
$citasAfectadas = [];
while ($stmt->fetch()) {
    $citasAfectadas[] = array('fecha' => $fecha, 'name' => $nombre);
}

if (count($citasAfectadas) > 0) {
    // Hay superposición de citas
    echo json_encode(array('success' => false, 'message' => 'Ya existe una cita que se empalma con este horario.', 'citas' => $citasAfectadas));
} else {
    // No hay superposición
    echo json_encode(array('success' => true, 'message' => 'La cita es válida.'));
}

$stmt->close();
$conn->close();
?>
