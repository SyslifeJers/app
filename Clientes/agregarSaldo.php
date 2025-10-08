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
    echo json_encode(['error' => 'No tienes permisos para agregar saldo.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

$ninoId = isset($payload['nino_id']) ? filter_var($payload['nino_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
$monto = isset($payload['monto']) ? (float) $payload['monto'] : null;
$comentario = isset($payload['comentario']) ? trim((string) $payload['comentario']) : '';

if ($ninoId === null || $monto === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Los parámetros nino_id y monto son obligatorios.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_finite($monto) || $monto <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'El monto debe ser mayor que cero.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

$stmt = $conn->prepare('SELECT name, saldo_paquete FROM nino WHERE id = ?');
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible preparar la consulta del paciente.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $conn->close();
    exit;
}

$stmt->bind_param('i', $ninoId);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible ejecutar la consulta del paciente.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->bind_result($pacienteNombre, $saldoActual);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Paciente no encontrado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();

$saldoActual = (float) $saldoActual;
$conn->begin_transaction();

try {
    if (!ajustarSaldoPaciente($conn, $ninoId, $monto)) {
        throw new RuntimeException('No fue posible actualizar el saldo del paciente.');
    }

    $nuevoSaldo = $saldoActual + $monto;

    $descripcion = sprintf(
        'Se agregaron %s al saldo del paciente %s. Nuevo saldo: %s.',
        number_format($monto, 2),
        $pacienteNombre,
        number_format($nuevoSaldo, 2)
    );

    if ($comentario !== '') {
        $descripcion .= ' Comentario: ' . $comentario;
    }

    registrarLog(
        $conn,
        (int) $_SESSION['id'],
        'pacientes',
        'agregar_saldo',
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
