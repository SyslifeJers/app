<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/conflictos_agenda.php';
$conn = conectar();

// Datos del formulario
$idPsicologo = $_POST['IdUsuario'];
$idPaciente = isset($_POST['IdNino']) ? (int) $_POST['IdNino'] : 0;
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

try {
    $conflicto = obtenerConflictoAgendaPsicologo($conn, (int) $idPsicologo, $inicioSql, $tiempo, null, $idPaciente > 0 ? $idPaciente : null);
} catch (Throwable $e) {
    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
    $conn->close();
    exit;
}

if ($conflicto !== null) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Ya existe una cita o reservación que se empalma con este horario.',
        'citas' => array(array(
            'fecha' => (string) ($conflicto['programado'] ?? ''),
            'name' => (string) ($conflicto['paciente'] ?? ''),
            'source_type' => (string) ($conflicto['source_type'] ?? 'cita')
        ))
    ));
} else {
    echo json_encode(array('success' => true, 'message' => 'La cita es válida.'));
}

$conn->close();
?>
