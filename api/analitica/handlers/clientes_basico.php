<?php

$filtro = construirFiltroRango($fechaInicio, $fechaFin, 'ci.Programado');
$joinCitas = 'LEFT JOIN Cita ci ON ci.IdNino = n.id';
if ($filtro['condiciones'] !== []) {
    $joinCitas .= ' AND ' . implode(' AND ', $filtro['condiciones']);
}

$sql = 'SELECT c.id AS cliente_id,
               c.name AS cliente,
               COUNT(DISTINCT n.id) AS total_pacientes,
               COUNT(ci.id) AS total_citas,
               SUM(CASE WHEN ci.Estatus = 1 THEN 1 ELSE 0 END) AS canceladas,
               SUM(CASE WHEN ci.Estatus = 4 THEN 1 ELSE 0 END) AS completadas,
               MAX(ci.Programado) AS ultima_cita
        FROM Clientes c
        LEFT JOIN nino n ON n.idtutor = c.id
        ' . $joinCitas . '
        GROUP BY c.id, c.name
        ORDER BY c.name ASC';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar el resumen de clientes.']);
}

if ($filtro['tipos'] !== '') {
    $stmt->bind_param($filtro['tipos'], ...$filtro['parametros']);
}

if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar el resumen de clientes.']);
}

$resultado = $stmt->get_result();
$clientes = [];
while ($fila = $resultado->fetch_assoc()) {
    $clientes[] = [
        'cliente_id' => isset($fila['cliente_id']) ? (int) $fila['cliente_id'] : null,
        'cliente' => $fila['cliente'] ?? '',
        'total_pacientes' => (int) ($fila['total_pacientes'] ?? 0),
        'total_citas' => (int) ($fila['total_citas'] ?? 0),
        'canceladas' => (int) ($fila['canceladas'] ?? 0),
        'completadas' => (int) ($fila['completadas'] ?? 0),
        'ultima_cita' => $fila['ultima_cita'],
    ];
}
$stmt->close();

jsonResponse(200, ['data' => $clientes]);
