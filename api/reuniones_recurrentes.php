<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../conexion.php';

function responderReunionRecurrente(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function leerJsonReunionRecurrente(): array
{
    $raw = file_get_contents('php://input');
    $payload = json_decode((string) $raw, true);
    if (!is_array($payload)) {
        responderReunionRecurrente(400, ['success' => false, 'message' => 'Datos inválidos.']);
    }
    return $payload;
}

function validarFechaRecurrente(string $valor, string $campo): string
{
    $dt = DateTime::createFromFormat('Y-m-d', trim($valor));
    if (!$dt || $dt->format('Y-m-d') !== trim($valor)) {
        responderReunionRecurrente(422, ['success' => false, 'message' => "{$campo} no tiene un formato válido."]);
    }
    return $dt->format('Y-m-d');
}

function validarHoraRecurrente(string $valor, string $campo): string
{
    $valor = trim($valor);
    foreach (['H:i', 'H:i:s'] as $formato) {
        $dt = DateTime::createFromFormat($formato, $valor);
        if ($dt instanceof DateTime) {
            return $dt->format('H:i:s');
        }
    }
    responderReunionRecurrente(422, ['success' => false, 'message' => "{$campo} no tiene un formato válido."]);
}

function obtenerIdRecurrente(): int
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        responderReunionRecurrente(422, ['success' => false, 'message' => 'La recurrencia es obligatoria.']);
    }
    return $id;
}

$usuarioId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
if ($usuarioId <= 0) {
    responderReunionRecurrente(401, ['success' => false, 'message' => 'No autenticado.']);
}
if ($rolUsuario === 6) {
    responderReunionRecurrente(403, ['success' => false, 'message' => 'No tienes permisos para gestionar reuniones recurrentes.']);
}

