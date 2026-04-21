<?php
header('Content-Type: application/json; charset=utf-8');

session_start();

require_once __DIR__ . '/../conexion.php';

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['id']) || !isset($_SESSION['token'])) {
    jsonResponse(401, ['error' => 'No autenticado.']);
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
                 ci.Tiempo,
                 ci.Tipo,
                 ci.FormaPago,
                 ci.costo,
                 ci.IdUsuario AS psicologo_id,
                 n.name AS paciente,
                 c.telefono AS contacto_telefono,
                 c.correo AS contacto_correo,
                 us.name AS psicologo,
                 co.codigo_hex AS psicologo_color,
                 es.name AS estatus'
    . $selectSolicitudesReprogramacion
    . $selectSolicitudesCancelacion
    . ' FROM Cita ci
        INNER JOIN nino n ON n.id = ci.IdNino
        LEFT JOIN Clientes c ON c.id = n.idtutor
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

    $duracion = isset($fila['Tiempo']) ? (int) $fila['Tiempo'] : 60;
    if ($duracion <= 0) {
        $duracion = 60;
    }

    $fin = clone $inicio;
    $fin->modify('+' . $duracion . ' minutes');

    $eventos[] = [
        'id' => 'cita-' . (int) $fila['id'],
        'event_kind' => 'cita',
        'entity_id' => (int) $fila['id'],
        'paciente' => $fila['paciente'],
        'contacto_telefono' => $fila['contacto_telefono'],
        'contacto_correo' => $fila['contacto_correo'],
        'psicologo' => $fila['psicologo'],
        'psicologo_id' => (int) $fila['psicologo_id'],
        'psicologo_color' => $fila['psicologo_color'] ?? null,
        'programado' => $inicio->format(DateTime::ATOM),
        'termina' => $fin->format(DateTime::ATOM),
        'tiempo' => $duracion,
        'estatus' => $fila['estatus'],
        'tipo' => $fila['Tipo'],
        'forma_pago' => $fila['FormaPago'],
        'costo' => $fila['costo'] !== null ? (float) $fila['costo'] : null,
        'solicitudesReprogramacionPendientes' => (int) ($fila['solicitudesReprogramacionPendientes'] ?? 0),
        'solicitudesCancelacionPendientes' => (int) ($fila['solicitudesCancelacionPendientes'] ?? 0),
    ];
}
$stmtCitas->close();

$condicionesReservacion = ['rc.activo = 1'];
$tiposReservacion = '';
$parametrosReservacion = [];

if ($psicologoId !== null) {
    $condicionesReservacion[] = 'rc.psicologo_id = ?';
    $tiposReservacion .= 'i';
    $parametrosReservacion[] = $psicologoId;
}

if ($pacienteFiltro !== null) {
    $condicionesReservacion[] = 'LOWER(n.name) LIKE ?';
    $tiposReservacion .= 's';
    $parametrosReservacion[] = '%' . $pacienteFiltro . '%';
}

if ($fechaFin !== null) {
    $condicionesReservacion[] = 'rc.fecha_inicio <= DATE(?)';
    $tiposReservacion .= 's';
    $parametrosReservacion[] = $fechaFin;
}

if ($fechaInicio !== null) {
    $condicionesReservacion[] = '(rc.fecha_fin IS NULL OR rc.fecha_fin >= DATE(?))';
    $tiposReservacion .= 's';
    $parametrosReservacion[] = $fechaInicio;
}

$sqlReservaciones = 'SELECT rc.id,
                            rc.paciente_id,
                            rc.psicologo_id,
                            rc.tipo,
                            rc.hora_inicio,
                            rc.tiempo,
                            rc.fecha_inicio,
                            rc.fecha_fin,
                            rc.forzada,
                            n.name AS paciente,
                            u.name AS psicologo,
                            co.codigo_hex AS psicologo_color,
                            GROUP_CONCAT(rcd.dia_semana ORDER BY rcd.dia_semana SEPARATOR ",") AS dias_semana
                     FROM ReservacionContinua rc
                     INNER JOIN nino n ON n.id = rc.paciente_id
                     INNER JOIN Usuarios u ON u.id = rc.psicologo_id
                     LEFT JOIN colores co ON co.id = u.color_id
                     INNER JOIN ReservacionContinuaDia rcd ON rcd.reservacion_id = rc.id
                     WHERE ' . implode(' AND ', $condicionesReservacion) . '
                     GROUP BY rc.id, rc.paciente_id, rc.psicologo_id, rc.tipo, rc.hora_inicio, rc.tiempo, rc.fecha_inicio, rc.fecha_fin, rc.forzada, n.name, u.name, co.codigo_hex
                     ORDER BY rc.fecha_inicio ASC, rc.hora_inicio ASC';

