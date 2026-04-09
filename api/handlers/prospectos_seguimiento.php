<?php

declare(strict_types=1);

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
if ($limit <= 0) {
    $limit = 100;
}

$sql = 'SELECT ps.id,
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
        ORDER BY ps.fecha_actualizacion DESC
        LIMIT ' . $limit;

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar el listado de seguimiento de prospectos.']);
}

if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar el listado de seguimiento de prospectos.']);
}

$resultado = $stmt->get_result();
$prospectosSeguimiento = [];
while ($fila = $resultado->fetch_assoc()) {
    $prospectosSeguimiento[] = [
        'id' => isset($fila['id']) ? (int) $fila['id'] : null,
        'paciente_id' => isset($fila['paciente_id']) ? (int) $fila['paciente_id'] : null,
        'paciente' => $fila['paciente'] ?? '',
        'cliente_id' => isset($fila['cliente_id']) ? (int) $fila['cliente_id'] : null,
        'cliente' => $fila['cliente'] ?? '',
        'total_completadas' => (int) ($fila['total_completadas'] ?? 0),
        'total_canceladas' => (int) ($fila['total_canceladas'] ?? 0),
        'calificacion' => isset($fila['calificacion']) ? (float) $fila['calificacion'] : 0,
        'ultima_cita' => $fila['ultima_cita'],
        'estatus_id' => isset($fila['estatus_id']) ? (int) $fila['estatus_id'] : 1,
        'estatus_seguimiento' => $fila['estatus_seguimiento'] ?? 'Creada',
        'promocion_id' => isset($fila['promocion_id']) ? (int) $fila['promocion_id'] : null,
        'promocion' => $fila['promocion'] ?? '',
        'notas' => $fila['notas'] ?? '',
        'fecha_alta' => $fila['fecha_alta'],
        'fecha_actualizacion' => $fila['fecha_actualizacion'],
    ];
}
$stmt->close();

jsonResponse(200, [
    'data' => $prospectosSeguimiento,
    'limit' => $limit,
]);
