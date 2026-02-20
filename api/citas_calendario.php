<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizarParametro($valor, DateTimeZone $tz, $campo)
{
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor . '');
    if ($valor === '') {
        return null;
    }

    try {
        $fecha = new DateTime($valor);
    } catch (Exception $exception) {
        jsonResponse(400, [
            'error' => sprintf('El parámetro %s no tiene un formato de fecha válido.', $campo),
        ]);
    }

    $fecha->setTimezone($tz);
    return $fecha->format('Y-m-d H:i:s');
}

function toLowerUtf8($valor)
{
    return function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor);
}

$timezone = new DateTimeZone('America/Mexico_City');
$conn = conectar();
$conn->set_charset('utf8mb4');

$fechaInicio = normalizarParametro($_GET['start'] ?? null, $timezone, 'start');
$fechaFin = normalizarParametro($_GET['end'] ?? null, $timezone, 'end');

$psicologoId = null;
if (array_key_exists('psicologo_id', $_GET)) {
    $psicologoRaw = trim($_GET['psicologo_id'] . '');

    if ($psicologoRaw !== '') {
        if (!ctype_digit($psicologoRaw)) {
            jsonResponse(400, ['error' => 'El parámetro psicologo_id debe ser un número entero positivo.']);
        }

        $psicologoId = (int) $psicologoRaw;
        if ($psicologoId <= 0) {
            jsonResponse(400, ['error' => 'El parámetro psicologo_id debe ser mayor que cero.']);
        }
    }
}

$pacienteFiltro = null;
if (array_key_exists('paciente', $_GET)) {
    $pacienteRaw = trim($_GET['paciente'] . '');
    if ($pacienteRaw !== '') {
        $pacienteFiltro = toLowerUtf8($pacienteRaw);
    }
}

$selectSolicitudesReprogramacion = ', COALESCE(sr_reprogramacion.solicitudesPendientes, 0) AS solicitudesReprogramacionPendientes';

$selectSolicitudesCancelacion = ', COALESCE(sr_cancelacion.solicitudesPendientesCancelacion, 0) AS solicitudesCancelacionPendientes';

$joinSolicitudesReprogramacion = "\n        LEFT JOIN (\n            SELECT cita_id, COUNT(*) AS solicitudesPendientes\n            FROM SolicitudReprogramacion\n            WHERE estatus = 'pendiente' AND tipo = 'reprogramacion'\n            GROUP BY cita_id\n        ) sr_reprogramacion ON sr_reprogramacion.cita_id = ci.id";

$joinSolicitudesCancelacion = "\n        LEFT JOIN (\n            SELECT cita_id, COUNT(*) AS solicitudesPendientesCancelacion\n            FROM SolicitudReprogramacion\n            WHERE estatus = 'pendiente' AND tipo = 'cancelacion'\n            GROUP BY cita_id\n        ) sr_cancelacion ON sr_cancelacion.cita_id = ci.id";

$condicionesCita = ['ci.Estatus IN (1, 2, 3, 4)'];
$tiposCita = '';
$parametrosCita = [];

if ($fechaInicio !== null) {
    $condicionesCita[] = 'ci.Programado >= ?';
    $tiposCita .= 's';
    $parametrosCita[] = $fechaInicio;
}

if ($fechaFin !== null) {
    $condicionesCita[] = 'ci.Programado < ?';
    $tiposCita .= 's';
    $parametrosCita[] = $fechaFin;
}

if ($psicologoId !== null) {
    $condicionesCita[] = 'ci.IdUsuario = ?';
    $tiposCita .= 'i';
    $parametrosCita[] = $psicologoId;
}

if ($pacienteFiltro !== null) {
    $condicionesCita[] = 'LOWER(n.name) LIKE ?';
    $tiposCita .= 's';
    $parametrosCita[] = '%' . $pacienteFiltro . '%';
}

$sqlCitas = 'SELECT ci.id,
               ci.Programado,
               ci.Tipo,
               ci.FormaPago,
               ci.costo,
               ci.IdUsuario AS psicologo_id,
               n.name AS paciente,
               us.name AS psicologo,
               co.codigo_hex AS psicologo_color,
               es.name AS estatus'
    . $selectSolicitudesReprogramacion
    . $selectSolicitudesCancelacion
    . ' FROM Cita ci
        INNER JOIN nino n ON n.id = ci.IdNino
        INNER JOIN Usuarios us ON us.id = ci.IdUsuario
        LEFT JOIN colores co ON co.id = us.color_id
        INNER JOIN Estatus es ON es.id = ci.Estatus'
    . $joinSolicitudesReprogramacion
    . $joinSolicitudesCancelacion
    . ' WHERE ' . implode(' AND ', $condicionesCita)
    . ' ORDER BY ci.Programado ASC';

$stmtCitas = $conn->prepare($sqlCitas);
if ($stmtCitas === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar la consulta de citas.']);
}

if ($tiposCita !== '') {
    $stmtCitas->bind_param($tiposCita, ...$parametrosCita);
}

if (!$stmtCitas->execute()) {
    $stmtCitas->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar la consulta de citas.']);
}

