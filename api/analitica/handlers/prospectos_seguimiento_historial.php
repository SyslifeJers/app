<?php

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
if ($limit <= 0 || $limit > 500) {
    $limit = 50;
}

$pacienteId = isset($_GET['paciente_id']) ? (int) $_GET['paciente_id'] : 0;
$prospectoId = isset($_GET['prospecto_id']) ? (int) $_GET['prospecto_id'] : 0;

if ($pacienteId <= 0 && $prospectoId <= 0) {
    jsonResponse(400, ['error' => 'Debes enviar paciente_id o prospecto_id.']);
}

$where = '';
$types = '';
$params = [];
if ($prospectoId > 0) {
    $where = 'h.prospecto_id = ?';
    $types = 'i';
    $params[] = $prospectoId;
} else {
    $where = 'h.paciente_id = ?';
    $types = 'i';
    $params[] = $pacienteId;
}

$sql = 'SELECT h.id,
               h.prospecto_id,
               h.paciente_id,
               n.name AS paciente,
               h.cliente_id,
               c.name AS cliente,
               h.estatus_id,
               es.nombre AS estatus_seguimiento,
               h.promocion_id,
               pr.nombre AS promocion,
               h.promocion_texto,
               h.notas,
               h.calificacion,
               h.origen,
               h.usuario_id,
               u.name AS usuario,
               h.creado_en
        FROM ProspectosSeguimientoHistorial h
        LEFT JOIN nino n ON n.id = h.paciente_id
        LEFT JOIN Clientes c ON c.id = h.cliente_id
        LEFT JOIN ProspectoEstatusSeguimiento es ON es.id = h.estatus_id
        LEFT JOIN PromocionesCatalogo pr ON pr.id = h.promocion_id
        LEFT JOIN Usuarios u ON u.id = h.usuario_id
        WHERE ' . $where . '
        ORDER BY h.creado_en DESC
        LIMIT ' . $limit;

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar el historial de seguimiento.']);
}

$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar el historial de seguimiento.']);
}

$resultado = $stmt->get_result();
$historial = [];
while ($fila = $resultado->fetch_assoc()) {
    $historial[] = [
        'id' => isset($fila['id']) ? (int) $fila['id'] : null,
        'prospecto_id' => isset($fila['prospecto_id']) ? (int) $fila['prospecto_id'] : null,
        'paciente_id' => isset($fila['paciente_id']) ? (int) $fila['paciente_id'] : null,
        'paciente' => $fila['paciente'] ?? '',
        'cliente_id' => isset($fila['cliente_id']) ? (int) $fila['cliente_id'] : null,
        'cliente' => $fila['cliente'] ?? '',
        'estatus_id' => isset($fila['estatus_id']) ? (int) $fila['estatus_id'] : 1,
        'estatus_seguimiento' => $fila['estatus_seguimiento'] ?? 'Creada',
        'promocion_id' => isset($fila['promocion_id']) ? (int) $fila['promocion_id'] : null,
        'promocion' => $fila['promocion'] ?? '',
        'promocion_texto' => $fila['promocion_texto'] ?? '',
        'notas' => $fila['notas'] ?? '',
        'calificacion' => isset($fila['calificacion']) ? (float) $fila['calificacion'] : 0,
        'origen' => $fila['origen'] ?? '',
        'usuario_id' => isset($fila['usuario_id']) ? (int) $fila['usuario_id'] : null,
        'usuario' => $fila['usuario'] ?? '',
        'creado_en' => $fila['creado_en'],
    ];
}
$stmt->close();

jsonResponse(200, [
    'data' => $historial,
    'limit' => $limit,
]);
