<?php

declare(strict_types=1);

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/impresion/tickets.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($conn) || !($conn instanceof mysqli)) {
    jsonResponse(500, [
        'status' => 'error',
        'message' => 'No se pudo establecer la conexión con la base de datos.',
    ]);
}

ensureOfflineSchema($conn);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$relative = substr($uri, strlen($base));
if ($relative === false) {
    $relative = $uri;
}
$path = ltrim($relative, '/');

try {
    switch (true) {
        case $path === '' || $path === '/':
            jsonResponse(200, [
                'status' => 'ok',
                'message' => 'CerenePrint API',
            ]);

        case $path === 'impresion/tickets' && $method === 'GET':
            handleListPrintTickets($conn);
            break;

        case $path === 'impresion/tickets' && $method === 'POST':
            handleCreatePrintTicket($conn);
            break;

        case $path === 'sync/push' && $method === 'POST':
            handleSyncPush($conn);
            break;

        case $path === 'sync/pull' && $method === 'GET':
            handleSyncPull($conn);
            break;

        case $path === 'offline/citas' && $method === 'GET':
            handleOfflineAppointments($conn);
            break;

        case $path === 'offline/pacientes' && $method === 'GET':
            handleOfflinePatients($conn);
            break;

        default:
            jsonResponse(404, [
                'status' => 'error',
                'message' => 'Recurso no encontrado',
                'path' => $path,
            ]);
    }
} catch (mysqli_sql_exception $exception) {
    jsonResponse(500, [
        'status' => 'error',
        'message' => 'Error al procesar la petición.',
        'details' => $exception->getMessage(),
    ]);
}

function handleSyncPush(mysqli $conn): void
{
    $data = getJsonInput();
    $results = [];

    if (isset($data['citas']) && is_array($data['citas'])) {
        foreach ($data['citas'] as $cita) {
            $results[] = processAppointmentPush($conn, $cita);
        }
    }

    if (isset($data['pagos']) && is_array($data['pagos'])) {
        foreach ($data['pagos'] as $pago) {
            $results[] = processPaymentPush($conn, $pago);
        }
    }

    jsonResponse(200, [
        'status' => 'success',
        'data' => $results,
    ]);
}

