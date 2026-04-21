<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/conflictos_agenda.php';
require_once __DIR__ . '/../Modulos/logger.php';

function responderReservacion(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function parseJsonReservacion(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        responderReservacion(400, ['success' => false, 'message' => 'El cuerpo de la petición no es válido.']);
        throw new RuntimeException('JSON inválido.');
    }

    return $data;
}

function normalizarFechaReservacion(string $valor, string $campo): string
{
    $valor = trim($valor);
    if ($valor === '') {
        responderReservacion(422, ['success' => false, 'message' => "El campo {$campo} es obligatorio."]);
        throw new RuntimeException('Fecha obligatoria.');
    }

    $dt = DateTime::createFromFormat('Y-m-d', $valor);
    if (!$dt) {
        responderReservacion(422, ['success' => false, 'message' => "El campo {$campo} debe tener formato YYYY-MM-DD."]);
        throw new RuntimeException('Fecha inválida.');
    }

    return $dt->format('Y-m-d');
}

function normalizarHoraReservacion(string $valor): string
{
    $valor = trim($valor);
    if ($valor === '') {
        responderReservacion(422, ['success' => false, 'message' => 'La hora de inicio es obligatoria.']);
        throw new RuntimeException('Hora obligatoria.');
    }

    $formatos = ['H:i', 'H:i:s'];
    foreach ($formatos as $formato) {
        $dt = DateTime::createFromFormat($formato, $valor);
        if ($dt instanceof DateTime) {
            return $dt->format('H:i:s');
        }
    }

    responderReservacion(422, ['success' => false, 'message' => 'La hora de inicio no es válida.']);
    throw new RuntimeException('Hora inválida.');
}

function obtenerFechaPrimeraOcurrencia(string $fechaInicio, int $diaSemana): string
{
    $fecha = new DateTime($fechaInicio);
    $objetivo = max(1, min(7, $diaSemana));
    while ((int) $fecha->format('N') !== $objetivo) {
        $fecha->modify('+1 day');
    }
    return $fecha->format('Y-m-d');
}

