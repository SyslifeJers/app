<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

function respuesta(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tablaExiste(mysqli $conn, string $tabla): bool
{
    $stmt = $conn->prepare('SHOW TABLES LIKE ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $tabla);
    $stmt->execute();
    $stmt->store_result();
    $existe = $stmt->num_rows > 0;
    $stmt->close();

    return $existe;
}

$conn = conectar();
$conn->set_charset('utf8mb4');

if (!tablaExiste($conn, 'ReunionInterna') || !tablaExiste($conn, 'ReunionInternaPsicologo')) {
    respuesta(500, [
        'success' => false,
        'message' => 'No existen las tablas de reuniones internas. Ejecuta el script sql/reuniones_internas.sql.'
    ]);
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    $sql = "SELECT
            ri.id,
            ri.titulo,
            ri.descripcion,
            ri.inicio,
            ri.fin,
            GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ', ') AS psicologos
        FROM ReunionInterna ri
        INNER JOIN ReunionInternaPsicologo rip ON rip.reunion_id = ri.id
        INNER JOIN Usuarios u ON u.id = rip.psicologo_id
        GROUP BY ri.id, ri.titulo, ri.descripcion, ri.inicio, ri.fin
        ORDER BY ri.inicio DESC
        LIMIT 200";

    $result = $conn->query($sql);
    if (!$result) {
        respuesta(500, ['success' => false, 'message' => 'No fue posible consultar las reuniones.']);
    }

    $reuniones = [];
    while ($fila = $result->fetch_assoc()) {
        $reuniones[] = [
            'id' => (int) $fila['id'],
            'titulo' => $fila['titulo'],
            'descripcion' => $fila['descripcion'],
            'inicio' => $fila['inicio'],
            'fin' => $fila['fin'],
            'psicologos' => $fila['psicologos'] ?? '',
        ];
    }

    respuesta(200, ['success' => true, 'data' => $reuniones]);
}

if ($metodo === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        respuesta(400, ['success' => false, 'message' => 'Datos inválidos.']);
    }

    $titulo = trim((string) ($payload['titulo'] ?? ''));
    $descripcion = trim((string) ($payload['descripcion'] ?? ''));
    $inicio = trim((string) ($payload['inicio'] ?? ''));
    $fin = trim((string) ($payload['fin'] ?? ''));
    $psicologos = $payload['psicologos'] ?? [];

    if ($titulo === '' || $inicio === '' || $fin === '') {
        respuesta(422, ['success' => false, 'message' => 'Título, inicio y fin son obligatorios.']);
    }

    if (!is_array($psicologos) || count($psicologos) === 0) {
        respuesta(422, ['success' => false, 'message' => 'Selecciona al menos una psicóloga para la reunión.']);
    }

    $idsPsicologos = [];
    foreach ($psicologos as $id) {
        $idNumero = (int) $id;
        if ($idNumero > 0) {
            $idsPsicologos[$idNumero] = $idNumero;
        }
    }

    if (count($idsPsicologos) === 0) {
        respuesta(422, ['success' => false, 'message' => 'Los participantes seleccionados no son válidos.']);
    }

    $inicioDate = DateTime::createFromFormat('Y-m-d\TH:i', $inicio) ?: DateTime::createFromFormat('Y-m-d H:i:s', $inicio);
    $finDate = DateTime::createFromFormat('Y-m-d\TH:i', $fin) ?: DateTime::createFromFormat('Y-m-d H:i:s', $fin);

    if (!$inicioDate || !$finDate) {
        respuesta(422, ['success' => false, 'message' => 'Las fechas de inicio y fin no son válidas.']);
    }

    if ($finDate <= $inicioDate) {
        respuesta(422, ['success' => false, 'message' => 'La fecha de fin debe ser mayor a la de inicio.']);
    }

    $inicioSql = $inicioDate->format('Y-m-d H:i:s');
    $finSql = $finDate->format('Y-m-d H:i:s');
    $creadoPor = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;

    $conn->begin_transaction();
    try {
        if ($creadoPor !== null && $creadoPor > 0) {
            $stmtReunion = $conn->prepare('INSERT INTO ReunionInterna (titulo, descripcion, inicio, fin, creado_por) VALUES (?, ?, ?, ?, ?)');
            $stmtReunion->bind_param('ssssi', $titulo, $descripcion, $inicioSql, $finSql, $creadoPor);
        } else {
            $stmtReunion = $conn->prepare('INSERT INTO ReunionInterna (titulo, descripcion, inicio, fin, creado_por) VALUES (?, ?, ?, ?, NULL)');
            $stmtReunion->bind_param('ssss', $titulo, $descripcion, $inicioSql, $finSql);
        }

        if (!$stmtReunion || !$stmtReunion->execute()) {
            throw new RuntimeException('No fue posible guardar la reunión.');
        }

        $reunionId = (int) $conn->insert_id;
        $stmtReunion->close();

        $stmtParticipante = $conn->prepare('INSERT INTO ReunionInternaPsicologo (reunion_id, psicologo_id) VALUES (?, ?)');
        if (!$stmtParticipante) {
            throw new RuntimeException('No fue posible guardar los participantes.');
        }

        foreach ($idsPsicologos as $idPsicologo) {
            $stmtParticipante->bind_param('ii', $reunionId, $idPsicologo);
            if (!$stmtParticipante->execute()) {
                throw new RuntimeException('No fue posible registrar a todas las psicólogas participantes.');
            }
        }

        $stmtParticipante->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        respuesta(500, ['success' => false, 'message' => $e->getMessage()]);
    }

    respuesta(201, ['success' => true, 'message' => 'Reunión guardada correctamente.']);
}

respuesta(405, ['success' => false, 'message' => 'Método no permitido.']);
