<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['error' => 'Método no permitido. Usa POST.']);
}

$pacienteId = isset($jsonBody['paciente_id']) ? (int) $jsonBody['paciente_id'] : 0;
if ($pacienteId <= 0) {
    jsonResponse(400, ['error' => 'paciente_id es requerido.']);
}

$clienteId = array_key_exists('cliente_id', $jsonBody) ? (int) $jsonBody['cliente_id'] : null;
$totalCompletadas = isset($jsonBody['total_completadas']) ? (int) $jsonBody['total_completadas'] : 0;
$totalCanceladas = isset($jsonBody['total_canceladas']) ? (int) $jsonBody['total_canceladas'] : 0;
$calificacion = isset($jsonBody['calificacion']) ? (float) $jsonBody['calificacion'] : 0;
$ultimaCita = $jsonBody['ultima_cita'] ?? null;

$estatusId = isset($jsonBody['estatus_id']) ? (int) $jsonBody['estatus_id'] : 2;
if ($estatusId <= 0) {
    $estatusId = 2;
}

$promocionId = array_key_exists('promocion_id', $jsonBody) && $jsonBody['promocion_id'] !== null
    ? (int) $jsonBody['promocion_id']
    : null;

$notas = isset($jsonBody['notas']) ? trim((string) $jsonBody['notas']) : '';
$promocionTexto = isset($jsonBody['promocion_texto']) ? trim((string) $jsonBody['promocion_texto']) : '';
if ($promocionTexto !== '') {
    $notas = "PROMO: {$promocionTexto}\n\n" . $notas;
}

$origenReporte = 'app_seguimiento';

$sql = 'INSERT INTO ProspectosSeguimiento (
            paciente_id,
            cliente_id,
            total_completadas,
            total_canceladas,
            calificacion,
            ultima_cita,
            estatus_id,
            promocion_id,
            notas,
            origen_reporte,
            fecha_alta,
            fecha_actualizacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            cliente_id = VALUES(cliente_id),
            total_completadas = VALUES(total_completadas),
            total_canceladas = VALUES(total_canceladas),
            calificacion = VALUES(calificacion),
            ultima_cita = VALUES(ultima_cita),
            estatus_id = VALUES(estatus_id),
            promocion_id = VALUES(promocion_id),
            notas = VALUES(notas),
            origen_reporte = VALUES(origen_reporte),
            fecha_actualizacion = NOW()';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar el guardado del seguimiento.']);
}

$stmt->bind_param(
    'iiiidsiiss',
    $pacienteId,
    $clienteId,
    $totalCompletadas,
    $totalCanceladas,
    $calificacion,
    $ultimaCita,
    $estatusId,
    $promocionId,
    $notas,
    $origenReporte
);

if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible guardar el seguimiento.', 'detalle' => $error]);
}
$stmt->close();

// Obtener id del prospecto (para historial)
$stmt = $conn->prepare('SELECT id FROM ProspectosSeguimiento WHERE paciente_id = ? LIMIT 1');
if ($stmt === false) {
    jsonResponse(500, ['error' => 'Se guardó, pero no fue posible localizar el prospecto.']);
}

$stmt->bind_param('i', $pacienteId);
if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'Se guardó, pero no fue posible ejecutar la búsqueda del prospecto.']);
}

$res = $stmt->get_result();
$filaId = $res ? $res->fetch_assoc() : null;
$stmt->close();

$prospectoId = $filaId && isset($filaId['id']) ? (int) $filaId['id'] : 0;
if ($prospectoId > 0) {
    $origen = 'app';
    $usuarioId = isset($jsonBody['usuario_id']) ? (int) $jsonBody['usuario_id'] : null;
    $canal = isset($jsonBody['canal']) ? trim((string) $jsonBody['canal']) : '';
    if ($canal !== '') {
        $origen = substr($canal, 0, 50);
    }

    $stmt = $conn->prepare('INSERT INTO ProspectosSeguimientoHistorial (
                                prospecto_id,
                                paciente_id,
                                cliente_id,
                                estatus_id,
                                promocion_id,
                                promocion_texto,
                                notas,
                                calificacion,
                                origen,
                                usuario_id,
                                creado_en
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    if ($stmt !== false) {
        $stmt->bind_param(
            'iiiiissdsi',
            $prospectoId,
            $pacienteId,
            $clienteId,
            $estatusId,
            $promocionId,
            $promocionTexto,
            $notas,
            $calificacion,
            $origen,
            $usuarioId
        );
        $stmt->execute();
        $stmt->close();
    }
}

$sqlDetalle = 'SELECT ps.id,
                     ps.paciente_id,
                     n.name AS paciente,
                     ps.cliente_id,
                     c.name AS cliente,
                     ps.total_completadas,
                     ps.total_canceladas,
                     ps.calificacion,
                     ps.ultima_cita,
                     ps.estatus_id,
                     es.nombre AS estatus_seguimiento,
                     ps.promocion_id,
                     pr.nombre AS promocion,
                     ps.notas,
                     ps.fecha_alta,
                     ps.fecha_actualizacion
              FROM ProspectosSeguimiento ps
              LEFT JOIN nino n ON n.id = ps.paciente_id
              LEFT JOIN Clientes c ON c.id = ps.cliente_id
              LEFT JOIN ProspectoEstatusSeguimiento es ON es.id = ps.estatus_id
              LEFT JOIN PromocionesCatalogo pr ON pr.id = ps.promocion_id
              WHERE ps.paciente_id = ?
              LIMIT 1';

$stmt = $conn->prepare($sqlDetalle);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'Se guardó, pero no fue posible leer el detalle.']);
}

$stmt->bind_param('i', $pacienteId);
if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'Se guardó, pero no fue posible ejecutar el detalle.']);
}

$resultado = $stmt->get_result();
$fila = $resultado ? $resultado->fetch_assoc() : null;
$stmt->close();

jsonResponse(200, [
    'ok' => true,
    'data' => $fila,
]);
