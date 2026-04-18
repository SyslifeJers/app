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

$conn = conectar();
$conn->set_charset('utf8mb4');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$usuarioId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
$ROL_PRACTICANTE = 6;
$ROL_VENTAS = 1;
$ROL_ADMIN = 3;

if ($usuarioId <= 0) {
    respuesta(401, ['success' => false, 'message' => 'No autenticado.']);
}

if ($metodo === 'POST' && $rolUsuario === $ROL_PRACTICANTE) {
    respuesta(403, ['success' => false, 'message' => 'No tienes permisos para crear reuniones.']);
}

function obtenerIdReunion(): int
{
    $idRaw = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
    if ($idRaw === '' || !ctype_digit($idRaw)) {
        respuesta(400, ['success' => false, 'message' => 'El parámetro id es obligatorio y debe ser un número entero positivo.']);
    }
    $id = (int) $idRaw;
    if ($id <= 0) {
        respuesta(400, ['success' => false, 'message' => 'El parámetro id debe ser mayor que cero.']);
    }
    return $id;
}

function exigirRolGestion(int $rolUsuario, int $rolVentas, int $rolAdmin): void
{
    if ($rolUsuario !== $rolVentas && $rolUsuario !== $rolAdmin) {
        respuesta(403, ['success' => false, 'message' => 'No tienes permisos para modificar reuniones.']);
    }
}

function leerJson(): array
{
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        respuesta(400, ['success' => false, 'message' => 'Datos inválidos.']);
    }
    return $payload;
}

function normalizarFechaReunion(string $valor, string $campo): DateTime
{
    $valor = trim($valor);
    if ($valor === '') {
        respuesta(422, ['success' => false, 'message' => "{$campo} es obligatorio."]);
        throw new RuntimeException('Unreachable');
    }

    $formatos = ['Y-m-d\\TH:i', 'Y-m-d H:i:s', DateTime::ATOM];
    foreach ($formatos as $formato) {
        $fecha = DateTime::createFromFormat($formato, $valor);
        if ($fecha instanceof DateTime) {
            return $fecha;
        }
    }

    respuesta(422, ['success' => false, 'message' => "{$campo} no tiene un formato válido."]);
    throw new RuntimeException('Unreachable');
}

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
        where ri.fin >= NOW()
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

if ($metodo === 'PUT') {
    exigirRolGestion($rolUsuario, $ROL_VENTAS, $ROL_ADMIN);
    $id = obtenerIdReunion();
    $payload = leerJson();

    $inicioDate = normalizarFechaReunion((string) ($payload['inicio'] ?? ''), 'inicio');
    $finDate = normalizarFechaReunion((string) ($payload['fin'] ?? ''), 'fin');

    if ($finDate <= $inicioDate) {
        respuesta(422, ['success' => false, 'message' => 'La fecha de fin debe ser mayor a la de inicio.']);
    }

    $inicioSql = $inicioDate->format('Y-m-d H:i:s');
    $finSql = $finDate->format('Y-m-d H:i:s');

    $stmtCheck = $conn->prepare('SELECT id FROM ReunionInterna WHERE id = ?');
    if (!$stmtCheck) {
        respuesta(500, ['success' => false, 'message' => 'No fue posible validar la reunión.']);
    }
    $stmtCheck->bind_param('i', $id);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();
    $exists = $result ? $result->fetch_assoc() : null;
    $stmtCheck->close();
    if (!$exists) {
        respuesta(404, ['success' => false, 'message' => 'No se encontró la reunión.']);
    }

    $stmt = $conn->prepare('UPDATE ReunionInterna SET inicio = ?, fin = ? WHERE id = ?');
    if (!$stmt) {
        respuesta(500, ['success' => false, 'message' => 'No fue posible preparar la actualización.']);
    }
    $stmt->bind_param('ssi', $inicioSql, $finSql, $id);
    if (!$stmt->execute()) {
        $stmt->close();
        respuesta(500, ['success' => false, 'message' => 'No fue posible actualizar la reunión.']);
    }
    $stmt->close();

    respuesta(200, ['success' => true, 'message' => 'Reunión reprogramada correctamente.']);
}

if ($metodo === 'DELETE') {
    exigirRolGestion($rolUsuario, $ROL_VENTAS, $ROL_ADMIN);
    $id = obtenerIdReunion();

    $stmtCheck = $conn->prepare('SELECT id FROM ReunionInterna WHERE id = ?');
    if (!$stmtCheck) {
        respuesta(500, ['success' => false, 'message' => 'No fue posible validar la reunión.']);
    }
    $stmtCheck->bind_param('i', $id);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();
    $exists = $result ? $result->fetch_assoc() : null;
    $stmtCheck->close();
    if (!$exists) {
        respuesta(404, ['success' => false, 'message' => 'No se encontró la reunión.']);
    }

    $conn->begin_transaction();
    try {
        $stmtRel = $conn->prepare('DELETE FROM ReunionInternaPsicologo WHERE reunion_id = ?');
        if (!$stmtRel) {
            throw new RuntimeException('No fue posible preparar la cancelación.');
        }
        $stmtRel->bind_param('i', $id);
        if (!$stmtRel->execute()) {
            $stmtRel->close();
            throw new RuntimeException('No fue posible cancelar la reunión.');
        }
        $stmtRel->close();

        $stmtDel = $conn->prepare('DELETE FROM ReunionInterna WHERE id = ?');
        if (!$stmtDel) {
            throw new RuntimeException('No fue posible preparar la cancelación.');
        }
        $stmtDel->bind_param('i', $id);
        if (!$stmtDel->execute()) {
            $stmtDel->close();
            throw new RuntimeException('No fue posible cancelar la reunión.');
        }
        $stmtDel->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        respuesta(500, ['success' => false, 'message' => $e->getMessage()]);
    }

    respuesta(200, ['success' => true, 'message' => 'Reunión cancelada correctamente.']);
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
    $creadoPor = $usuarioId;

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
