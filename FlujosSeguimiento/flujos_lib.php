<?php

declare(strict_types=1);

date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../conexion.php';

function flujos_json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function flujos_require_session(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $usuarioId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
    if ($usuarioId <= 0) {
        flujos_json_response(401, ['success' => false, 'message' => 'No autenticado.']);
    }

    if (empty($_SESSION['flujos_csrf'])) {
        $_SESSION['flujos_csrf'] = bin2hex(random_bytes(16));
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        flujos_require_csrf();
    }

    return [
        'id' => $usuarioId,
        'rol' => isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0,
    ];
}

function flujos_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['flujos_csrf'])) {
        $_SESSION['flujos_csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['flujos_csrf'];
}

function flujos_require_csrf(): void
{
    $expected = (string) ($_SESSION['flujos_csrf'] ?? '');
    $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        flujos_json_response(419, ['success' => false, 'message' => 'Token de seguridad invalido.']);
    }
}

function flujos_require_admin_role(array $session): void
{
    if (!in_array((int) ($session['rol'] ?? 0), [1, 3, 5], true)) {
        flujos_json_response(403, ['success' => false, 'message' => 'No autorizado para administrar flujos.']);
    }
}

function flujos_input(): array
{
    $raw = (string) file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST;
}

function flujos_normalize_enum(string $value, array $allowed, string $default): string
{
    $value = strtolower(trim($value));
    return in_array($value, $allowed, true) ? $value : $default;
}

function flujos_historial(mysqli $conn, ?int $asignacionId, ?int $tareaId, string $accion, string $descripcion, int $usuarioId): void
{
    $stmt = $conn->prepare('INSERT INTO exp_flujo_historial (id_asignacion, id_tarea_asignada, accion, descripcion, usuario_id) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iissi', $asignacionId, $tareaId, $accion, $descripcion, $usuarioId);
    $stmt->execute();
    $stmt->close();
}

