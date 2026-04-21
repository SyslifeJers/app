<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/conflictos_agenda.php';
require_once __DIR__ . '/../Modulos/logger.php';

function responderConversion(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function leerPayloadConversion(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        responderConversion(400, ['success' => false, 'message' => 'El cuerpo de la petición es obligatorio.']);
        throw new RuntimeException('Payload vacío.');
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        responderConversion(400, ['success' => false, 'message' => 'El cuerpo de la petición no es válido.']);
        throw new RuntimeException('Payload inválido.');
    }

    return $data;
}

function normalizarProgramadoConversion(string $valor): string
{
    $valor = trim($valor);
    if ($valor === '') {
        responderConversion(422, ['success' => false, 'message' => 'La fecha y hora son obligatorias.']);
        throw new RuntimeException('Fecha vacía.');
    }

    $formatos = ['Y-m-d H:i:s', DateTime::ATOM, 'Y-m-d\TH:i', 'Y-m-d\TH:i:s'];
    foreach ($formatos as $formato) {
        $fecha = DateTime::createFromFormat($formato, $valor);
        if ($fecha instanceof DateTime) {
            return $fecha->format('Y-m-d H:i:s');
        }
    }

    try {
        $fallback = new DateTime($valor);
        return $fallback->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        responderConversion(422, ['success' => false, 'message' => 'La fecha y hora no tienen un formato válido.']);
        throw new RuntimeException('Fecha inválida.');
    }
}

if (!isset($_SESSION['id'])) {
    responderConversion(401, ['success' => false, 'message' => 'No autenticado.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    responderConversion(405, ['success' => false, 'message' => 'Método no permitido.']);
}

$payload = leerPayloadConversion();
$reservacionId = isset($payload['reservacion_id']) ? (int) $payload['reservacion_id'] : 0;
$programado = normalizarProgramadoConversion((string) ($payload['programado'] ?? ''));
$forzar = !empty($payload['forzar']);

if ($reservacionId <= 0) {
    responderConversion(422, ['success' => false, 'message' => 'La reservación continua es obligatoria.']);
}

$conn = conectar();
if (!($conn instanceof mysqli) || $conn->connect_errno) {
    responderConversion(500, ['success' => false, 'message' => 'No fue posible conectar con la base de datos.']);
}
$conn->set_charset('utf8mb4');
$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        'SELECT rc.id,
                rc.paciente_id,
                rc.psicologo_id,
                rc.tipo,
                rc.tiempo,
                rc.activo,
                n.name AS paciente,
                u.name AS psicologo
         FROM ReservacionContinua rc
         INNER JOIN nino n ON n.id = rc.paciente_id
         INNER JOIN Usuarios u ON u.id = rc.psicologo_id
         WHERE rc.id = ?
         FOR UPDATE'
    );
    if ($stmt === false) {
        throw new RuntimeException('No fue posible consultar la reservación continua.');
    }
    $stmt->bind_param('i', $reservacionId);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $reservacion = $resultado ? $resultado->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($reservacion)) {
        throw new RuntimeException('La reservación continua seleccionada no existe.');
    }

    if ((int) ($reservacion['activo'] ?? 0) !== 1) {
        throw new RuntimeException('La reservación continua no está activa.');
    }

    $pacienteId = (int) $reservacion['paciente_id'];
    $psicologoId = (int) $reservacion['psicologo_id'];
    $tiempo = max(1, (int) ($reservacion['tiempo'] ?? 60));
    $tipo = (string) ($reservacion['tipo'] ?? 'Cita');

    $check = $conn->prepare('SELECT COUNT(*) FROM Cita WHERE IdNino = ? AND Programado = ?');
    if ($check === false) {
        throw new RuntimeException('No fue posible validar si el paciente ya tiene cita en ese horario.');
    }
    $check->bind_param('is', $pacienteId, $programado);
    $check->execute();
    $check->bind_result($duplicados);
    $check->fetch();
    $check->close();

    if ((int) $duplicados > 0) {
        responderConversion(409, ['success' => false, 'message' => 'Ya existe una cita para este paciente en esa fecha y hora.']);
    }

    $conflicto = obtenerConflictoAgendaPsicologo($conn, $psicologoId, $programado, $tiempo, null, $pacienteId);
    if ($conflicto !== null && !$forzar) {
        $conn->rollback();
        $conn->close();
        responderConversion(409, array_merge([
            'success' => false,
        ], construirPayloadConflictoAgenda($conflicto, 'La psicóloga seleccionada ya tiene una cita o reservación en ese horario.')));
    }

    $forzada = ($conflicto !== null && $forzar) ? 1 : 0;
    $fechaRegistro = date('Y-m-d H:i:s');
    $creadoPor = (int) $_SESSION['id'];
    $estatus = 2;
    $costo = 0.0;

    $insert = $conn->prepare(
        'INSERT INTO Cita (IdNino, IdUsuario, idGenerado, fecha, costo, Programado, Tiempo, forzada, Estatus, Tipo, paquete_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)'
    );
    if ($insert === false) {
        throw new RuntimeException('No fue posible preparar la creación de la cita.');
    }
    $insert->bind_param('iiisdsiiis', $pacienteId, $psicologoId, $creadoPor, $fechaRegistro, $costo, $programado, $tiempo, $forzada, $estatus, $tipo);
    if (!$insert->execute()) {
        $insert->close();
        throw new RuntimeException('No fue posible crear la cita desde la reservación continua.');
    }
    $citaId = (int) $conn->insert_id;
    $insert->close();

    registrarLog(
        $conn,
        $creadoPor,
        'reservaciones_continuas',
        'convertir_a_cita',
        sprintf('La reservación continua #%d se convirtió en la cita #%d programada para %s.%s', $reservacionId, $citaId, $programado, $forzada === 1 ? ' Marcada como forzada.' : ''),
        'Cita',
        (string) $citaId
    );

    $conn->commit();
    $conn->close();
    responderConversion(201, [
        'success' => true,
        'cita_id' => $citaId,
        'forzada' => $forzada === 1,
        'message' => 'La reservación continua se convirtió en cita correctamente.',
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    responderConversion(400, ['success' => false, 'message' => $e->getMessage()]);
}