$conn = conectar();
if (!($conn instanceof mysqli) || $conn->connect_errno) {
    responderReunionRecurrente(500, ['success' => false, 'message' => 'No fue posible conectar con la base de datos.']);
}
$conn->set_charset('utf8mb4');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'POST') {
    $payload = leerJsonReunionRecurrente();
    $titulo = trim((string) ($payload['titulo'] ?? ''));
    $descripcion = trim((string) ($payload['descripcion'] ?? ''));
    $frecuencia = trim((string) ($payload['frecuencia'] ?? ''));
    $participantes = $payload['psicologos'] ?? [];
    $fechaInicio = validarFechaRecurrente((string) ($payload['fecha_inicio'] ?? ''), 'fecha_inicio');
    $fechaFin = null;
    if (isset($payload['fecha_fin']) && trim((string) $payload['fecha_fin']) !== '') {
        $fechaFin = validarFechaRecurrente((string) $payload['fecha_fin'], 'fecha_fin');
    }
    $horaInicio = validarHoraRecurrente((string) ($payload['hora_inicio'] ?? ''), 'hora_inicio');
    $horaFin = validarHoraRecurrente((string) ($payload['hora_fin'] ?? ''), 'hora_fin');
    $intervalo = max(1, (int) ($payload['intervalo'] ?? 1));
    $bloqueaAgenda = !empty($payload['bloquea_agenda']) ? 1 : 0;

    if ($titulo === '') {
        responderReunionRecurrente(422, ['success' => false, 'message' => 'El título es obligatorio.']);
    }
    if (!in_array($frecuencia, ['semanal', 'mensual_dia_semana', 'anual_aviso'], true)) {
        responderReunionRecurrente(422, ['success' => false, 'message' => 'La frecuencia no es válida.']);
    }
    if ($horaFin <= $horaInicio) {
        responderReunionRecurrente(422, ['success' => false, 'message' => 'La hora fin debe ser mayor a la hora inicio.']);
    }
    if ($fechaFin !== null && $fechaFin < $fechaInicio) {
        responderReunionRecurrente(422, ['success' => false, 'message' => 'La fecha fin no puede ser menor a la fecha inicio.']);
    }

    $idsPsicologos = [];
    if (is_array($participantes)) {
        foreach ($participantes as $id) {
            $idNumero = (int) $id;
            if ($idNumero > 0) {
                $idsPsicologos[$idNumero] = $idNumero;
            }
        }
    }
    if ($bloqueaAgenda === 1 && count($idsPsicologos) === 0) {
        responderReunionRecurrente(422, ['success' => false, 'message' => 'Selecciona al menos una psicóloga para bloquear agenda.']);
    }

    $inicioDt = new DateTime($fechaInicio . ' ' . $horaInicio);
    $diaSemana = (int) $inicioDt->format('N');
    $semanaMes = (int) ceil(((int) $inicioDt->format('j')) / 7);
    $mesAnual = (int) $inicioDt->format('n');
    $diaAnual = (int) $inicioDt->format('j');

    if ($frecuencia === 'anual_aviso') {
        $bloqueaAgenda = 0;
        $idsPsicologos = [];
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('INSERT INTO ReunionInternaRecurrencia (titulo, descripcion, fecha_inicio, fecha_fin, hora_inicio, hora_fin, frecuencia, intervalo, dia_semana, semana_mes, mes_anual, dia_anual, bloquea_agenda, activo, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
        if ($stmt === false) {
            throw new RuntimeException('No fue posible preparar la recurrencia.');
        }
        $stmt->bind_param('sssssssiiiiiii', $titulo, $descripcion, $fechaInicio, $fechaFin, $horaInicio, $horaFin, $frecuencia, $intervalo, $diaSemana, $semanaMes, $mesAnual, $diaAnual, $bloqueaAgenda, $usuarioId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('No fue posible guardar la recurrencia.');
        }
        $recurrenciaId = (int) $conn->insert_id;
        $stmt->close();

        if ($idsPsicologos !== []) {
            $stmtParticipante = $conn->prepare('INSERT INTO ReunionInternaRecurrenciaPsicologo (recurrencia_id, psicologo_id) VALUES (?, ?)');
            if ($stmtParticipante === false) {
                throw new RuntimeException('No fue posible preparar los participantes.');
            }
            foreach ($idsPsicologos as $psicologoId) {
                $stmtParticipante->bind_param('ii', $recurrenciaId, $psicologoId);
                if (!$stmtParticipante->execute()) {
                    $stmtParticipante->close();
                    throw new RuntimeException('No fue posible guardar los participantes.');
                }
            }
            $stmtParticipante->close();
        }

        $conn->commit();
        responderReunionRecurrente(201, ['success' => true, 'id' => $recurrenciaId, 'message' => 'Reunión recurrente guardada correctamente.']);
    } catch (Throwable $e) {
        $conn->rollback();
        responderReunionRecurrente(400, ['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($metodo === 'PUT') {
    $id = obtenerIdRecurrente();
    $payload = leerJsonReunionRecurrente();
    $inicio = validarHoraRecurrente((string) ($payload['hora_inicio'] ?? ''), 'hora_inicio');
    $fin = validarHoraRecurrente((string) ($payload['hora_fin'] ?? ''), 'hora_fin');
    $fechaReferencia = isset($payload['fecha_referencia']) ? validarFechaRecurrente((string) $payload['fecha_referencia'], 'fecha_referencia') : null;
    if ($fin <= $inicio) {
        responderReunionRecurrente(422, ['success' => false, 'message' => 'La hora fin debe ser mayor a la hora inicio.']);
    }

    if ($fechaReferencia !== null) {
        $referencia = new DateTime($fechaReferencia . ' ' . $inicio);
        $diaSemana = (int) $referencia->format('N');
        $semanaMes = (int) ceil(((int) $referencia->format('j')) / 7);
        $mesAnual = (int) $referencia->format('n');
        $diaAnual = (int) $referencia->format('j');
        $stmt = $conn->prepare('UPDATE ReunionInternaRecurrencia SET hora_inicio = ?, hora_fin = ?, dia_semana = ?, semana_mes = ?, mes_anual = ?, dia_anual = ? WHERE id = ? AND activo = 1');
        if ($stmt === false) {
            responderReunionRecurrente(500, ['success' => false, 'message' => 'No fue posible preparar la actualización.']);
        }
        $stmt->bind_param('ssiiiii', $inicio, $fin, $diaSemana, $semanaMes, $mesAnual, $diaAnual, $id);
    } else {
        $stmt = $conn->prepare('UPDATE ReunionInternaRecurrencia SET hora_inicio = ?, hora_fin = ? WHERE id = ? AND activo = 1');
        if ($stmt === false) {
            responderReunionRecurrente(500, ['success' => false, 'message' => 'No fue posible preparar la actualización.']);
        }
        $stmt->bind_param('ssi', $inicio, $fin, $id);
    }
    if ($stmt === false) {
        responderReunionRecurrente(500, ['success' => false, 'message' => 'No fue posible preparar la actualización.']);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        responderReunionRecurrente(500, ['success' => false, 'message' => 'No fue posible mover la recurrencia.']);
    }
    $stmt->close();
    responderReunionRecurrente(200, ['success' => true, 'message' => 'Serie recurrente movida correctamente.']);
}

if ($metodo === 'DELETE') {
    $id = obtenerIdRecurrente();
    $scope = isset($_GET['scope']) ? trim((string) $_GET['scope']) : 'series';
    if ($scope === 'occurrence') {
        $fecha = validarFechaRecurrente((string) ($_GET['date'] ?? ''), 'date');
        $stmt = $conn->prepare("INSERT IGNORE INTO ReunionInternaRecurrenciaExcepcion (recurrencia_id, fecha_ocurrencia, accion, creado_por) VALUES (?, ?, 'cancelada', ?)");
        if ($stmt === false) {
            responderReunionRecurrente(500, ['success' => false, 'message' => 'No fue posible preparar la cancelación temporal.']);
        }
        $stmt->bind_param('isi', $id, $fecha, $usuarioId);
        if (!$stmt->execute()) {
            $stmt->close();
            responderReunionRecurrente(500, ['success' => false, 'message' => 'No fue posible cancelar la ocurrencia.']);
        }
        $stmt->close();
        responderReunionRecurrente(200, ['success' => true, 'message' => 'Ocurrencia cancelada correctamente.']);
    }

    $stmt = $conn->prepare('UPDATE ReunionInternaRecurrencia SET activo = 0 WHERE id = ?');
    if ($stmt === false) {
        responderReunionRecurrente(500, ['success' => false, 'message' => 'No fue posible preparar la cancelación.']);
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        $stmt->close();
        responderReunionRecurrente(500, ['success' => false, 'message' => 'No fue posible cancelar la recurrencia.']);
    }
    $stmt->close();
    responderReunionRecurrente(200, ['success' => true, 'message' => 'Recurrencia cancelada correctamente.']);
}

responderReunionRecurrente(405, ['success' => false, 'message' => 'Método no permitido.']);
