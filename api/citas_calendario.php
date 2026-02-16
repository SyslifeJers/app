<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$timezone = new DateTimeZone('America/Mexico_City');

function normalizarParametro(?string $valor, DateTimeZone $tz, string $campo): ?string
{
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor);
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

function toLowerUtf8(string $valor): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor);
}

function tablaExiste(mysqli $conn, string $tabla): bool
{
    $stmt = $conn->prepare('SHOW TABLES LIKE ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $tabla);
    $stmt->execute();
    $stmt->store_result();
    $existe = $stmt->num_rows > 0;
    $stmt->close();

    return $existe;
}

$conn = conectar();

$fechaInicio = normalizarParametro($_GET['start'] ?? null, $timezone, 'start');
$fechaFin = normalizarParametro($_GET['end'] ?? null, $timezone, 'end');

$psicologoId = null;
if (array_key_exists('psicologo_id', $_GET)) {
    $psicologoRaw = trim((string) $_GET['psicologo_id']);

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
    $pacienteRaw = trim((string) $_GET['paciente']);
    if ($pacienteRaw !== '') {
        $pacienteFiltro = toLowerUtf8($pacienteRaw);
    }
}

$condiciones = [];
$tipos = '';
$parametros = [];

if ($fechaInicio !== null) {
    $condiciones[] = 'ci.Programado >= ?';
    $tipos .= 's';
    $parametros[] = $fechaInicio;
}

if ($fechaFin !== null) {
    $condiciones[] = 'ci.Programado < ?';
    $tipos .= 's';
    $parametros[] = $fechaFin;
}

$condiciones[] = 'ci.Estatus IN (1, 2, 3, 4)';

if ($psicologoId !== null) {
    $condiciones[] = 'ci.IdUsuario = ?';
    $tipos .= 'i';
    $parametros[] = $psicologoId;
}

if ($pacienteFiltro !== null) {
    $condiciones[] = 'LOWER(n.name) LIKE ?';
    $tipos .= 's';
    $parametros[] = '%' . $pacienteFiltro . '%';
}

$tablaSolicitudesExiste = tablaExiste($conn, 'SolicitudReprogramacion');

$selectSolicitudesReprogramacion = $tablaSolicitudesExiste
    ? ",\n               COALESCE(sr_reprogramacion.solicitudesPendientes, 0) AS solicitudesReprogramacionPendientes"
    : ",\n               0 AS solicitudesReprogramacionPendientes";

$selectSolicitudesCancelacion = $tablaSolicitudesExiste
    ? ",\n               COALESCE(sr_cancelacion.solicitudesPendientesCancelacion, 0) AS solicitudesCancelacionPendientes"
    : ",\n               0 AS solicitudesCancelacionPendientes";

$joinSolicitudesReprogramacion = $tablaSolicitudesExiste
    ? "\n        LEFT JOIN (\n            SELECT cita_id, COUNT(*) AS solicitudesPendientes\n            FROM SolicitudReprogramacion\n            WHERE estatus = 'pendiente' AND tipo = 'reprogramacion'\n            GROUP BY cita_id\n        ) sr_reprogramacion ON sr_reprogramacion.cita_id = ci.id"
    : '';

$joinSolicitudesCancelacion = $tablaSolicitudesExiste
    ? "\n        LEFT JOIN (\n            SELECT cita_id, COUNT(*) AS solicitudesPendientesCancelacion\n            FROM SolicitudReprogramacion\n            WHERE estatus = 'pendiente' AND tipo = 'cancelacion'\n            GROUP BY cita_id\n        ) sr_cancelacion ON sr_cancelacion.cita_id = ci.id"
    : '';

$sql = 'SELECT ci.id,
               ci.Programado,
               ci.Tipo,
               ci.FormaPago,
               ci.costo,
               ci.IdUsuario AS psicologo_id,
               n.name  AS paciente,
               us.name AS psicologo,
               co.codigo_hex AS psicologo_color,
               es.name AS estatus'
        . $selectSolicitudesReprogramacion
        . $selectSolicitudesCancelacion .
        '       FROM Cita ci
        INNER JOIN nino n ON n.id = ci.IdNino
        INNER JOIN Usuarios us ON us.id = ci.IdUsuario
        LEFT JOIN colores co ON co.id = us.color_id
        INNER JOIN Estatus es ON es.id = ci.Estatus'
        . $joinSolicitudesReprogramacion
        . $joinSolicitudesCancelacion;

if ($condiciones !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}

$sql .= ' ORDER BY ci.Programado ASC';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar la consulta de citas.']);
}

if ($tipos !== '') {
    $stmt->bind_param($tipos, ...$parametros);
}