function flujos_actualizar_avance(mysqli $conn, int $asignacionId): void
{
    $stmt = $conn->prepare("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN estatus = 'completada' THEN 1 ELSE 0 END) AS completadas,
        SUM(CASE WHEN estatus = 'negada' AND motivo_negacion IS NOT NULL AND motivo_negacion <> '' THEN 1 ELSE 0 END) AS negadas
        FROM exp_flujo_tareas_asignadas
        WHERE id_asignacion = ? AND obligatoria = 1 AND activo = 1 AND estatus <> 'cancelada'");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $asignacionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $total = (int) ($row['total'] ?? 0);
    $completadas = (int) ($row['completadas'] ?? 0);
    $negadas = (int) ($row['negadas'] ?? 0);
    $avance = $total > 0 ? round(($completadas / $total) * 100, 2) : 0.0;
    $estatus = 'activo';

    if ($total > 0 && $completadas === $total) {
        $estatus = 'completado';
    } elseif ($total > 0 && ($completadas + $negadas) === $total) {
        $estatus = 'completado_excepciones';
    }

    $fechaCierre = in_array($estatus, ['completado', 'completado_excepciones'], true) ? date('Y-m-d H:i:s') : null;
    if ($fechaCierre !== null) {
        $stmtUpdate = $conn->prepare("UPDATE exp_flujo_asignaciones SET avance = ?, estatus = CASE WHEN estatus = 'cancelado' THEN estatus ELSE ? END, fecha_cierre = COALESCE(fecha_cierre, ?) WHERE id_asignacion = ?");
        if ($stmtUpdate) {
            $stmtUpdate->bind_param('dssi', $avance, $estatus, $fechaCierre, $asignacionId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
        return;
    }

    $stmtUpdate = $conn->prepare("UPDATE exp_flujo_asignaciones SET avance = ?, estatus = CASE WHEN estatus = 'cancelado' THEN estatus ELSE ? END WHERE id_asignacion = ?");
    if ($stmtUpdate) {
        $stmtUpdate->bind_param('dsi', $avance, $estatus, $asignacionId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }
}

function flujos_asignar(mysqli $conn, int $flujoId, int $ninoId, int $psicologoId, string $observaciones, int $usuarioId): int
{
    $stmtFlujo = $conn->prepare('SELECT id_flujo FROM exp_flujos WHERE id_flujo = ? AND activo = 1 LIMIT 1');
    if (!$stmtFlujo) {
        throw new RuntimeException('No fue posible validar el flujo.');
    }
    $stmtFlujo->bind_param('i', $flujoId);
    $stmtFlujo->execute();
    $flujoExiste = $stmtFlujo->get_result()->fetch_assoc();
    $stmtFlujo->close();
    if (!$flujoExiste) {
        throw new RuntimeException('Flujo no encontrado o inactivo.');
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('INSERT INTO exp_flujo_asignaciones (id_flujo, id_nino, observaciones_iniciales, usuario_asignacion) VALUES (?, ?, ?, ?)');
        if (!$stmt) {
            throw new RuntimeException('No fue posible asignar el flujo.');
        }
        $stmt->bind_param('iisi', $flujoId, $ninoId, $observaciones, $usuarioId);
        if (!$stmt->execute()) {
            throw new RuntimeException('No fue posible guardar la asignacion.');
        }
        $asignacionId = (int) $conn->insert_id;
        $stmt->close();

        $stmtTareas = $conn->prepare('SELECT id_tarea, nombre, descripcion, orden, tipo_tarea, responsable, obligatoria, dias_vencimiento, requiere_documento FROM exp_flujo_tareas WHERE id_flujo = ? AND activo = 1 ORDER BY orden ASC, id_tarea ASC');
        if (!$stmtTareas) {
            throw new RuntimeException('No fue posible obtener tareas del flujo.');
        }
        $stmtTareas->bind_param('i', $flujoId);
        $stmtTareas->execute();
        $result = $stmtTareas->get_result();

        $stmtInsert = $conn->prepare('INSERT INTO exp_flujo_tareas_asignadas (id_asignacion, id_tarea_plantilla, id_nino, nombre, descripcion, orden, tipo_tarea, responsable, responsable_usuario_id, obligatoria, requiere_documento, fecha_limite) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmtInsert) {
            throw new RuntimeException('No fue posible generar tareas.');
        }

        while ($task = $result->fetch_assoc()) {
            $dias = $task['dias_vencimiento'] !== null ? (int) $task['dias_vencimiento'] : null;
            $fechaLimite = $dias !== null ? date('Y-m-d', strtotime('+' . max(0, $dias) . ' days')) : null;
            $responsableUsuario = $task['responsable'] === 'psicologa' && $psicologoId > 0 ? $psicologoId : null;
            $taskId = (int) $task['id_tarea'];
            $nombre = (string) $task['nombre'];
            $descripcion = (string) ($task['descripcion'] ?? '');
            $orden = (int) $task['orden'];
            $tipoTarea = (string) $task['tipo_tarea'];
            $responsable = (string) $task['responsable'];
            $obligatoria = (int) $task['obligatoria'];
            $requiereDocumento = (int) $task['requiere_documento'];
            $stmtInsert->bind_param(
                'iiississiiis',
                $asignacionId,
                $taskId,
                $ninoId,
                $nombre,
                $descripcion,
                $orden,
                $tipoTarea,
                $responsable,
                $responsableUsuario,
                $obligatoria,
                $requiereDocumento,
                $fechaLimite
            );
            if (!$stmtInsert->execute()) {
                throw new RuntimeException('No fue posible generar una tarea del flujo.');
            }
        }

        $stmtInsert->close();
        $stmtTareas->close();
        flujos_historial($conn, $asignacionId, null, 'asignar_flujo', 'Flujo asignado y tareas generadas.', $usuarioId);
        flujos_actualizar_avance($conn, $asignacionId);
        $conn->commit();
        return $asignacionId;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
