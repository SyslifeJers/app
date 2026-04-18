<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/ticket_resumen.php';

header('Content-Type: application/json; charset=utf-8');

$cita_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($cita_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de cita no valido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = conectar();
    $resumen = obtenerResumenTicketCita($conn, $cita_id);
    $cita = $resumen['cita'];
    $pagos = $resumen['pagos'];
    $totales = $resumen['totales'];

    $json = [
        'ticket' => [
            'id' => (int) $cita['id'],
            'clinic' => [
                'name' => 'CLINICA CERENE',
            ],
            'cita' => [
                'programado' => $cita['Programado'],
                'fecha' => $cita['fecha'],
                'hora' => $cita['hora'],
                'tipo' => $cita['Tipo'],
                'formaPago' => $cita['FormaPago'],
                'costo' => (float) $cita['costo'],
                'estatus' => [
                    'id' => (int) $cita['estatus_id'],
                    'name' => $cita['estatus_nombre'],
                ],
            ],
            'paciente' => [
                'id' => (int) $cita['IdNino'],
                'name' => $cita['paciente_nombre'],
            ],
            'psicologo' => [
                'id' => (int) $cita['psicologo_id'],
                'name' => $cita['psicologo_nombre'],
            ],
            'pagos' => $pagos,
            'totals' => [
                'currency' => $totales['currency'],
                'appointmentTotal' => $totales['appointmentTotal'],
                'receivedTotal' => $totales['receivedTotal'],
                'appliedToAppointment' => $totales['appliedToAppointment'],
                'creditBalanceAdded' => $totales['creditBalanceAdded'],
                'dueTotal' => $totales['dueTotal'],
            ],
        ],
    ];

    echo json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $conn->close();
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'No se encontró la cita con el ID especificado.') {
        http_response_code(404);
        echo json_encode(['error' => 'Cita no encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
