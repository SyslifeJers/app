<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

$conn = conectar();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible conectar con la base de datos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$ninoId = null;
if (isset($_GET['nino_id'])) {
    $ninoId = filter_var($_GET['nino_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

$idTutor = null;
$nombre = null;
if ($ninoId === null) {
    if (isset($_GET['idtutor'])) {
        $idTutor = filter_var($_GET['idtutor'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    }
    if (isset($_GET['name'])) {
        $nombre = trim((string) $_GET['name']);
    }

    if ($idTutor === null || $nombre === null || $nombre === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Los parámetros idtutor y name son obligatorios.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $conn->close();
        exit;
    }
}

if ($ninoId !== null) {
    $stmt = $conn->prepare('SELECT id, name, edad, activo, idtutor, Observacion, FechaIngreso, saldo_paquete FROM nino WHERE id = ?');
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['error' => 'No fue posible preparar la consulta del paciente.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $conn->close();
        exit;
    }

    $stmt->bind_param('i', $ninoId);
} else {
    $stmt = $conn->prepare('SELECT id, name, edad, activo, idtutor, Observacion, FechaIngreso, saldo_paquete FROM nino WHERE idtutor = ? AND name = ?');
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['error' => 'No fue posible preparar la consulta del paciente.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $conn->close();
        exit;
    }

    $stmt->bind_param('is', $idTutor, $nombre);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible ejecutar la consulta del paciente.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt->close();
    $conn->close();
    exit;
}

$resultado = $stmt->get_result();
$paciente = $resultado->fetch_assoc();

$stmt->close();
$conn->close();

echo json_encode($paciente ?: null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
