<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../conexion.php';

/**
 * Envía una respuesta JSON y termina la ejecución.
 */
function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Normaliza un parámetro de fecha para usarlo en filtros SQL.
 */
function normalizarFechaParametro(?string $valor, DateTimeZone $tz, string $campo): ?string
{
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }

    try {
        $fecha = new DateTime($valor, $tz);
    } catch (Exception $exception) {
        jsonResponse(400, ['error' => sprintf('El parámetro %s no tiene un formato de fecha válido.', $campo)]);
    }

    $fecha->setTimezone($tz);

    return $fecha->format('Y-m-d H:i:s');
}

/**
 * Construye filtros y parámetros para rangos de fechas.
 */
function construirFiltroRango(?string $inicio, ?string $fin, string $columna): array
{
    $condiciones = [];
    $tipos = '';
    $parametros = [];

    if ($inicio !== null) {
        $condiciones[] = "{$columna} >= ?";
        $tipos .= 's';
        $parametros[] = $inicio;
    }

    if ($fin !== null) {
        $condiciones[] = "{$columna} < ?";
        $tipos .= 's';
        $parametros[] = $fin;
    }

    return [
        'condiciones' => $condiciones,
        'tipos' => $tipos,
        'parametros' => $parametros,
    ];
}

$timezone = new DateTimeZone('America/Mexico_City');

$reporte = isset($_GET['reporte']) ? trim((string) $_GET['reporte']) : 'citas_basico';
if ($reporte === '') {
    $reporte = 'citas_basico';
}

$fechaInicio = normalizarFechaParametro($_GET['fecha_inicio'] ?? null, $timezone, 'fecha_inicio');
$fechaFin = normalizarFechaParametro($_GET['fecha_fin'] ?? null, $timezone, 'fecha_fin');

$conn = conectar();
if (!($conn instanceof mysqli) || $conn->connect_errno) {
    jsonResponse(500, ['error' => 'No se pudo establecer la conexión a la base de datos.']);
}

$conn->set_charset('utf8mb4');

switch ($reporte) {
    case 'citas_basico':
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

    case 'clientes_basico':
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

    case 'cancelaciones_frecuentes':
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

    case 'prospectos_promocion':
        $minCompletadas = isset($_GET['min_completadas']) ? (int) $_GET['min_completadas'] : 5;
        if ($minCompletadas <= 0) {
            $minCompletadas = 1;
        }

        $maxCanceladas = isset($_GET['max_canceladas']) ? (int) $_GET['max_canceladas'] : 1;
        if ($maxCanceladas < 0) {
            $maxCanceladas = 0;
        }

        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
        if ($limit <= 0) {
            $limit = 20;
        }

        $filtro = construirFiltroRango($fechaInicio, $fechaFin, 'ci.Programado');
        $joinCitas = 'LEFT JOIN Cita ci ON ci.IdNino = n.id';
        if ($filtro['condiciones'] !== []) {
            $joinCitas .= ' AND ' . implode(' AND ', $filtro['condiciones']);
        }

        $sql = 'SELECT n.id AS paciente_id,
                       n.name AS paciente,
                       c.id AS cliente_id,
                       c.name AS cliente,
                       SUM(CASE WHEN ci.Estatus = 4 THEN 1 ELSE 0 END) AS total_completadas,
                       SUM(CASE WHEN ci.Estatus = 1 THEN 1 ELSE 0 END) AS total_canceladas,
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
            $prospectos[] = [
                'paciente_id' => isset($fila['paciente_id']) ? (int) $fila['paciente_id'] : null,
                'paciente' => $fila['paciente'] ?? '',
                'cliente_id' => isset($fila['cliente_id']) ? (int) $fila['cliente_id'] : null,
                'cliente' => $fila['cliente'] ?? '',
                'total_completadas' => (int) ($fila['total_completadas'] ?? 0),
                'total_canceladas' => (int) ($fila['total_canceladas'] ?? 0),
                'ultima_cita' => $fila['ultima_cita'],
            ];
        }
        $stmt->close();

        jsonResponse(200, [
            'data' => $prospectos,
            'min_completadas' => $minCompletadas,
            'max_canceladas' => $maxCanceladas,
        ]);

    case 'clientes_adherencia':
    case 'pacientes_adherencia':
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

    default:
        jsonResponse(400, [
            'error' => 'Reporte no reconocido. Usa: citas_basico, clientes_basico, cancelaciones_frecuentes, prospectos_promocion, pacientes_adherencia.',
        ]);
}
