<?php

$minCompletadas = isset($_GET['min_completadas']) ? (int) $_GET['min_completadas'] : 5;
if ($minCompletadas <= 0) {
    $minCompletadas = 1;
}

// Rango por defecto de calificación "buena" para promociones.
$minCalificacion = isset($_GET['min_calificacion']) ? (float) $_GET['min_calificacion'] : 70.0;
$maxCalificacion = isset($_GET['max_calificacion']) ? (float) $_GET['max_calificacion'] : 89.9;

if ($minCalificacion < 0) {
    $minCalificacion = 0;
}
if ($maxCalificacion > 100) {
    $maxCalificacion = 100;
}
if ($minCalificacion > $maxCalificacion) {
    [$minCalificacion, $maxCalificacion] = [$maxCalificacion, $minCalificacion];
}

$maxCanceladas = isset($_GET['max_canceladas']) ? (int) $_GET['max_canceladas'] : 1;
if ($maxCanceladas < 0) {
    $maxCanceladas = 0;
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
if ($limit <= 0) {
    $limit = 20;
}

// IMPORTANTE:
// Este reporte se usa para analitica/listados. Por defecto NO debe crear registros
// en ProspectosSeguimiento; el alta debe hacerse explicitamente desde la app.
// Para mantener compatibilidad, se permite habilitar el alta con registrar_seguimiento=1.
$registrarSeguimiento = isset($_GET['registrar_seguimiento']) && (int) $_GET['registrar_seguimiento'] === 1;

$filtro = construirFiltroRango($fechaInicio, $fechaFin, 'ci.Programado');
$joinCitas = 'LEFT JOIN Cita ci ON ci.IdNino = n.id';
if ($filtro['condiciones'] !== []) {
    $joinCitas .= ' AND ' . implode(' AND ', $filtro['condiciones']);
}

$sql = 'SELECT n.id AS paciente_id,
               n.name AS paciente,
               c.id AS cliente_id,
               c.name AS cliente,
               COUNT(ci.id) AS total_citas,
               SUM(CASE WHEN ci.Estatus = 4 THEN 1 ELSE 0 END) AS total_completadas,
               SUM(CASE WHEN ci.Estatus = 1 THEN 1 ELSE 0 END) AS total_canceladas,
               SUM(CASE WHEN ci.Estatus = 2 AND ci.Programado < NOW() THEN 1 ELSE 0 END) AS total_ausencias,
               MAX(ci.Programado) AS ultima_cita
        FROM nino n
        LEFT JOIN Clientes c ON c.id = n.idtutor
        ' . $joinCitas . '
        GROUP BY n.id, n.name, c.id, c.name
        HAVING SUM(CASE WHEN ci.Estatus = 4 THEN 1 ELSE 0 END) >= ?
           AND SUM(CASE WHEN ci.Estatus = 1 THEN 1 ELSE 0 END) <= ?
        ORDER BY total_completadas DESC, ultima_cita DESC
        LIMIT ' . $limit;

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar el reporte de prospectos.']);
}

$tipos = $filtro['tipos'] . 'ii';
$parametros = array_merge($filtro['parametros'], [$minCompletadas, $maxCanceladas]);
$stmt->bind_param($tipos, ...$parametros);

if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar el reporte de prospectos.']);
}

$resultado = $stmt->get_result();
$prospectos = [];
while ($fila = $resultado->fetch_assoc()) {
    $totalCitas = (int) ($fila['total_citas'] ?? 0);
    $completadas = (int) ($fila['total_completadas'] ?? 0);
    $canceladas = (int) ($fila['total_canceladas'] ?? 0);
    $ausencias = (int) ($fila['total_ausencias'] ?? 0);

    $tasaAsistencia = $totalCitas > 0 ? $completadas / $totalCitas : 0;
    $tasaCancelacion = $totalCitas > 0 ? $canceladas / $totalCitas : 0;
    $tasaAusencias = $totalCitas > 0 ? $ausencias / $totalCitas : 0;
    $frecuenciaMensual = $totalCitas / 4;

    $puntajeAsistencia = max(0, min(50, $tasaAsistencia * 50));
    $puntajeFrecuencia = max(0, min(20, ($frecuenciaMensual / 4) * 20));
    $puntajeCancelaciones = max(0, 20 - ($canceladas * 3) - ($tasaCancelacion * 10));
    $puntajeAusencias = max(0, 10 - ($ausencias * 3) - ($tasaAusencias * 10));
    $calificacion = round($puntajeAsistencia + $puntajeFrecuencia + $puntajeCancelaciones + $puntajeAusencias, 1);

    if ($calificacion < $minCalificacion || $calificacion > $maxCalificacion) {
        continue;
    }

    $prospectos[] = [
        'paciente_id' => isset($fila['paciente_id']) ? (int) $fila['paciente_id'] : null,
        'paciente' => $fila['paciente'] ?? '',
        'cliente_id' => isset($fila['cliente_id']) ? (int) $fila['cliente_id'] : null,
        'cliente' => $fila['cliente'] ?? '',
        'total_completadas' => $completadas,
        'total_canceladas' => $canceladas,
        'calificacion' => $calificacion,
        'ultima_cita' => $fila['ultima_cita'],
    ];
}
$stmt->close();

if ($registrarSeguimiento) {
    registrarProspectosSeguimiento($conn, $prospectos);
}

jsonResponse(200, [
    'data' => $prospectos,
    'min_completadas' => $minCompletadas,
    'max_canceladas' => $maxCanceladas,
    'min_calificacion' => $minCalificacion,
    'max_calificacion' => $maxCalificacion,
    'seguimiento_actualizado' => $registrarSeguimiento,
]);
