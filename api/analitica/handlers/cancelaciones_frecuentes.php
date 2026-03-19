<?php

$minCancelaciones = isset($_GET['min_cancelaciones']) ? (int) $_GET['min_cancelaciones'] : 2;
if ($minCancelaciones <= 0) {
    $minCancelaciones = 1;
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
if ($limit <= 0) {
    $limit = 20;
}

$filtro = construirFiltroRango($fechaInicio, $fechaFin, 'ci.Programado');
$condiciones = array_merge(['ci.Estatus = 1'], $filtro['condiciones']);
$tipos = $filtro['tipos'] . 'i';
$parametros = array_merge($filtro['parametros'], [$minCancelaciones]);

$sql = 'SELECT n.id AS paciente_id,
               n.name AS paciente,
               c.id AS cliente_id,
               c.name AS cliente,
               COUNT(ci.id) AS total_cancelaciones,
               MAX(ci.Programado) AS ultima_cancelacion
        FROM Cita ci
        INNER JOIN nino n ON n.id = ci.IdNino
        LEFT JOIN Clientes c ON c.id = n.idtutor
        WHERE ' . implode(' AND ', $condiciones) . '
        GROUP BY n.id, n.name, c.id, c.name
        HAVING COUNT(ci.id) >= ?
        ORDER BY total_cancelaciones DESC, ultima_cancelacion DESC
        LIMIT ' . $limit;

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar el reporte de cancelaciones frecuentes.']);
}

$stmt->bind_param($tipos, ...$parametros);

if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar el reporte de cancelaciones frecuentes.']);
}

$resultado = $stmt->get_result();
$cancelaciones = [];
while ($fila = $resultado->fetch_assoc()) {
    $cancelaciones[] = [
        'paciente_id' => isset($fila['paciente_id']) ? (int) $fila['paciente_id'] : null,
        'paciente' => $fila['paciente'] ?? '',
        'cliente_id' => isset($fila['cliente_id']) ? (int) $fila['cliente_id'] : null,
        'cliente' => $fila['cliente'] ?? '',
        'total_cancelaciones' => (int) ($fila['total_cancelaciones'] ?? 0),
        'ultima_cancelacion' => $fila['ultima_cancelacion'],
    ];
}
$stmt->close();

jsonResponse(200, ['data' => $cancelaciones, 'min_cancelaciones' => $minCancelaciones]);
