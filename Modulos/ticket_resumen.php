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
                    ci.diagnostico_id,
                    ci.diagnostico_sesion,
                    ci.servicio_paciente_id,
                    ci.servicio_sesion,
                    sp.total AS servicio_total,
                    sp.saldo_restante AS servicio_saldo_restante,
                    sp.sesiones_total AS servicio_sesiones_total,
                    sp.sesiones_completadas AS servicio_sesiones_completadas,
                    pr.name AS servicio_nombre,
                    d.id AS diagnostico_legacy_id,
                    d.total AS diagnostico_total,
                    d.saldo_restante AS diagnostico_saldo_restante,
                    d.sesiones_total AS diagnostico_sesiones_total,
                    d.sesiones_completadas AS diagnostico_sesiones_completadas,
                    es.id AS estatus_id,
                    es.name AS estatus_nombre
                FROM Cita ci
                INNER JOIN nino n ON n.id = ci.IdNino
                INNER JOIN Usuarios us ON us.id = ci.IdUsuario
                INNER JOIN Estatus es ON es.id = ci.Estatus
                LEFT JOIN ServiciosPaciente sp ON sp.id = ci.servicio_paciente_id
                LEFT JOIN Precios pr ON pr.id = sp.precio_id
                LEFT JOIN Diagnosticos d ON d.id = ci.diagnostico_id OR d.cita_inicial_id = ci.id
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
            'origen' => 'cita',
            'metodo' => (string) ($filaPago['metodo'] ?? ''),
            'monto' => $monto,
            'registrado_por' => isset($filaPago['registrado_por']) ? (int) $filaPago['registrado_por'] : 0,
            'creado_en' => (string) ($filaPago['creado_en'] ?? ''),
        ];
    }
    $stmtPagos->close();

    $servicioPacienteId = isset($cita['servicio_paciente_id']) && $cita['servicio_paciente_id'] !== null
        ? (int) $cita['servicio_paciente_id']
        : 0;

    if ($servicioPacienteId > 0) {
        $sqlPagosServicio = "SELECT id, servicio_paciente_id, cita_id, metodo, monto, registrado_por, creado_en
                             FROM ServicioPagos
                             WHERE servicio_paciente_id = ?
                               AND (cita_id = ? OR cita_id IS NULL)
                             ORDER BY creado_en ASC, id ASC";
        $stmtPagosServicio = $conn->prepare($sqlPagosServicio);
        if (!$stmtPagosServicio) {
            throw new RuntimeException('No se pudieron consultar los pagos del servicio.');
        }

        $stmtPagosServicio->bind_param('ii', $servicioPacienteId, $citaId);
        $stmtPagosServicio->execute();
        $resultadoPagosServicio = $stmtPagosServicio->get_result();

        while ($filaPagoServicio = $resultadoPagosServicio->fetch_assoc()) {
            $monto = (float) ($filaPagoServicio['monto'] ?? 0);
            $pagadoTotal += $monto;
            $pagos[] = [
                'id' => (int) ($filaPagoServicio['id'] ?? 0),
                'cita_id' => $filaPagoServicio['cita_id'] !== null ? (int) $filaPagoServicio['cita_id'] : null,
                'servicio_paciente_id' => (int) ($filaPagoServicio['servicio_paciente_id'] ?? 0),
                'origen' => 'servicio',
                'metodo' => (string) ($filaPagoServicio['metodo'] ?? ''),
                'monto' => $monto,
                'registrado_por' => isset($filaPagoServicio['registrado_por']) ? (int) $filaPagoServicio['registrado_por'] : 0,
                'creado_en' => (string) ($filaPagoServicio['creado_en'] ?? ''),
            ];
        }
        $stmtPagosServicio->close();
    }

    $diagnosticoLegacyId = isset($cita['diagnostico_legacy_id']) && $cita['diagnostico_legacy_id'] !== null
        ? (int) $cita['diagnostico_legacy_id']
        : 0;

    if ($servicioPacienteId === 0 && $diagnosticoLegacyId > 0) {
        $sqlPagosDiagnostico = "SELECT id, diagnostico_id, metodo, monto, registrado_por, creado_en
                                FROM DiagnosticoPagos
                                WHERE diagnostico_id = ?
                                ORDER BY creado_en ASC, id ASC";
        $stmtPagosDiagnostico = $conn->prepare($sqlPagosDiagnostico);
        if (!$stmtPagosDiagnostico) {
            throw new RuntimeException('No se pudieron consultar los pagos del diagnostico.');
        }

        $stmtPagosDiagnostico->bind_param('i', $diagnosticoLegacyId);
        $stmtPagosDiagnostico->execute();
        $resultadoPagosDiagnostico = $stmtPagosDiagnostico->get_result();

        while ($filaPagoDiagnostico = $resultadoPagosDiagnostico->fetch_assoc()) {
            $monto = (float) ($filaPagoDiagnostico['monto'] ?? 0);
            $pagadoTotal += $monto;
            $pagos[] = [
                'id' => (int) ($filaPagoDiagnostico['id'] ?? 0),
                'cita_id' => null,
                'diagnostico_id' => (int) ($filaPagoDiagnostico['diagnostico_id'] ?? 0),
                'origen' => 'diagnostico',
                'metodo' => (string) ($filaPagoDiagnostico['metodo'] ?? ''),
                'monto' => $monto,
                'registrado_por' => isset($filaPagoDiagnostico['registrado_por']) ? (int) $filaPagoDiagnostico['registrado_por'] : 0,
                'creado_en' => (string) ($filaPagoDiagnostico['creado_en'] ?? ''),
            ];
        }
        $stmtPagosDiagnostico->close();
    }

    $costo = (float) ($cita['costo'] ?? 0);
    if ($servicioPacienteId > 0) {
        $servicioTotal = (float) ($cita['servicio_total'] ?? 0);
        $servicioRestante = (float) ($cita['servicio_saldo_restante'] ?? 0);
        $aplicadoCita = max(0.0, $servicioTotal - $servicioRestante);
        $saldoFavor = 0.0;
        $adeudo = max(0.0, $servicioRestante);
        $costo = $servicioTotal;
    } elseif ($diagnosticoLegacyId > 0) {
        $diagnosticoTotal = (float) ($cita['diagnostico_total'] ?? $costo);
        $diagnosticoRestante = (float) ($cita['diagnostico_saldo_restante'] ?? max(0.0, $diagnosticoTotal - $pagadoTotal));
        $aplicadoCita = max(0.0, $diagnosticoTotal - $diagnosticoRestante);
        $saldoFavor = 0.0;
        $adeudo = max(0.0, $diagnosticoRestante);
        $costo = $diagnosticoTotal;
    } else {
        $aplicadoCita = min($costo, $pagadoTotal);
        $saldoFavor = max(0.0, $pagadoTotal - $costo);
        $adeudo = max(0.0, $costo - $pagadoTotal);
    }

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