$eventos = [];
$resultadoCitas = $stmtCitas->get_result();
while ($fila = $resultadoCitas->fetch_assoc()) {
    $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $fila['Programado'] . '', $timezone);
    if ($inicio === false) {
        continue;
    }

    $fin = clone $inicio;
    $fin->modify('+1 hour');

    $eventos[] = [
        'id' => 'cita-' . (int) $fila['id'],
        'event_kind' => 'cita',
        'entity_id' => (int) $fila['id'],
        'paciente' => $fila['paciente'],
        'psicologo' => $fila['psicologo'],
        'psicologo_id' => (int) $fila['psicologo_id'],
        'psicologo_color' => $fila['psicologo_color'] ?? null,
        'programado' => $inicio->format(DateTime::ATOM),
        'termina' => $fin->format(DateTime::ATOM),
        'estatus' => $fila['estatus'],
        'tipo' => $fila['Tipo'],
        'forma_pago' => $fila['FormaPago'],
        'costo' => $fila['costo'] !== null ? (float) $fila['costo'] : null,
        'solicitudesReprogramacionPendientes' => (int) ($fila['solicitudesReprogramacionPendientes'] ?? 0),
        'solicitudesCancelacionPendientes' => (int) ($fila['solicitudesCancelacionPendientes'] ?? 0),
    ];
}
$stmtCitas->close();

$condicionesReunion = [];
$tiposReunion = '';
$parametrosReunion = [];

$ahora = new DateTime('now', $timezone);
$condicionesReunion[] = 'ri.fin >= ?';
$tiposReunion .= 's';
$parametrosReunion[] = $ahora->format('Y-m-d H:i:s');

    if ($fechaInicio !== null) {
        $condicionesReunion[] = 'ri.fin >= ?';
        $tiposReunion .= 's';
        $parametrosReunion[] = $fechaInicio;
    }

    if ($fechaFin !== null) {
        $condicionesReunion[] = 'ri.inicio < ?';
        $tiposReunion .= 's';
        $parametrosReunion[] = $fechaFin;
    }

    if ($psicologoId !== null) {
        $condicionesReunion[] = 'EXISTS (
            SELECT 1
            FROM ReunionInternaPsicologo rip_filtro
            WHERE rip_filtro.reunion_id = ri.id AND rip_filtro.psicologo_id = ?
        )';
        $tiposReunion .= 'i';
        $parametrosReunion[] = $psicologoId;
    }

$sqlReuniones = 'SELECT ri.id,
                           ri.titulo,
                           ri.inicio,
                           ri.fin,
                           GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ", ") AS psicologos,
                           MIN(rip.psicologo_id) AS psicologo_id,
                           MIN(co.codigo_hex) AS psicologo_color
                    FROM ReunionInterna ri
                    INNER JOIN ReunionInternaPsicologo rip ON rip.reunion_id = ri.id
                    INNER JOIN Usuarios u ON u.id = rip.psicologo_id
                    LEFT JOIN colores co ON co.id = u.color_id';

if ($condicionesReunion !== []) {
    $sqlReuniones .= ' WHERE ' . implode(' AND ', $condicionesReunion);
}

$sqlReuniones .= ' GROUP BY ri.id, ri.titulo, ri.inicio, ri.fin
                      ORDER BY ri.inicio ASC';

$stmtReuniones = $conn->prepare($sqlReuniones);
if ($stmtReuniones === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar la consulta de reuniones.']);
}

if ($tiposReunion !== '') {
    $stmtReuniones->bind_param($tiposReunion, ...$parametrosReunion);
}

if (!$stmtReuniones->execute()) {
    $stmtReuniones->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar la consulta de reuniones.']);
}

$resultadoReuniones = $stmtReuniones->get_result();
while ($fila = $resultadoReuniones->fetch_assoc()) {
    $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $fila['inicio'] . '', $timezone);
    if ($inicio === false) {
        continue;
    }

    $fin = DateTime::createFromFormat('Y-m-d H:i:s', $fila['fin'] . '', $timezone);
    if ($fin === false) {
        $fin = clone $inicio;
        $fin->modify('+1 hour');
    }

    $eventos[] = [
        'id' => 'reunion-' . (int) $fila['id'],
        'event_kind' => 'reunion',
        'entity_id' => (int) $fila['id'],
        'paciente' => null,
        'psicologo' => $fila['psicologos'] ?? '',
        'psicologo_id' => isset($fila['psicologo_id']) ? (int) $fila['psicologo_id'] : null,
        'psicologo_color' => $fila['psicologo_color'] ?? null,
        'programado' => $inicio->format(DateTime::ATOM),
        'termina' => $fin->format(DateTime::ATOM),
        'estatus' => 'Programada',
        'tipo' => $fila['titulo'] ?: 'Reunión interna',
        'forma_pago' => null,
        'costo' => null,
        'solicitudesReprogramacionPendientes' => 0,
        'solicitudesCancelacionPendientes' => 0,
    ];
}

$stmtReuniones->close();

usort($eventos, static function (array $a, array $b): int {
    return strcmp(($a['programado'] ?? '') . '', ($b['programado'] ?? '') . '');
});

$conn->close();
jsonResponse(200, ['data' => $eventos]);
