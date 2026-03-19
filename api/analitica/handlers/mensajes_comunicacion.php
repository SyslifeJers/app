<?php

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
if ($limit <= 0 || $limit > 200) {
    $limit = 20;
}

$scope = isset($_GET['scope']) ? trim((string) $_GET['scope']) : '';

$pacienteId = isset($_GET['paciente_id']) ? (int) $_GET['paciente_id'] : 0;
$seguimientoId = isset($_GET['seguimiento_id']) ? (int) $_GET['seguimiento_id'] : 0;

if ($scope === 'global') {
    $fechaHoy = date('Y-m-d');
    $where = 'mc.fecha = ?';
    $types = 's';
    $params = [$fechaHoy];
} elseif ($seguimientoId > 0) {
    $where = 'm.seguimiento_id = ?';
    $types = 'i';
    $params = [$seguimientoId];
} else {
    if ($pacienteId <= 0) {
        jsonResponse(400, ['error' => 'Debes enviar paciente_id o seguimiento_id, o scope=global.']);
    }
    $where = 'm.paciente_id = ?';
    $types = 'i';
    $params = [$pacienteId];
}

if ($scope === 'global') {
    $sql = 'SELECT m.id,
                   m.seguimiento_id,
                   m.paciente_id,
                   NULL AS paciente,
                   m.cliente_id,
                   NULL AS cliente,
                   m.estatus_id,
                   es.nombre AS estatus_seguimiento,
                   m.plantilla,
                   m.mensaje_renderizado,
                   CONCAT(mc.fecha, " 00:00:00") AS creado_en
            FROM MensajeCreado mc
            INNER JOIN ProspectosSeguimientoComunicacionMensajes m ON m.id = mc.mensaje_id
            LEFT JOIN ProspectoEstatusSeguimiento es ON es.id = m.estatus_id
            WHERE ' . $where . '
            ORDER BY mc.id DESC
            LIMIT ' . $limit;
} else {
    $sql = 'SELECT m.id,
                   m.seguimiento_id,
                   m.paciente_id,
                   n.name AS paciente,
                   m.cliente_id,
                   c.name AS cliente,
                   m.estatus_id,
                   es.nombre AS estatus_seguimiento,
                   m.plantilla,
                   m.mensaje_renderizado,
                   m.creado_en
            FROM ProspectosSeguimientoComunicacionMensajes m
            LEFT JOIN nino n ON n.id = m.paciente_id
            LEFT JOIN Clientes c ON c.id = m.cliente_id
            LEFT JOIN ProspectoEstatusSeguimiento es ON es.id = m.estatus_id
            WHERE ' . $where . '
            ORDER BY m.creado_en DESC
            LIMIT ' . $limit;
}

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar la consulta de mensajes.']);
}

$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar la consulta de mensajes.']);
}

$resultado = $stmt->get_result();
$mensajes = [];
while ($fila = $resultado->fetch_assoc()) {
    $mensajes[] = [
        'id' => isset($fila['id']) ? (int) $fila['id'] : null,
        'seguimiento_id' => isset($fila['seguimiento_id']) ? (int) $fila['seguimiento_id'] : null,
        'paciente_id' => isset($fila['paciente_id']) ? (int) $fila['paciente_id'] : null,
        'paciente' => $fila['paciente'] ?? '',
        'cliente_id' => isset($fila['cliente_id']) ? (int) $fila['cliente_id'] : null,
        'cliente' => $fila['cliente'] ?? '',
        'estatus_id' => isset($fila['estatus_id']) ? (int) $fila['estatus_id'] : 1,
        'estatus_seguimiento' => $fila['estatus_seguimiento'] ?? '',
        'plantilla' => $fila['plantilla'] ?? '',
        'mensaje_renderizado' => $fila['mensaje_renderizado'] ?? '',
        'creado_en' => $fila['creado_en'],
    ];
}
$stmt->close();

jsonResponse(200, [
    'data' => $mensajes,
    'limit' => $limit,
]);