function buscarConflictoReservacionContinua(
    mysqli $conn,
    int $psicologoId,
    int $pacienteId,
    string $horaInicio,
    int $tiempo,
    string $fechaInicio,
    ?string $fechaFin,
    array $diasSemana
): ?array {
    $horaFin = (new DateTime('2000-01-01 ' . $horaInicio))->modify('+' . $tiempo . ' minutes')->format('H:i:s');

    foreach ($diasSemana as $diaSemana) {
        $stmtCita = $conn->prepare(
            'SELECT ci.id,
                    ci.Programado,
                    COALESCE(ci.Tiempo, 60) AS Tiempo,
                    DATE_ADD(ci.Programado, INTERVAL COALESCE(ci.Tiempo, 60) MINUTE) AS Termina,
                    n.name AS paciente,
                    u.name AS psicologo
             FROM Cita ci
             INNER JOIN nino n ON n.id = ci.IdNino
             INNER JOIN Usuarios u ON u.id = ci.IdUsuario
             WHERE ci.IdUsuario = ?
               AND ci.IdNino <> ?
               AND ci.Estatus IN (2, 3)
               AND DATE(ci.Programado) >= ?
               AND (? IS NULL OR DATE(ci.Programado) <= ?)
               AND (WEEKDAY(ci.Programado) + 1) = ?
               AND TIME(ci.Programado) < ?
               AND ADDTIME(TIME(ci.Programado), SEC_TO_TIME(COALESCE(ci.Tiempo, 60) * 60)) > ?
             ORDER BY ci.Programado ASC
             LIMIT 1'
        );
        if ($stmtCita === false) {
            throw new RuntimeException('No fue posible validar las citas existentes para la reservación continua.');
        }
        $stmtCita->bind_param('iisssiss', $psicologoId, $pacienteId, $fechaInicio, $fechaFin, $fechaFin, $diaSemana, $horaFin, $horaInicio);
        $stmtCita->execute();
        $resultadoCita = $stmtCita->get_result();
        $cita = $resultadoCita ? $resultadoCita->fetch_assoc() : null;
        $stmtCita->close();

        if (is_array($cita)) {
            return [
                'cita_id' => (int) $cita['id'],
                'programado' => (string) $cita['Programado'],
                'termina' => (string) $cita['Termina'],
                'tiempo' => (int) $cita['Tiempo'],
                'paciente' => (string) $cita['paciente'],
                'psicologo' => (string) $cita['psicologo'],
                'psicologo_id' => $psicologoId,
                'source_type' => 'cita',
            ];
        }

        $stmtReservacion = $conn->prepare(
            'SELECT rc.id,
                    rc.tipo,
                    rc.hora_inicio,
                    rc.tiempo,
                    n.name AS paciente,
                    u.name AS psicologo
             FROM ReservacionContinua rc
             INNER JOIN ReservacionContinuaDia rcd ON rcd.reservacion_id = rc.id
             INNER JOIN nino n ON n.id = rc.paciente_id
             INNER JOIN Usuarios u ON u.id = rc.psicologo_id
             WHERE rc.activo = 1
               AND rc.psicologo_id = ?
               AND rc.paciente_id <> ?
               AND rc.fecha_inicio <= ?
               AND (? IS NULL OR rc.fecha_fin IS NULL OR rc.fecha_fin >= ?)
               AND rcd.dia_semana = ?
               AND rc.hora_inicio < ?
               AND ADDTIME(rc.hora_inicio, SEC_TO_TIME(rc.tiempo * 60)) > ?
             ORDER BY rc.hora_inicio ASC
             LIMIT 1'
        );
        if ($stmtReservacion === false) {
            throw new RuntimeException('No fue posible validar las reservaciones continuas existentes.');
        }
        $fechaMuestra = obtenerFechaPrimeraOcurrencia($fechaInicio, $diaSemana);
        $fechaLimite = $fechaFin ?? $fechaInicio;
        $stmtReservacion->bind_param('iisssiss', $psicologoId, $pacienteId, $fechaLimite, $fechaFin, $fechaInicio, $diaSemana, $horaFin, $horaInicio);
        $stmtReservacion->execute();
        $resultadoReservacion = $stmtReservacion->get_result();
        $reservacion = $resultadoReservacion ? $resultadoReservacion->fetch_assoc() : null;
        $stmtReservacion->close();

        if (is_array($reservacion)) {
            $inicioMuestra = new DateTime($fechaMuestra . ' ' . (string) $reservacion['hora_inicio']);
            $finMuestra = clone $inicioMuestra;
            $finMuestra->modify('+' . max(1, (int) $reservacion['tiempo']) . ' minutes');

            return [
                'cita_id' => 0,
                'reservacion_id' => (int) $reservacion['id'],
                'programado' => $inicioMuestra->format('Y-m-d H:i:s'),
                'termina' => $finMuestra->format('Y-m-d H:i:s'),
                'tiempo' => (int) $reservacion['tiempo'],
                'paciente' => (string) $reservacion['paciente'],
                'psicologo' => (string) $reservacion['psicologo'],
                'psicologo_id' => $psicologoId,
                'tipo' => (string) $reservacion['tipo'],
                'source_type' => 'reservacion_continua',
            ];
        }
    }

    return null;
}

if (!isset($_SESSION['id'])) {
    responderReservacion(401, ['success' => false, 'message' => 'No autenticado.']);
}

