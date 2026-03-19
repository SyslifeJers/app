<?php

$ahora = new DateTime('now', $timezone);
$inicioBase = (clone $ahora)->modify('-4 months');

$inicioAnalisis = $fechaInicio ?? $inicioBase->format('Y-m-d H:i:s');
$finAnalisis = $fechaFin ?? $ahora->format('Y-m-d H:i:s');

$filtro = construirFiltroRango($inicioAnalisis, $finAnalisis, 'ci.Programado');
$joinCitas = 'LEFT JOIN Cita ci ON ci.IdNino = n.id';
if ($filtro['condiciones'] !== []) {
    $joinCitas .= ' AND ' . implode(' AND ', $filtro['condiciones']);
}

$sql = 'SELECT n.id AS paciente_id,
               n.name AS paciente,
               c.id AS cliente_id,
               c.name AS cliente,
               COUNT(ci.id) AS total_citas,
               SUM(CASE WHEN ci.Estatus = 4 THEN 1 ELSE 0 END) AS completadas,
               SUM(CASE WHEN ci.Estatus = 1 THEN 1 ELSE 0 END) AS canceladas,
               SUM(CASE WHEN ci.Estatus = 2 AND ci.Programado < NOW() THEN 1 ELSE 0 END) AS ausencias,
               MAX(ci.Programado) AS ultima_cita,
               DATEDIFF(NOW(), MAX(ci.Programado)) AS dias_desde_ultima_cita
        FROM nino n
        LEFT JOIN Clientes c ON c.id = n.idtutor
        ' . $joinCitas . '
        GROUP BY n.id, n.name, c.id, c.name
        ORDER BY n.name ASC';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar el reporte de adherencia por paciente.']);
}

if ($filtro['tipos'] !== '') {
    $stmt->bind_param($filtro['tipos'], ...$filtro['parametros']);
}

if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar el reporte de adherencia por paciente.']);
}

$resultado = $stmt->get_result();
$pacientes = [];
while ($fila = $resultado->fetch_assoc()) {
    $totalCitas = (int) ($fila['total_citas'] ?? 0);
    $completadas = (int) ($fila['completadas'] ?? 0);
    $canceladas = (int) ($fila['canceladas'] ?? 0);
    $ausencias = (int) ($fila['ausencias'] ?? 0);
    $diasSinAsistir = isset($fila['dias_desde_ultima_cita']) ? (int) $fila['dias_desde_ultima_cita'] : 999;
    $tasaAsistencia = $totalCitas > 0 ? $completadas / $totalCitas : 0;
    $tasaCancelacion = $totalCitas > 0 ? $canceladas / $totalCitas : 0;
    $tasaAusencias = $totalCitas > 0 ? $ausencias / $totalCitas : 0;
    $frecuenciaMensual = $totalCitas / 4;

    $puntajeAsistencia = max(0, min(50, $tasaAsistencia * 50));
    $puntajeFrecuencia = max(0, min(20, ($frecuenciaMensual / 4) * 20));
    $puntajeCancelaciones = max(0, 20 - ($canceladas * 3) - ($tasaCancelacion * 10));
    $puntajeAusencias = max(0, 10 - ($ausencias * 3) - ($tasaAusencias * 10));
    $calificacion = round($puntajeAsistencia + $puntajeFrecuencia + $puntajeCancelaciones + $puntajeAusencias, 1);

    if ($totalCitas === 0 || $diasSinAsistir >= 45) {
        $estado = 'Inactivo';
    } elseif ($canceladas >= 3 && $canceladas >= $completadas) {
        $estado = 'Cancelación frecuente';
    } elseif ($ausencias >= 2 || $diasSinAsistir >= 21) {
        $estado = 'Ausencia frecuente';
    } else {
        $estado = 'Seguimiento activo';
    }

    $pacientes[] = [
        'paciente_id' => isset($fila['paciente_id']) ? (int) $fila['paciente_id'] : null,
        'paciente' => $fila['paciente'] ?? '',
        'cliente_id' => isset($fila['cliente_id']) ? (int) $fila['cliente_id'] : null,
        'cliente' => $fila['cliente'] ?? '',
        'total_citas' => $totalCitas,
        'completadas' => $completadas,
        'canceladas' => $canceladas,
        'ausencias' => $ausencias,
        'frecuencia_mensual' => round($frecuenciaMensual, 2),
        'calificacion' => $calificacion,
        'estado' => $estado,
        'ultima_cita' => $fila['ultima_cita'],
        'dias_desde_ultima_cita' => $diasSinAsistir,
    ];
}
$stmt->close();

jsonResponse(200, [
    'data' => $pacientes,
    'fecha_inicio' => $inicioAnalisis,
    'fecha_fin' => $finAnalisis,
]);
