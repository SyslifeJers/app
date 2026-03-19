<?php

$sql = 'SELECT id, clave, nombre, descripcion, activo
        FROM ProspectoEstatusSeguimiento
        WHERE activo = 1
        ORDER BY id ASC';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar el catálogo de estatus de prospecto.']);
}

if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar el catálogo de estatus de prospecto.']);
}

$resultado = $stmt->get_result();
$estatus = [];
while ($fila = $resultado->fetch_assoc()) {
    $estatus[] = [
        'id' => isset($fila['id']) ? (int) $fila['id'] : null,
        'clave' => $fila['clave'] ?? '',
        'nombre' => $fila['nombre'] ?? '',
        'descripcion' => $fila['descripcion'] ?? '',
    ];
}
$stmt->close();

jsonResponse(200, ['data' => $estatus]);
