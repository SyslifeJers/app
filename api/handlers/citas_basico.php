<?php

declare(strict_types=1);

$filtro = construirFiltroRango($fechaInicio, $fechaFin, 'Programado');
$sql = 'SELECT COUNT(*) AS total_citas,
               SUM(CASE WHEN Estatus = 1 THEN 1 ELSE 0 END) AS canceladas,
               SUM(CASE WHEN Estatus = 2 THEN 1 ELSE 0 END) AS programadas,
               SUM(CASE WHEN Estatus = 3 THEN 1 ELSE 0 END) AS reprogramadas,
               SUM(CASE WHEN Estatus = 4 THEN 1 ELSE 0 END) AS completadas
        FROM Cita';

if ($filtro['condiciones'] !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $filtro['condiciones']);
}

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar el resumen de citas.']);
}

if ($filtro['tipos'] !== '') {
    $stmt->bind_param($filtro['tipos'], ...$filtro['parametros']);
}

if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar el resumen de citas.']);
}

$resultado = $stmt->get_result();
$resumen = $resultado ? $resultado->fetch_assoc() : null;
$stmt->close();

if (!$resumen) {
    jsonResponse(200, ['data' => [
        'total_citas' => 0,
        'canceladas' => 0,
        'programadas' => 0,
        'reprogramadas' => 0,
        'completadas' => 0,
    ]]);
}

$resumenNormalizado = [
    'total_citas' => (int) ($resumen['total_citas'] ?? 0),
    'canceladas' => (int) ($resumen['canceladas'] ?? 0),
    'programadas' => (int) ($resumen['programadas'] ?? 0),
    'reprogramadas' => (int) ($resumen['reprogramadas'] ?? 0),
    'completadas' => (int) ($resumen['completadas'] ?? 0),
];

jsonResponse(200, ['data' => $resumenNormalizado]);
