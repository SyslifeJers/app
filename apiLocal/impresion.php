<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function ensurePrintSchema(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS cola_impresion_ticket (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(100) NOT NULL,
        payload LONGTEXT NOT NULL,
        estado ENUM('pendiente','procesando','procesado','error') NOT NULL DEFAULT 'pendiente',
        intentos INT UNSIGNED NOT NULL DEFAULT 0,
        mensaje_error TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->query($sql);
}

function createPrintRequest(mysqli $conn): void
{
    $data = getJsonInput();

    $tipo = isset($data['tipo']) ? trim((string) $data['tipo']) : '';
    $payload = $data['payload'] ?? null;

    if ($tipo === '' || $payload === null) {
        jsonResponse(400, [
            'status' => 'error',
            'message' => 'Los campos tipo y payload son obligatorios.',
        ]);
    }

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        jsonResponse(400, [
            'status' => 'error',
            'message' => 'No fue posible serializar el payload proporcionado.',
        ]);
    }

    $stmt = $conn->prepare('INSERT INTO cola_impresion_ticket (tipo, payload, estado) VALUES (?, ?, ?)');
    $estado = 'pendiente';
    $stmt->bind_param('sss', $tipo, $payloadJson, $estado);
    $stmt->execute();

    $id = (int) $conn->insert_id;

    jsonResponse(201, [
        'status' => 'success',
        'data' => [
            'id' => $id,
            'tipo' => $tipo,
        ],
    ]);
}

function getPrintQueue(mysqli $conn): void
{
    $estado = isset($_GET['estado']) ? normalisePrintStatus((string) $_GET['estado']) : null;
    $desde = isset($_GET['desde_fecha']) ? trim((string) $_GET['desde_fecha']) : null;
    $limit = isset($_GET['limite']) ? (int) $_GET['limite'] : 100;

    $conditions = [];
    $types = '';
    $params = [];

    if ($estado !== null) {
        $conditions[] = 'estado = ?';
        $types .= 's';
        $params[] = $estado;
    }

    if ($desde !== null && $desde !== '') {
        $conditions[] = 'created_at >= ?';
        $types .= 's';
        $params[] = $desde;
    }

    $sql = 'SELECT id, tipo, payload, estado, intentos, mensaje_error, created_at, updated_at FROM cola_impresion_ticket';
    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY created_at ASC';
    if ($limit > 0) {
        $sql .= ' LIMIT ?';
        $types .= 'i';
        $params[] = $limit;
    }

    $stmt = $conn->prepare($sql);
    bindStatementParams($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $payload = json_decode($row['payload'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $payload = $row['payload'];
        }

        $jobs[] = [
            'id' => (int) $row['id'],
            'tipo' => $row['tipo'],
            'payload' => $payload,
            'estado' => $row['estado'],
            'intentos' => (int) $row['intentos'],
            'mensaje_error' => $row['mensaje_error'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    jsonResponse(200, [
        'status' => 'success',
        'data' => $jobs,
    ]);
}

function updatePrintJob(mysqli $conn, int $jobId): void
{
    if ($jobId <= 0) {
        jsonResponse(400, [
            'status' => 'error',
            'message' => 'Identificador inválido.',
        ]);
    }

    $data = getJsonInput();
    if (!isset($data['estado'])) {
        jsonResponse(400, [
            'status' => 'error',
            'message' => 'El campo estado es obligatorio.',
        ]);
    }

    $estado = normalisePrintStatus((string) $data['estado']);
    $mensaje = isset($data['mensaje_error']) ? trim((string) $data['mensaje_error']) : null;
    if ($mensaje === '') {
        $mensaje = null;
    }

    $stmt = $conn->prepare('SELECT estado, intentos FROM cola_impresion_ticket WHERE id = ?');
    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();

    if ($current === null) {
        jsonResponse(404, [
            'status' => 'error',
            'message' => 'Trabajo no encontrado.',
        ]);
    }

    $intentos = (int) $current['intentos'];
    if ($estado === 'error') {
        $intentos++;
    }

    $update = $conn->prepare('UPDATE cola_impresion_ticket SET estado = ?, mensaje_error = ?, intentos = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $update->bind_param('ssii', $estado, $mensaje, $intentos, $jobId);
    $update->execute();

    jsonResponse(200, [
        'status' => 'success',
        'data' => [
            'id' => $jobId,
            'estado' => $estado,
            'intentos' => $intentos,
        ],
    ]);
}

