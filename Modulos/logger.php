<?php
require_once __DIR__ . '/../conexion.php';

/**
 * Registra un evento dentro de la tabla de logs del sistema.
 *
 * @param mysqli|null $conn Conexión activa a la base de datos. Si es null se abre una nueva.
 * @param int|null    $usuarioId Identificador del usuario que ejecuta la acción.
 * @param string      $modulo Módulo donde ocurrió el evento (por ejemplo: citas, usuarios).
 * @param string      $accion Acción ejecutada (por ejemplo: crear, actualizar, cancelar).
 * @param string      $descripcion Descripción legible de lo que sucedió.
 * @param string|null $entidad Nombre de la entidad afectada (por ejemplo: Cita, Usuario).
 * @param string|null $referencia Identificador de la entidad afectada.
 *
 * @return void
 */
function registrarLog(?mysqli $conn, ?int $usuarioId, string $modulo, string $accion, string $descripcion, ?string $entidad = null, ?string $referencia = null): void
{
    $conexionPropia = false;

    if (!($conn instanceof mysqli)) {
        $conn = conectar();
        $conexionPropia = true;
    }

    if (!($conn instanceof mysqli)) {
        error_log('registrarLog: no se pudo obtener la conexión a la base de datos.');
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $modulo = mb_substr($modulo, 0, 100, 'UTF-8');
    $accion = mb_substr($accion, 0, 100, 'UTF-8');

    $query = 'INSERT INTO LogSistema (usuario_id, modulo, accion, descripcion, entidad, referencia, ip) VALUES (?, ?, ?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        error_log('registrarLog: error al preparar la consulta: ' . $conn->error);
        if ($conexionPropia) {
            $conn->close();
        }
        return;
    }

    $stmt->bind_param(
        'issssss',
        $usuarioId,
        $modulo,
        $accion,
        $descripcion,
        $entidad,
        $referencia,
        $ip
    );

    if (!$stmt->execute()) {
        error_log('registrarLog: error al ejecutar la consulta: ' . $stmt->error);
    }

    $stmt->close();

    if ($conexionPropia) {
        $conn->close();
    }
}
