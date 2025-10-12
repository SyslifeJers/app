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
    echo json_encode(['error' => 'No tienes permisos para ajustar el saldo directamente.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
    echo json_encode(['error' => 'El monto debe ser distinto de cero.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/saldo_pacientes.php';
require_once __DIR__ . '/../Modulos/logger.php';

$conn = conectar();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible conectar con la base de datos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$conn->begin_transaction();

try {
    $stmtPaciente = $conn->prepare('SELECT name, saldo_paquete FROM nino WHERE id = ? FOR UPDATE');
    if ($stmtPaciente === false) {
        throw new RuntimeException('No fue posible preparar la consulta del paciente.');
    }

    $stmtPaciente->bind_param('i', $ninoId);

    if (!$stmtPaciente->execute()) {
        throw new RuntimeException('No fue posible obtener la información del paciente.');
    }

    $stmtPaciente->bind_result($pacienteNombre, $saldoActual);
    if (!$stmtPaciente->fetch()) {
        $stmtPaciente->close();
        throw new RuntimeException('Paciente no encontrado.');
    }

    $stmtPaciente->close();

    $saldoActual = (float) $saldoActual;

    if (!ajustarSaldoPaciente($conn, $ninoId, $monto)) {
        throw new RuntimeException('No fue posible actualizar el saldo del paciente.');
    }

    $nuevoSaldo = $saldoActual + $monto;

    $descripcion = sprintf(
        'Se aplicó un ajuste directo al saldo del paciente %s. Monto: %s. Saldo anterior: %s. Saldo actual: %s%s',
        $pacienteNombre,
        number_format($monto, 2),
        number_format($saldoActual, 2),
        number_format($nuevoSaldo, 2),
        $comentario !== '' ? ' Comentario: ' . $comentario : ''
    );

    registrarLog(
        $conn,
        (int) $_SESSION['id'],
        'pacientes',
        'ajuste_saldo_directo',
        $descripcion,
        'Paciente',
        (string) $ninoId
    );

    $conn->commit();

    echo json_encode([
        'success' => true,
        'nuevoSaldo' => $nuevoSaldo,
        'paciente' => $pacienteNombre,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    $conn->close();
}
