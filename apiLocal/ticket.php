<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/ticket_resumen.php';

$cita_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($cita_id <= 0) {
    die('ID de cita no valido.');
}

try {
    $conn = conectar();
    $resumen = obtenerResumenTicketCita($conn, $cita_id);
    $ticket = construirTextoTicketCita($resumen, 'Centro de rehabilitacion Psicologia y Neurodesarrollo');

    echo json_encode(['ticket' => $ticket], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $conn->close();
} catch (Exception $e) {
    echo 'No se pudo imprimir el ticket: ' . $e->getMessage();
}
