<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once __DIR__ . '/../conexion.php';

$calendarResponseSent = false;

function jsonResponse(int $statusCode, array $payload): void
{
    global $calendarResponseSent;
    $calendarResponseSent = true;
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    jsonResponse(500, [
        'error' => 'Error interno del calendario.',
        'debug' => [
            'type' => 'php_error',
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ],
    ]);
});

set_exception_handler(static function (Throwable $exception): void {
    jsonResponse(500, [
        'error' => 'Excepción interna del calendario.',
        'debug' => [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ],
    ]);
});

register_shutdown_function(static function (): void {
    global $calendarResponseSent;
    if ($calendarResponseSent) {
        return;
    }

    $error = error_get_last();
    if (!is_array($error)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
    if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Error fatal interno del calendario.',
        'debug' => [
            'type' => 'fatal_error',
            'message' => $error['message'] ?? '',
            'file' => $error['file'] ?? '',
            'line' => $error['line'] ?? 0,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

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

function nthWeekdayOfMonth(int $year, int $month, int $weekday, int $weekNumber, DateTimeZone $timezone): DateTime
{
    $date = new DateTime(sprintf('%04d-%02d-01', $year, $month), $timezone);
    while ((int) $date->format('N') !== $weekday) {
        $date->modify('+1 day');
    }

    $candidate = clone $date;
    $candidate->modify('+' . max(0, $weekNumber - 1) . ' week');

    if ((int) $candidate->format('n') === $month) {
        return $candidate;
    }

    $last = new DateTime(sprintf('%04d-%02d-01', $year, $month), $timezone);
    $last->modify('last day of this month');
    while ((int) $last->format('N') !== $weekday) {
        $last->modify('-1 day');
    }
    return $last;
}

function aplicarHora(DateTime $fecha, string $hora): DateTime
{
    $horaDt = DateTime::createFromFormat('H:i:s', $hora) ?: DateTime::createFromFormat('H:i', $hora);
    $resultado = clone $fecha;
    if ($horaDt instanceof DateTime) {
        $resultado->setTime((int) $horaDt->format('H'), (int) $horaDt->format('i'), (int) $horaDt->format('s'));
    }
    return $resultado;
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
                 ci.IdNino AS paciente_id,
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
    jsonResponse(500, ['error' => 'No fue posible preparar la consulta de citas.', 'debug' => ['mysqli_error' => $conn->error, 'sql' => $sqlCitas]]);
}

if ($tiposCita !== '') {
    $stmtCitas->bind_param($tiposCita, ...$parametrosCita);
}

if (!$stmtCitas->execute()) {
    $errorCitas = $stmtCitas->error;
    $stmtCitas->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar la consulta de citas.', 'debug' => ['mysqli_error' => $errorCitas]]);
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
        'paciente_id' => (int) $fila['paciente_id'],
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
    jsonResponse(500, ['error' => 'No fue posible preparar la consulta de reservaciones continuas.', 'debug' => ['mysqli_error' => $conn->error, 'sql' => $sqlReservaciones]]);
}

if ($tiposReservacion !== '') {
    $stmtReservaciones->bind_param($tiposReservacion, ...$parametrosReservacion);
}

if (!$stmtReservaciones->execute()) {
    $errorReservaciones = $stmtReservaciones->error;
    $stmtReservaciones->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar la consulta de reservaciones continuas.', 'debug' => ['mysqli_error' => $errorReservaciones]]);
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
                'paciente_id' => (int) $fila['paciente_id'],
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
    jsonResponse(500, ['error' => 'No fue posible preparar la consulta de reuniones.', 'debug' => ['mysqli_error' => $conn->error, 'sql' => $sqlReuniones]]);
}

if ($tiposReunion !== '') {
    $stmtReuniones->bind_param($tiposReunion, ...$parametrosReunion);
}

if (!$stmtReuniones->execute()) {
    $errorReuniones = $stmtReuniones->error;
    $stmtReuniones->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar la consulta de reuniones.', 'debug' => ['mysqli_error' => $errorReuniones]]);
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
        'bloquea_agenda' => true,
        'solicitudesReprogramacionPendientes' => 0,
        'solicitudesCancelacionPendientes' => 0,
    ];
}

$stmtReuniones->close();

if (true) {
    try {
    $rangeStart = $fechaInicio !== null ? new DateTime($fechaInicio, $timezone) : new DateTime('first day of this month', $timezone);
    $rangeEnd = $fechaFin !== null ? new DateTime($fechaFin, $timezone) : new DateTime('last day of this month 23:59:59', $timezone);
    $rangeStartDate = clone $rangeStart;
    $rangeStartDate->setTime(0, 0, 0);
    $rangeEndDate = clone $rangeEnd;
    $rangeEndDate->setTime(23, 59, 59);

    $condicionesRecurrencia = ['rir.activo = 1', 'rir.fecha_inicio <= DATE(?)', '(rir.fecha_fin IS NULL OR rir.fecha_fin >= DATE(?))'];
    $tiposRecurrencia = 'ss';
    $parametrosRecurrencia = [$rangeEndDate->format('Y-m-d'), $rangeStartDate->format('Y-m-d')];

    if ($psicologoId !== null) {
        $condicionesRecurrencia[] = '(rir.bloquea_agenda = 0 OR EXISTS (SELECT 1 FROM ReunionInternaRecurrenciaPsicologo rirp_f WHERE rirp_f.recurrencia_id = rir.id AND rirp_f.psicologo_id = ?))';
        $tiposRecurrencia .= 'i';
        $parametrosRecurrencia[] = $psicologoId;
    }

    $sqlRecurrencias = 'SELECT rir.id,
                               rir.titulo,
                               rir.descripcion,
                               rir.fecha_inicio,
                               rir.fecha_fin,
                               rir.hora_inicio,
                               rir.hora_fin,
                               rir.frecuencia,
                               rir.intervalo,
                               rir.dia_semana,
                               rir.semana_mes,
                               rir.mes_anual,
                               rir.dia_anual,
                               rir.bloquea_agenda,
                               GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ", ") AS psicologos,
                               MIN(rirp.psicologo_id) AS psicologo_id,
                               MIN(co.codigo_hex) AS psicologo_color,
                               GROUP_CONCAT(DISTINCT ex.fecha_ocurrencia ORDER BY ex.fecha_ocurrencia SEPARATOR ",") AS fechas_canceladas
                        FROM ReunionInternaRecurrencia rir
                        LEFT JOIN ReunionInternaRecurrenciaPsicologo rirp ON rirp.recurrencia_id = rir.id
                        LEFT JOIN Usuarios u ON u.id = rirp.psicologo_id
                        LEFT JOIN colores co ON co.id = u.color_id
                        LEFT JOIN ReunionInternaRecurrenciaExcepcion ex ON ex.recurrencia_id = rir.id AND ex.accion = \'cancelada\'
                        WHERE ' . implode(' AND ', $condicionesRecurrencia) . '
                        GROUP BY rir.id, rir.titulo, rir.descripcion, rir.fecha_inicio, rir.fecha_fin, rir.hora_inicio, rir.hora_fin, rir.frecuencia, rir.intervalo, rir.dia_semana, rir.semana_mes, rir.mes_anual, rir.dia_anual, rir.bloquea_agenda
                        ORDER BY rir.fecha_inicio ASC';

    $stmtRecurrencias = $conn->prepare($sqlRecurrencias);
    if ($stmtRecurrencias === false) {
        error_log('citas_calendario: error al preparar reuniones recurrentes: ' . $conn->error);
    } else {
        $stmtRecurrencias->bind_param($tiposRecurrencia, ...$parametrosRecurrencia);
        if (!$stmtRecurrencias->execute()) {
            error_log('citas_calendario: error al ejecutar reuniones recurrentes: ' . $stmtRecurrencias->error);
            $stmtRecurrencias->close();
            $stmtRecurrencias = null;
        }
    }

    $resultadoRecurrencias = $stmtRecurrencias instanceof mysqli_stmt ? $stmtRecurrencias->get_result() : null;
    while ($resultadoRecurrencias instanceof mysqli_result && ($fila = $resultadoRecurrencias->fetch_assoc())) {
        $inicioSerie = DateTime::createFromFormat('Y-m-d', (string) $fila['fecha_inicio'], $timezone);
        if (!$inicioSerie) {
            continue;
        }
        $finSerie = !empty($fila['fecha_fin']) ? DateTime::createFromFormat('Y-m-d', (string) $fila['fecha_fin'], $timezone) : null;
        $canceladas = array_flip(array_filter(explode(',', (string) ($fila['fechas_canceladas'] ?? ''))));
        $intervalo = max(1, (int) $fila['intervalo']);
        $frecuencia = (string) $fila['frecuencia'];
        $ocurrencias = [];

        if ($frecuencia === 'semanal') {
            $cursor = clone $inicioSerie;
            while ((int) $cursor->format('N') !== (int) $fila['dia_semana']) {
                $cursor->modify('+1 day');
            }
            while ($cursor <= $rangeEndDate) {
                if ($cursor >= $rangeStartDate && ($finSerie === null || $cursor <= $finSerie)) {
                    $ocurrencias[] = clone $cursor;
                }
                $cursor->modify('+' . $intervalo . ' week');
            }
        } elseif ($frecuencia === 'mensual_dia_semana') {
            $cursor = new DateTime($rangeStartDate->format('Y-m-01'), $timezone);
            $serieMonth = ((int) $inicioSerie->format('Y')) * 12 + (int) $inicioSerie->format('n');
            while ($cursor <= $rangeEndDate) {
                $cursorMonth = ((int) $cursor->format('Y')) * 12 + (int) $cursor->format('n');
                if ($cursorMonth >= $serieMonth && (($cursorMonth - $serieMonth) % $intervalo) === 0) {
                    $candidate = nthWeekdayOfMonth((int) $cursor->format('Y'), (int) $cursor->format('n'), (int) $fila['dia_semana'], (int) $fila['semana_mes'], $timezone);
                    if ($candidate >= $rangeStartDate && $candidate <= $rangeEndDate && $candidate >= $inicioSerie && ($finSerie === null || $candidate <= $finSerie)) {
                        $ocurrencias[] = $candidate;
                    }
                }
                $cursor->modify('first day of next month');
            }
        } elseif ($frecuencia === 'anual_aviso') {
            for ($year = (int) $rangeStartDate->format('Y'); $year <= (int) $rangeEndDate->format('Y'); $year++) {
                $candidate = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, (int) $fila['mes_anual'], (int) $fila['dia_anual']), $timezone);
                if ($candidate && $candidate >= $rangeStartDate && $candidate <= $rangeEndDate && $candidate >= $inicioSerie && ($finSerie === null || $candidate <= $finSerie)) {
                    $ocurrencias[] = $candidate;
                }
            }
        }

        foreach ($ocurrencias as $ocurrencia) {
            $fechaOcurrencia = $ocurrencia->format('Y-m-d');
            if (isset($canceladas[$fechaOcurrencia])) {
                continue;
            }
            $inicio = aplicarHora($ocurrencia, (string) $fila['hora_inicio']);
            $fin = aplicarHora($ocurrencia, (string) $fila['hora_fin']);
            $bloqueaAgenda = (int) $fila['bloquea_agenda'] === 1;

            $eventos[] = [
                'id' => 'reunion-recurrente-' . (int) $fila['id'] . '-' . $inicio->format('Ymd'),
                'event_kind' => $bloqueaAgenda ? 'reunion_recurrente' : 'aviso_anual',
                'entity_id' => (int) $fila['id'],
                'occurrence_date' => $fechaOcurrencia,
                'paciente' => null,
                'psicologo' => $bloqueaAgenda ? ($fila['psicologos'] ?? '') : '',
                'psicologo_id' => isset($fila['psicologo_id']) ? (int) $fila['psicologo_id'] : null,
                'psicologo_color' => $fila['psicologo_color'] ?? null,
                'programado' => $inicio->format(DateTime::ATOM),
                'termina' => $fin->format(DateTime::ATOM),
                'estatus' => $bloqueaAgenda ? 'Recurrente' : 'Aviso anual',
                'tipo' => $fila['titulo'] ?: ($bloqueaAgenda ? 'Reunión recurrente' : 'Aviso anual'),
                'forma_pago' => null,
                'costo' => null,
                'bloquea_agenda' => $bloqueaAgenda,
                'solicitudesReprogramacionPendientes' => 0,
                'solicitudesCancelacionPendientes' => 0,
            ];
        }
    }
    if ($stmtRecurrencias instanceof mysqli_stmt) {
        $stmtRecurrencias->close();
    }
    } catch (Throwable $e) {
        error_log('citas_calendario: se omitieron reuniones recurrentes: ' . $e->getMessage());
    }
}

usort($eventos, static function (array $a, array $b): int {
    return strcmp(($a['programado'] ?? '') . '', ($b['programado'] ?? '') . '');
});

$conn->close();
jsonResponse(200, ['data' => $eventos]);
