<?php

date_default_timezone_set('America/Mexico_City');

function obtenerNombrePacienteResumen(mysqli $conn, ?int $pacienteId, string $pacienteNombre): string
{
    $pacienteNombre = trim($pacienteNombre);
    if ($pacienteNombre !== '' || $pacienteId === null || $pacienteId <= 0) {
        return mb_substr($pacienteNombre, 0, 150, 'UTF-8');
    }

    $stmt = $conn->prepare('SELECT name FROM nino WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('i', $pacienteId);
    $stmt->execute();
    $stmt->bind_result($nombre);
    $resultado = $stmt->fetch() ? (string) $nombre : '';
    $stmt->close();

    return mb_substr(trim($resultado), 0, 150, 'UTF-8');
}

function obtenerNombrePsicologoResumen(mysqli $conn, ?int $psicologoId, string $psicologoNombre): string
{
    $psicologoNombre = trim($psicologoNombre);
    if ($psicologoNombre !== '' || $psicologoId === null || $psicologoId <= 0) {
        return mb_substr($psicologoNombre, 0, 150, 'UTF-8');
    }

    $stmt = $conn->prepare('SELECT name FROM Usuarios WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('i', $psicologoId);
    $stmt->execute();
    $stmt->bind_result($nombre);
    $resultado = $stmt->fetch() ? (string) $nombre : '';
    $stmt->close();

    return mb_substr(trim($resultado), 0, 150, 'UTF-8');
}

function registrarResumenPago(mysqli $conn, array $data): void
{
    $origen = isset($data['origen']) ? trim((string) $data['origen']) : '';
    $referenciaId = isset($data['referencia_id']) ? (int) $data['referencia_id'] : 0;
    $monto = isset($data['monto']) ? (float) $data['monto'] : 0.0;
    $metodoPago = isset($data['metodo_pago']) ? trim((string) $data['metodo_pago']) : '';

    if ($origen === '' || $referenciaId <= 0 || $monto <= 0 || $metodoPago === '') {
        throw new RuntimeException('No fue posible preparar el resumen del pago.');
    }

    $fechaPago = isset($data['fecha_pago']) ? trim((string) $data['fecha_pago']) : '';
    if ($fechaPago === '') {
        $fechaPago = date('Y-m-d H:i:s');
    }

    $fechaCorte = isset($data['fecha_corte']) ? trim((string) $data['fecha_corte']) : '';
    if ($fechaCorte === '') {
        $fechaCorte = substr($fechaPago, 0, 10);
    }

    $pacienteId = isset($data['paciente_id']) && $data['paciente_id'] !== null ? (int) $data['paciente_id'] : null;
    $psicologoId = isset($data['psicologo_id']) && $data['psicologo_id'] !== null ? (int) $data['psicologo_id'] : null;
    $pacienteNombre = obtenerNombrePacienteResumen($conn, $pacienteId, (string) ($data['paciente_nombre'] ?? ''));
    $psicologoNombre = obtenerNombrePsicologoResumen($conn, $psicologoId, (string) ($data['psicologo_nombre'] ?? ''));
    $registradoPor = isset($data['registrado_por']) && $data['registrado_por'] !== null ? (int) $data['registrado_por'] : null;
    $citaId = isset($data['cita_id']) && $data['cita_id'] !== null ? (int) $data['cita_id'] : null;
    $diagnosticoId = isset($data['diagnostico_id']) && $data['diagnostico_id'] !== null ? (int) $data['diagnostico_id'] : null;
    $adeudoId = isset($data['adeudo_id']) && $data['adeudo_id'] !== null ? (int) $data['adeudo_id'] : null;
    $observaciones = mb_substr(trim((string) ($data['observaciones'] ?? '')), 0, 255, 'UTF-8');
    $observaciones = $observaciones !== '' ? $observaciones : null;

    $stmt = $conn->prepare('INSERT INTO PagoResumenDiario (origen, referencia_id, cita_id, diagnostico_id, adeudo_id, paciente_id, paciente_nombre, psicologo_id, psicologo_nombre, monto, metodo_pago, fecha_pago, fecha_corte, registrado_por, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('No fue posible preparar el resumen del pago.');
    }

    $stmt->bind_param(
        'siiiiisisdsssis',
        $origen,
        $referenciaId,
        $citaId,
        $diagnosticoId,
        $adeudoId,
        $pacienteId,
        $pacienteNombre,
        $psicologoId,
        $psicologoNombre,
        $monto,
        $metodoPago,
        $fechaPago,
        $fechaCorte,
        $registradoPor,
        $observaciones
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('No fue posible guardar el resumen del pago.');
    }

    $stmt->close();
}