$rolSesion = (int) ($_SESSION['rol'] ?? 0);
if ($rolSesion === 6) {
    responderReservacion(403, ['success' => false, 'message' => 'No tienes permisos para crear reservaciones continuas.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    responderReservacion(405, ['success' => false, 'message' => 'Método no permitido.']);
}

$payload = parseJsonReservacion();
$pacienteId = isset($payload['paciente_id']) ? (int) $payload['paciente_id'] : 0;
$psicologoId = isset($payload['psicologo_id']) ? (int) $payload['psicologo_id'] : 0;
$tipo = trim((string) ($payload['tipo'] ?? ''));
$horaInicio = normalizarHoraReservacion((string) ($payload['hora_inicio'] ?? ''));
$fechaInicio = normalizarFechaReservacion((string) ($payload['fecha_inicio'] ?? ''), 'fecha_inicio');
$fechaFin = null;
if (isset($payload['fecha_fin']) && trim((string) $payload['fecha_fin']) !== '') {
    $fechaFin = normalizarFechaReservacion((string) $payload['fecha_fin'], 'fecha_fin');
}
$tiempo = isset($payload['tiempo']) ? (int) $payload['tiempo'] : 60;
$diasSemana = isset($payload['dias_semana']) && is_array($payload['dias_semana']) ? $payload['dias_semana'] : [];
$forzar = !empty($payload['forzar']);

if ($pacienteId <= 0 || $psicologoId <= 0) {
    responderReservacion(422, ['success' => false, 'message' => 'Paciente y psicóloga son obligatorios.']);
}

if ($tipo !== 'Cita' && $tipo !== 'Diagnostico') {
    responderReservacion(422, ['success' => false, 'message' => 'El tipo debe ser Cita o Diagnostico.']);
}

if ($tiempo <= 0) {
    responderReservacion(422, ['success' => false, 'message' => 'El tiempo debe ser mayor a cero.']);
}

if ($fechaFin !== null && $fechaFin < $fechaInicio) {
    responderReservacion(422, ['success' => false, 'message' => 'La fecha fin no puede ser menor a la fecha inicio.']);
}

$diasNormalizados = [];
foreach ($diasSemana as $dia) {
    $diaInt = (int) $dia;
    if ($diaInt >= 1 && $diaInt <= 7) {
        $diasNormalizados[$diaInt] = $diaInt;
    }
}
$diasNormalizados = array_values($diasNormalizados);

if (count($diasNormalizados) < 1 || count($diasNormalizados) > 3) {
    responderReservacion(422, ['success' => false, 'message' => 'Selecciona de 1 a 3 días diferentes de la semana.']);
}

$conn = conectar();
if (!($conn instanceof mysqli) || $conn->connect_errno) {
    responderReservacion(500, ['success' => false, 'message' => 'No fue posible conectar con la base de datos.']);
}
$conn->set_charset('utf8mb4');

$conn->begin_transaction();

try {
    $conflicto = buscarConflictoReservacionContinua($conn, $psicologoId, $pacienteId, $horaInicio, $tiempo, $fechaInicio, $fechaFin, $diasNormalizados);
    if ($conflicto !== null && !$forzar) {
        $conn->rollback();
        $conn->close();
        responderReservacion(409, array_merge([
            'success' => false,
        ], construirPayloadConflictoAgenda($conflicto, 'La psicóloga seleccionada ya tiene una cita o reservación en ese horario.')));
    }

    $forzada = ($conflicto !== null && $forzar) ? 1 : 0;

    $stmt = $conn->prepare('INSERT INTO ReservacionContinua (paciente_id, psicologo_id, tipo, hora_inicio, tiempo, fecha_inicio, fecha_fin, forzada, activo, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
    if ($stmt === false) {
        throw new RuntimeException('No fue posible preparar la reservación continua.');
    }
    $creadoPor = (int) $_SESSION['id'];
    $stmt->bind_param('iississii', $pacienteId, $psicologoId, $tipo, $horaInicio, $tiempo, $fechaInicio, $fechaFin, $forzada, $creadoPor);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('No fue posible guardar la reservación continua.');
    }
    $reservacionId = (int) $conn->insert_id;
    $stmt->close();

    $stmtDia = $conn->prepare('INSERT INTO ReservacionContinuaDia (reservacion_id, dia_semana) VALUES (?, ?)');
    if ($stmtDia === false) {
        throw new RuntimeException('No fue posible guardar los días de la reservación.');
    }
    foreach ($diasNormalizados as $diaSemana) {
        $stmtDia->bind_param('ii', $reservacionId, $diaSemana);
        if (!$stmtDia->execute()) {
            $stmtDia->close();
            throw new RuntimeException('No fue posible guardar los días de la reservación.');
        }
    }
    $stmtDia->close();

    registrarLog(
        $conn,
        $_SESSION['id'],
        'reservaciones_continuas',
        'crear',
        sprintf('Se creó la reservación continua #%d para el paciente %d con la psicóloga %d.%s', $reservacionId, $pacienteId, $psicologoId, $forzada === 1 ? ' Marcada como forzada.' : ''),
        'ReservacionContinua',
        (string) $reservacionId
    );

    $conn->commit();
    $conn->close();
    responderReservacion(201, ['success' => true, 'id' => $reservacionId, 'forzada' => $forzada === 1]);
} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    responderReservacion(400, ['success' => false, 'message' => $e->getMessage()]);
}
