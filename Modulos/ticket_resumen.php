<?php

function obtenerResumenTicketCita(mysqli $conn, int $citaId): array
{
    $sqlCita = "SELECT
                    ci.id,
                    ci.IdNino,
                    n.name AS paciente_nombre,
                    us.id AS psicologo_id,
                    us.name AS psicologo_nombre,
                    ci.costo,
                    ci.Programado,
                    DATE_FORMAT(DATE(ci.Programado), '%d-%m-%Y') AS fecha,
                    TIME(ci.Programado) AS hora,
                    ci.Tipo,
                    ci.FormaPago,
                    es.id AS estatus_id,
                    es.name AS estatus_nombre
                FROM Cita ci
                INNER JOIN nino n ON n.id = ci.IdNino
                INNER JOIN Usuarios us ON us.id = ci.IdUsuario
                INNER JOIN Estatus es ON es.id = ci.Estatus
                WHERE ci.id = ?
                LIMIT 1";

    $stmt = $conn->prepare($sqlCita);
    if (!$stmt) {
        throw new RuntimeException('No se pudo consultar la cita del ticket.');
    }

    $stmt->bind_param('i', $citaId);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $cita = $resultado->fetch_assoc();
    $stmt->close();

    if (!$cita) {
        throw new RuntimeException('No se encontró la cita con el ID especificado.');
    }

    $sqlPagos = "SELECT id, cita_id, metodo, monto, registrado_por, creado_en
                 FROM CitaPagos
                 WHERE cita_id = ?
                 ORDER BY creado_en ASC, id ASC";
    $stmtPagos = $conn->prepare($sqlPagos);
    if (!$stmtPagos) {
        throw new RuntimeException('No se pudieron consultar los pagos de la cita.');
    }

    $stmtPagos->bind_param('i', $citaId);
    $stmtPagos->execute();
    $resultadoPagos = $stmtPagos->get_result();

    $pagos = [];
    $pagadoTotal = 0.0;
    while ($filaPago = $resultadoPagos->fetch_assoc()) {
        $monto = (float) ($filaPago['monto'] ?? 0);
        $pagadoTotal += $monto;
        $pagos[] = [
            'id' => (int) ($filaPago['id'] ?? 0),
            'cita_id' => (int) ($filaPago['cita_id'] ?? 0),
            'metodo' => (string) ($filaPago['metodo'] ?? ''),
            'monto' => $monto,
            'registrado_por' => isset($filaPago['registrado_por']) ? (int) $filaPago['registrado_por'] : 0,
            'creado_en' => (string) ($filaPago['creado_en'] ?? ''),
        ];
    }
    $stmtPagos->close();

    $costo = (float) ($cita['costo'] ?? 0);
    $aplicadoCita = min($costo, $pagadoTotal);
    $saldoFavor = max(0.0, $pagadoTotal - $costo);
    $adeudo = max(0.0, $costo - $pagadoTotal);

    return [
        'cita' => $cita,
        'pagos' => $pagos,
        'totales' => [
            'currency' => 'MXN',
            'appointmentTotal' => $costo,
            'receivedTotal' => $pagadoTotal,
            'appliedToAppointment' => $aplicadoCita,
            'creditBalanceAdded' => $saldoFavor,
            'dueTotal' => $adeudo,
        ],
    ];
}

function construirTextoTicketCita(array $resumen, string $subtituloClinica): string
{
    $cita = $resumen['cita'];
    $totales = $resumen['totales'];
    $pagos = $resumen['pagos'];

    $lineas = [
        'Clinica Cerene',
        $subtituloClinica,
        'Fecha: ' . date('d-m-Y H:i:s'),
        'Cliente: ' . (string) ($cita['paciente_nombre'] ?? ''),
        'Psicologo: ' . (string) ($cita['psicologo_nombre'] ?? ''),
        'Costo cita: $' . number_format((float) $totales['appointmentTotal'], 2),
        'Monto recibido: $' . number_format((float) $totales['receivedTotal'], 2),
        'Aplicado a cita: $' . number_format((float) $totales['appliedToAppointment'], 2),
    ];

    if ((float) $totales['creditBalanceAdded'] > 0.009) {
        $lineas[] = 'Saldo a favor: $' . number_format((float) $totales['creditBalanceAdded'], 2);
    }

    if ((float) $totales['dueTotal'] > 0.009) {
        $lineas[] = 'Adeudo restante: $' . number_format((float) $totales['dueTotal'], 2);
    }

    $lineas[] = 'Fecha de cita: ' . (string) ($cita['fecha'] ?? '');
    $lineas[] = 'Hora de cita: ' . (string) ($cita['hora'] ?? '');
    $lineas[] = 'Tipo de servicio: ' . (string) ($cita['Tipo'] ?? '');
    $lineas[] = 'Forma de pago: ' . (string) ($cita['FormaPago'] ?? 'Sin registrar');

    if ($pagos !== []) {
        $lineas[] = 'Detalle de pagos:';
        foreach ($pagos as $pago) {
            $lineas[] = '- ' . (string) ($pago['metodo'] ?? '') . ' $' . number_format((float) ($pago['monto'] ?? 0), 2);
        }
    }

    return implode("\n", $lineas);
}