$stmtReservaciones = $conn->prepare($sqlReservaciones);
if ($stmtReservaciones === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar la consulta de reservaciones continuas.']);
}

if ($tiposReservacion !== '') {
    $stmtReservaciones->bind_param($tiposReservacion, ...$parametrosReservacion);
}

if (!$stmtReservaciones->execute()) {
    $stmtReservaciones->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar la consulta de reservaciones continuas.']);
}

$rangeStartDate = $fechaInicio !== null ? new DateTime(substr($fechaInicio, 0, 10), $timezone) : new DateTime('first day of this month', $timezone);
$rangeEndDate = $fechaFin !== null ? new DateTime(substr($fechaFin, 0, 10), $timezone) : new DateTime('last day of this month', $timezone);
if ($fechaFin !== null) {
    $rangeEndDate->modify('-1 day');
}

$resultadoReservaciones = $stmtReservaciones->get_result();
while ($fila = $resultadoReservaciones->fetch_assoc()) {
    $diasSemana = array_values(array_filter(array_map('intval', explode(',', (string) ($fila['dias_semana'] ?? '')))));
    if ($diasSemana === []) {
        continue;
    }

    $fechaInicioReserva = DateTime::createFromFormat('Y-m-d', (string) $fila['fecha_inicio'], $timezone);
    if (!$fechaInicioReserva) {
        continue;
    }

    $fechaFinReserva = null;
    if (!empty($fila['fecha_fin'])) {
        $fechaFinReserva = DateTime::createFromFormat('Y-m-d', (string) $fila['fecha_fin'], $timezone);
    }

    $iteracionInicio = clone $rangeStartDate;
    if ($iteracionInicio < $fechaInicioReserva) {
        $iteracionInicio = clone $fechaInicioReserva;
    }

    $iteracionFin = clone $rangeEndDate;
    if ($fechaFinReserva instanceof DateTime && $iteracionFin > $fechaFinReserva) {
        $iteracionFin = clone $fechaFinReserva;
    }

    if ($iteracionInicio > $iteracionFin) {
        continue;
    }

    $duracion = isset($fila['tiempo']) ? (int) $fila['tiempo'] : 60;
    if ($duracion <= 0) {
        $duracion = 60;
    }

    $horaInicio = DateTime::createFromFormat('H:i:s', (string) $fila['hora_inicio'], $timezone);
    if (!$horaInicio) {
        continue;
    }

    $cursor = clone $iteracionInicio;
    while ($cursor <= $iteracionFin) {
        $diaSemana = (int) $cursor->format('N');
        if (in_array($diaSemana, $diasSemana, true)) {
            $inicioReserva = clone $cursor;
            $inicioReserva->setTime((int) $horaInicio->format('H'), (int) $horaInicio->format('i'), (int) $horaInicio->format('s'));
            $finReserva = clone $inicioReserva;
            $finReserva->modify('+' . $duracion . ' minutes');

            $eventos[] = [
                'id' => 'reservacion-' . (int) $fila['id'] . '-' . $cursor->format('Ymd'),
                'event_kind' => 'reservacion_continua',
                'entity_id' => (int) $fila['id'],
                'paciente' => $fila['paciente'],
                'psicologo' => $fila['psicologo'],
                'psicologo_id' => (int) $fila['psicologo_id'],
                'psicologo_color' => $fila['psicologo_color'] ?? null,
                'programado' => $inicioReserva->format(DateTime::ATOM),
                'termina' => $finReserva->format(DateTime::ATOM),
                'tiempo' => $duracion,
                'estatus' => 'Reservación continua',
                'tipo' => $fila['tipo'],
                'forma_pago' => null,
                'costo' => null,
                'forzada' => !empty($fila['forzada']),
                'solicitudesReprogramacionPendientes' => 0,
                'solicitudesCancelacionPendientes' => 0,
            ];
        }

        $cursor->modify('+1 day');
    }
}
$stmtReservaciones->close();

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
