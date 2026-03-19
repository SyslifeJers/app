<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['error' => 'Método no permitido. Usa POST.']);
}

$id = isset($jsonBody['id']) ? (int) $jsonBody['id'] : 0;

$scope = isset($jsonBody['scope']) ? trim((string) $jsonBody['scope']) : 'paciente';
if ($scope === '') {
    $scope = 'paciente';
}

$pacienteId = isset($jsonBody['paciente_id']) ? (int) $jsonBody['paciente_id'] : 0;
if ($pacienteId <= 0) {
    $pacienteId = null;
}

$seguimientoId = array_key_exists('seguimiento_id', $jsonBody) && $jsonBody['seguimiento_id'] !== null
    ? (int) $jsonBody['seguimiento_id']
    : 0;
$clienteId = array_key_exists('cliente_id', $jsonBody) && $jsonBody['cliente_id'] !== null
    ? (int) $jsonBody['cliente_id']
    : 0;

if ($scope === 'global') {
    $pacienteId = null;
    $seguimientoId = 0;
    $clienteId = 0;
}

if ($scope !== 'global' && $pacienteId === null) {
    jsonResponse(400, ['error' => 'paciente_id es requerido para scope=paciente.']);
}

$estatusId = isset($jsonBody['estatus_id']) ? (int) $jsonBody['estatus_id'] : 0;
if ($estatusId <= 0) {
    jsonResponse(400, ['error' => 'estatus_id es requerido.']);
}

$plantilla = isset($jsonBody['plantilla']) ? trim((string) $jsonBody['plantilla']) : '';
if ($plantilla === '') {
    jsonResponse(400, ['error' => 'plantilla es requerida.']);
}

$mensajeRenderizado = array_key_exists('mensaje_renderizado', $jsonBody) && $jsonBody['mensaje_renderizado'] !== null
    ? trim((string) $jsonBody['mensaje_renderizado'])
    : null;

// Update (si viene id)
if ($id > 0) {
    $sql = 'UPDATE ProspectosSeguimientoComunicacionMensajes
            SET seguimiento_id = NULLIF(?, 0),
                paciente_id = NULLIF(?, 0),
                cliente_id = NULLIF(?, 0),
                estatus_id = ?,
                plantilla = ?,
                mensaje_renderizado = ?
            WHERE id = ?';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        jsonResponse(500, ['error' => 'No fue posible preparar la actualización del mensaje.']);
    }

    $pacienteIdParam = $pacienteId === null ? 0 : (int) $pacienteId;
    $stmt->bind_param('iiiissi', $seguimientoId, $pacienteIdParam, $clienteId, $estatusId, $plantilla, $mensajeRenderizado, $id);
    if (!$stmt->execute()) {
        $stmt->close();
        jsonResponse(500, ['error' => 'No fue posible actualizar el mensaje.']);
    }

    $stmt->close();
    jsonResponse(200, [
        'ok' => true,
        'data' => [
            'id' => $id,
        ],
    ]);
}

$sql = 'INSERT INTO ProspectosSeguimientoComunicacionMensajes (
            seguimiento_id,
            paciente_id,
            cliente_id,
            estatus_id,
            plantilla,
            mensaje_renderizado,
            creado_en
        ) VALUES (NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, NOW())';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar el guardado del mensaje.']);
}

// paciente_id puede ser NULL en scope=global (enviando 0 para que NULLIF lo convierta a NULL)
$pacienteIdParam = $pacienteId === null ? 0 : (int) $pacienteId;
$stmt->bind_param('iiiiss', $seguimientoId, $pacienteIdParam, $clienteId, $estatusId, $plantilla, $mensajeRenderizado);
if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible guardar el mensaje.']);
}

$id = (int) $stmt->insert_id;
$stmt->close();

// Si es "global", registrar que este machote se genero hoy (sin relacion a clientes).
if ($scope === 'global') {
    try {
        $fechaHoy = date('Y-m-d');
        $sql2 = 'INSERT INTO MensajeCreado (mensaje_id, fecha) VALUES (?, ?)';
        $stmt2 = $conn->prepare($sql2);
        if ($stmt2 !== false) {
            $stmt2->bind_param('is', $id, $fechaHoy);
            $stmt2->execute();
            $stmt2->close();
        }
    } catch (Throwable $t) {
        // best-effort
    }
}

jsonResponse(200, [
    'ok' => true,
    'data' => [
        'id' => $id,
    ],
]);
