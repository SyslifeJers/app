<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
if (!in_array($rolUsuario, [3, 5], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'No tienes permisos para solicitar ajustes de saldo.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$ninoId = isset($payload['nino_id'])
    ? filter_var($payload['nino_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : null;
$monto = isset($payload['monto']) ? (float) $payload['monto'] : null;
$comentario = isset($payload['comentario']) ? trim((string) $payload['comentario']) : '';

if ($ninoId === null || $monto === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Los parámetros nino_id y monto son obligatorios.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_finite($monto) || abs($monto) < 0.01) {
    http_response_code(422);
    echo json_encode(['error' => 'El monto debe ser diferente de cero.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/logger.php';

$conn = conectar();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible conectar con la base de datos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmtPaciente = $conn->prepare('SELECT name, saldo_paquete FROM nino WHERE id = ?');
if ($stmtPaciente === false) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible preparar la consulta del paciente.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $conn->close();
    exit;
}

$stmtPaciente->bind_param('i', $ninoId);

if (!$stmtPaciente->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible obtener la información del paciente.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmtPaciente->close();
    $conn->close();
    exit;
}

$stmtPaciente->bind_result($pacienteNombre, $saldoActual);
if (!$stmtPaciente->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Paciente no encontrado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmtPaciente->close();
    $conn->close();
    exit;
}

$stmtPaciente->close();
$saldoActual = (float) $saldoActual;
$saldoSolicitado = $saldoActual + $monto;

$stmtPendientes = $conn->prepare("SELECT COUNT(*) FROM SolicitudAjusteSaldo WHERE nino_id = ? AND estatus = 'pendiente'");
if ($stmtPendientes === false) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible validar solicitudes previas.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $conn->close();
    exit;
}

$stmtPendientes->bind_param('i', $ninoId);
$stmtPendientes->execute();
$stmtPendientes->bind_result($pendientes);
$stmtPendientes->fetch();
$stmtPendientes->close();

if ((int) $pendientes > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Ya existe una solicitud pendiente para este paciente.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $conn->close();
    exit;
}

$conn->begin_transaction();

try {
    $stmtInsert = $conn->prepare("INSERT INTO SolicitudAjusteSaldo (nino_id, solicitado_por, monto, saldo_anterior, saldo_solicitado, comentario, estatus, fecha_solicitud) VALUES (?, ?, ?, ?, ?, ?, 'pendiente', NOW())");
    if ($stmtInsert === false) {
        throw new RuntimeException('No fue posible preparar el registro de la solicitud.');
    }

    $usuarioId = (int) $_SESSION['id'];

    $stmtInsert->bind_param('iiddis', $ninoId, $usuarioId, $monto, $saldoActual, $saldoSolicitado, $comentario);

    if (!$stmtInsert->execute()) {
        throw new RuntimeException('No fue posible registrar la solicitud.');
    }

    $solicitudId = $conn->insert_id;
    $stmtInsert->close();

    $descripcion = sprintf(
        'Solicitud #%d para ajustar el saldo del paciente %s. Saldo actual: %s. Monto solicitado: %s. Saldo resultante esperado: %s%s',
        $solicitudId,
        $pacienteNombre,
        number_format($saldoActual, 2),
        number_format($monto, 2),
        number_format($saldoSolicitado, 2),
        $comentario !== '' ? ' Comentario: ' . $comentario : ''
    );

    registrarLog(
        $conn,
        $usuarioId,
        'pacientes',
        'solicitar_ajuste_saldo',
        $descripcion,
        'Paciente',
        (string) $ninoId
    );

    $conn->commit();

    echo json_encode([
        'success' => true,
        'solicitudId' => $solicitudId,
        'saldoActual' => $saldoActual,
        'saldoSolicitado' => $saldoSolicitado,
        'paciente' => $pacienteNombre,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    $conn->close();
}
