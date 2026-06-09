<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../conexion.php';

function jsonResponse($statusCode, $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonInput()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        jsonResponse(400, ['ok' => false, 'error' => 'No se recibio contenido JSON.']);
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(400, ['ok' => false, 'error' => 'JSON invalido: ' . json_last_error_msg()]);
    }
    if (!is_array($data)) {
        jsonResponse(400, ['ok' => false, 'error' => 'El cuerpo debe ser un objeto JSON.']);
    }

    return $data;
}

function normalizarFecha($valor, $campo)
{
    if ($valor === null || trim($valor) === '') {
        return null;
    }

    try {
        $fecha = new DateTimeImmutable($valor);
        return $fecha->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        jsonResponse(400, ['ok' => false, 'error' => "El campo {$campo} no tiene una fecha valida."]);
    }
}

function obtenerEntero($valor, $campo, $requerido)
{
    if ($valor === null || $valor === '') {
        if ($requerido) {
            jsonResponse(400, ['ok' => false, 'error' => "Falta el campo obligatorio {$campo}."]);
        }
        return null;
    }

    $entero = filter_var($valor, FILTER_VALIDATE_INT);
    if ($entero === false) {
        jsonResponse(400, ['ok' => false, 'error' => "El campo {$campo} debe ser entero."]);
    }

    return (int) $entero;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['ok' => false, 'error' => 'Metodo no permitido. Use POST.']);
}

$payload = getJsonInput();

if (!isset($payload['eventos']) || !is_array($payload['eventos'])) {
    jsonResponse(400, ['ok' => false, 'error' => 'El campo eventos debe ser una lista.']);
}

$fechaSincronizacion = normalizarFecha(isset($payload['fechaSincronizacion']) ? (string) $payload['fechaSincronizacion'] : null, 'fechaSincronizacion');
$equipo = isset($payload['equipo']) ? trim((string) $payload['equipo']) : null;
$sucursal = isset($payload['sucursal']) ? trim((string) $payload['sucursal']) : null;

$conn = conectar();

$sql = 'INSERT IGNORE INTO checador_eventos
    (serial_no, employee_no, nombre, fecha_hora, door_no, dispositivo, sucursal, fecha_sincronizacion)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    jsonResponse(500, ['ok' => false, 'error' => 'No se pudo preparar la consulta: ' . $conn->error]);
}

$recibidos = 0;
$insertados = 0;
$duplicados = 0;
$omitidos = [];

foreach ($payload['eventos'] as $indice => $evento) {
    $recibidos++;

    if (!is_array($evento)) {
        $omitidos[] = ['indice' => $indice, 'motivo' => 'El evento no es un objeto.'];
        continue;
    }

    $serialNo = obtenerEntero(isset($evento['serialNo']) ? $evento['serialNo'] : null, 'serialNo', true);
    $empleado = isset($evento['empleado']) ? trim((string) $evento['empleado']) : '';
    $nombre = isset($evento['nombre']) ? trim((string) $evento['nombre']) : null;
    $fechaHora = normalizarFecha(isset($evento['fechaHora']) ? (string) $evento['fechaHora'] : null, 'fechaHora');
    $doorNo = obtenerEntero(isset($evento['doorNo']) ? $evento['doorNo'] : null, 'doorNo', false);

    if ($serialNo === null || $serialNo <= 0) {
        $omitidos[] = ['indice' => $indice, 'motivo' => 'serialNo invalido.'];
        continue;
    }
    if ($empleado === '') {
        $omitidos[] = ['indice' => $indice, 'serialNo' => $serialNo, 'motivo' => 'empleado vacio.'];
        continue;
    }
    if ($fechaHora === null) {
        $omitidos[] = ['indice' => $indice, 'serialNo' => $serialNo, 'motivo' => 'fechaHora vacia.'];
        continue;
    }

    $stmt->bind_param(
        'isssisss',
        $serialNo,
        $empleado,
        $nombre,
        $fechaHora,
        $doorNo,
        $equipo,
        $sucursal,
        $fechaSincronizacion
    );

    if (!$stmt->execute()) {
        $omitidos[] = ['indice' => $indice, 'serialNo' => $serialNo, 'motivo' => $stmt->error];
        continue;
    }

    if ($stmt->affected_rows === 1) {
        $insertados++;
    } else {
        $duplicados++;
    }
}

$stmt->close();
$conn->close();

jsonResponse(200, [
    'ok' => true,
    'recibidos' => $recibidos,
    'insertados' => $insertados,
    'duplicados' => $duplicados,
    'omitidos' => $omitidos,
]);