if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar la consulta de citas.']);
}

$resultado = $stmt->get_result();
$eventos = [];

while ($fila = $resultado->fetch_assoc()) {
    $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $fila['Programado'], $timezone);
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
        'solicitudesReprogramacionPendientes' => isset($fila['solicitudesReprogramacionPendientes'])
            ? (int) $fila['solicitudesReprogramacionPendientes']
            : 0,
        'solicitudesCancelacionPendientes' => isset($fila['solicitudesCancelacionPendientes'])
            ? (int) $fila['solicitudesCancelacionPendientes']
            : 0,
    ];
}
$stmt->close();

$tablaReunionesExiste = tablaExiste($conn, 'ReunionInterna');
$tablaParticipantesExiste = tablaExiste($conn, 'ReunionInternaPsicologo');

if ($tablaReunionesExiste && $tablaParticipantesExiste) {
    $condicionesReunion = [];
    $tiposReunion = '';
    $paramsReunion = [];

    if ($fechaInicio !== null) {
        $condicionesReunion[] = 'ri.inicio >= ?';
        $tiposReunion .= 's';
        $paramsReunion[] = $fechaInicio;
    }

    if ($fechaFin !== null) {
        $condicionesReunion[] = 'ri.inicio < ?';
        $tiposReunion .= 's';
        $paramsReunion[] = $fechaFin;
    }

    if ($psicologoId !== null) {
        $condicionesReunion[] = 'EXISTS (
            SELECT 1 FROM ReunionInternaPsicologo ripf
            WHERE ripf.reunion_id = ri.id AND ripf.psicologo_id = ?
        )';
        $tiposReunion .= 'i';
        $paramsReunion[] = $psicologoId;
    }

    if ($pacienteFiltro !== null) {
        $condicionesReunion[] = '(LOWER(ri.titulo) LIKE ? OR LOWER(ri.descripcion) LIKE ?)';
        $tiposReunion .= 'ss';
        $paramsReunion[] = '%' . $pacienteFiltro . '%';
        $paramsReunion[] = '%' . $pacienteFiltro . '%';
    }

    $sqlReuniones = "SELECT
            ri.id,
            ri.titulo,
            ri.descripcion,
            ri.inicio,
            ri.fin,
            GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ', ') AS psicologos
        FROM ReunionInterna ri
        INNER JOIN ReunionInternaPsicologo rip ON rip.reunion_id = ri.id
        INNER JOIN Usuarios u ON u.id = rip.psicologo_id";

    if ($condicionesReunion !== []) {
        $sqlReuniones .= ' WHERE ' . implode(' AND ', $condicionesReunion);
    }

    $sqlReuniones .= ' GROUP BY ri.id, ri.titulo, ri.descripcion, ri.inicio, ri.fin ORDER BY ri.inicio ASC';

    $stmtReunion = $conn->prepare($sqlReuniones);
    if ($stmtReunion) {
        if ($tiposReunion !== '') {
            $stmtReunion->bind_param($tiposReunion, ...$paramsReunion);
        }

        if ($stmtReunion->execute()) {
            $resultReunion = $stmtReunion->get_result();
            while ($filaReunion = $resultReunion->fetch_assoc()) {
                $inicioReunion = DateTime::createFromFormat('Y-m-d H:i:s', $filaReunion['inicio'], $timezone);
                $finReunion = DateTime::createFromFormat('Y-m-d H:i:s', $filaReunion['fin'], $timezone);
                if (!$inicioReunion || !$finReunion) {
                    continue;
                }

                $eventos[] = [
                    'id' => 'reunion-' . (int) $filaReunion['id'],
                    'event_kind' => 'reunion',
                    'entity_id' => (int) $filaReunion['id'],
                    'paciente' => null,
                    'psicologo' => $filaReunion['psicologos'],
                    'psicologo_id' => null,
                    'psicologo_color' => null,
                    'programado' => $inicioReunion->format(DateTime::ATOM),
                    'termina' => $finReunion->format(DateTime::ATOM),
                    'estatus' => 'Reunión interna',
                    'tipo' => $filaReunion['titulo'],
                    'forma_pago' => 'No aplica',
                    'costo' => 0,
                    'descripcion' => $filaReunion['descripcion'],
                    'solicitudesReprogramacionPendientes' => 0,
                    'solicitudesCancelacionPendientes' => 0,
                ];
            }
        }

        $stmtReunion->close();
    }
}

usort($eventos, static function (array $a, array $b): int {
    return strcmp((string) ($a['programado'] ?? ''), (string) ($b['programado'] ?? ''));
});

$conn->close();
jsonResponse(200, ['data' => $eventos]);