function processAppointmentPush(mysqli $conn, array $payload): array
{
    foreach (['id_local', 'version', 'updated_at', 'datos'] as $field) {
        if (!array_key_exists($field, $payload)) {
            jsonResponse(400, [
                'status' => 'error',
                'message' => "Falta el campo {$field} en una cita.",
            ]);
        }
    }

    $idLocal = (int) $payload['id_local'];
    $version = max(1, (int) $payload['version']);
    $updatedAt = parseIsoDate((string) $payload['updated_at'], 'updated_at');
    $datos = $payload['datos'];

    foreach (['paciente_id', 'fecha', 'hora', 'estado'] as $field) {
        if (!array_key_exists($field, $datos)) {
            jsonResponse(400, [
                'status' => 'error',
                'message' => "Falta el campo {$field} en datos de la cita.",
            ]);
        }
    }

    $pacienteId = (int) $datos['paciente_id'];
    $fecha = DateTimeImmutable::createFromFormat('Y-m-d', (string) $datos['fecha']);
    $hora = DateTimeImmutable::createFromFormat('H:i', (string) $datos['hora']);

    if ($fecha === false || $hora === false) {
        jsonResponse(400, [
            'status' => 'error',
            'message' => 'La fecha u hora de la cita no tiene el formato correcto.',
        ]);
    }

    $estado = trim((string) $datos['estado']);
    if ($estado === '') {
        jsonResponse(400, [
            'status' => 'error',
            'message' => 'El estado de la cita no puede estar vacío.',
        ]);
    }

    $conn->begin_transaction();

    try {
        $idServidor = isset($payload['id_servidor']) ? (int) $payload['id_servidor'] : null;
        $fechaStr = $fecha->format('Y-m-d');
        $horaStr = $hora->format('H:i:s');
        $updatedStr = $updatedAt->format('Y-m-d H:i:s');

        if ($idServidor === null || $idServidor <= 0) {
            $stmt = $conn->prepare('INSERT INTO offline_citas (paciente_id, fecha, hora, estado, version, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isssis', $pacienteId, $fechaStr, $horaStr, $estado, $version, $updatedStr);
            $stmt->execute();
            $idServidor = (int) $conn->insert_id;
        } else {
            $stmt = $conn->prepare('SELECT version FROM offline_citas WHERE id_servidor = ?');
            $stmt->bind_param('i', $idServidor);
            $stmt->execute();
            $current = $stmt->get_result()->fetch_assoc();

            if ($current === null) {
                $conn->rollback();
                jsonResponse(404, [
                    'status' => 'error',
                    'message' => 'La cita especificada no existe en el servidor.',
                ]);
            }

            $currentVersion = (int) $current['version'];
            if ($version < $currentVersion) {
                $conn->rollback();
                return [
                    'id_local' => $idLocal,
                    'id_servidor' => $idServidor,
                    'version' => $currentVersion,
                    'sync_status' => 'ignored',
                ];
            }

            $stmt = $conn->prepare('UPDATE offline_citas SET paciente_id = ?, fecha = ?, hora = ?, estado = ?, version = ?, updated_at = ? WHERE id_servidor = ?');
            $stmt->bind_param('isssisi', $pacienteId, $fechaStr, $horaStr, $estado, $version, $updatedStr, $idServidor);
            $stmt->execute();
        }

        $conn->commit();

        return [
            'id_local' => $idLocal,
            'id_servidor' => $idServidor,
            'version' => $version,
            'sync_status' => 'updated',
        ];
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}

function processPaymentPush(mysqli $conn, array $payload): array
{
    foreach (['id_local', 'version', 'updated_at', 'datos'] as $field) {
        if (!array_key_exists($field, $payload)) {
            jsonResponse(400, [
                'status' => 'error',
                'message' => "Falta el campo {$field} en un pago.",
            ]);
        }
    }

    $idLocal = (int) $payload['id_local'];
    $version = max(1, (int) $payload['version']);
    $updatedAt = parseIsoDate((string) $payload['updated_at'], 'updated_at');
    $datos = $payload['datos'];

    foreach (['cita_id', 'monto', 'metodo'] as $field) {
        if (!array_key_exists($field, $datos)) {
            jsonResponse(400, [
                'status' => 'error',
                'message' => "Falta el campo {$field} en datos del pago.",
            ]);
        }
    }

    $citaId = (int) $datos['cita_id'];
    $monto = (float) $datos['monto'];
    $metodo = trim((string) $datos['metodo']);

    if ($metodo === '') {
        jsonResponse(400, [
            'status' => 'error',
            'message' => 'El método de pago no puede estar vacío.',
        ]);
    }

    $conn->begin_transaction();

    try {
        $idServidor = isset($payload['id_servidor']) ? (int) $payload['id_servidor'] : null;
        $updatedStr = $updatedAt->format('Y-m-d H:i:s');

        if ($idServidor === null || $idServidor <= 0) {
            $stmt = $conn->prepare('INSERT INTO offline_pagos (cita_id, monto, metodo, version, updated_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('idsis', $citaId, $monto, $metodo, $version, $updatedStr);
            $stmt->execute();
            $idServidor = (int) $conn->insert_id;
        } else {
            $stmt = $conn->prepare('SELECT version FROM offline_pagos WHERE id_servidor = ?');
            $stmt->bind_param('i', $idServidor);
            $stmt->execute();
            $current = $stmt->get_result()->fetch_assoc();

            if ($current === null) {
                $conn->rollback();
                jsonResponse(404, [
                    'status' => 'error',
                    'message' => 'El pago especificado no existe en el servidor.',
                ]);
            }

            $currentVersion = (int) $current['version'];
            if ($version < $currentVersion) {
                $conn->rollback();
                return [
                    'id_local' => $idLocal,
                    'id_servidor' => $idServidor,
                    'version' => $currentVersion,
                    'sync_status' => 'ignored',
                ];
            }

            $stmt = $conn->prepare('UPDATE offline_pagos SET cita_id = ?, monto = ?, metodo = ?, version = ?, updated_at = ? WHERE id_servidor = ?');
            $stmt->bind_param('idsisi', $citaId, $monto, $metodo, $version, $updatedStr, $idServidor);
            $stmt->execute();
        }

        $conn->commit();

        return [
            'id_local' => $idLocal,
            'id_servidor' => $idServidor,
            'version' => $version,
            'sync_status' => 'updated',
        ];
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}

function handleSyncPull(mysqli $conn): void
{
    $lastSync = isset($_GET['last_sync']) ? trim((string) $_GET['last_sync']) : null;

    $citasSql = 'SELECT id_servidor, paciente_id, fecha, hora, estado, version, updated_at FROM offline_citas';
    $pagosSql = 'SELECT id_servidor, cita_id, monto, metodo, version, updated_at FROM offline_pagos';

    $types = '';
    $params = [];

    if ($lastSync !== null && $lastSync !== '') {
        $citasSql .= ' WHERE updated_at > ?';
        $pagosSql .= ' WHERE updated_at > ?';
        $types = 's';
        $params[] = $lastSync;
    }

    $stmt = $conn->prepare($citasSql);
    bindStatementParams($stmt, $types, $params);
    $stmt->execute();
    $citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare($pagosSql);
    bindStatementParams($stmt, $types, $params);
    $stmt->execute();
    $pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    jsonResponse(200, [
        'status' => 'success',
        'data' => [
            'citas' => $citas,
            'pagos' => $pagos,
            'last_sync' => $lastSync ?? (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
        ],
    ]);
}

function handleOfflineAppointments(mysqli $conn): void
{
    $result = $conn->query('SELECT id_servidor, paciente_id, fecha, hora, estado, version, updated_at FROM offline_citas ORDER BY updated_at DESC');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    jsonResponse(200, [
        'status' => 'success',
        'data' => $rows,
    ]);
}

function handleOfflinePatients(mysqli $conn): void
{
    $result = $conn->query('SELECT id, nombre, apellido_paterno, apellido_materno, fecha_nacimiento, created_at, updated_at FROM pacientes');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    jsonResponse(200, [
        'status' => 'success',
        'data' => $rows,
    ]);
}

function ensureOfflineSchema(mysqli $conn): void
{
    $queries = [
        "CREATE TABLE IF NOT EXISTS offline_citas (
            id_servidor BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            paciente_id BIGINT UNSIGNED NOT NULL,
            fecha DATE NOT NULL,
            hora TIME NOT NULL,
            estado VARCHAR(50) NOT NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS offline_pagos (
            id_servidor BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cita_id BIGINT UNSIGNED NOT NULL,
            monto DECIMAL(12,2) NOT NULL,
            metodo VARCHAR(100) NOT NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS impresion_tickets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_id BIGINT UNSIGNED NOT NULL,
            estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($queries as $sql) {
        $conn->query($sql);
    }
}
