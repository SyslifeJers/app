<?php

declare(strict_types=1);

$sql = 'SELECT id, clave, nombre, descripcion, tipo_descuento, aplica_a, valor, vigencia_inicio, vigencia_fin, activo
        FROM PromocionesCatalogo
        WHERE activo = 1
        ORDER BY nombre ASC';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar el catálogo de promociones.']);
}

if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar el catálogo de promociones.']);
}

$resultado = $stmt->get_result();
$promociones = [];
while ($fila = $resultado->fetch_assoc()) {
    $promociones[] = [
        'id' => isset($fila['id']) ? (int) $fila['id'] : null,
        'clave' => $fila['clave'] ?? '',
        'nombre' => $fila['nombre'] ?? '',
        'descripcion' => $fila['descripcion'] ?? '',
        'tipo_descuento' => $fila['tipo_descuento'] ?? '',
        'aplica_a' => $fila['aplica_a'] ?? 'cita_seguimiento',
        'valor' => isset($fila['valor']) ? (float) $fila['valor'] : 0,
        'vigencia_inicio' => $fila['vigencia_inicio'],
        'vigencia_fin' => $fila['vigencia_fin'],
    ];
}
$stmt->close();

jsonResponse(200, ['data' => $promociones]);
