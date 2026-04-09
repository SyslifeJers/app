<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/logger.php';

/**
 * Envía una respuesta JSON y termina la ejecución.
 */
function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Obtiene y decodifica el cuerpo JSON de la petición.
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(400, ['error' => 'JSON inválido: ' . json_last_error_msg()]);
    }

    if (!is_array($data)) {
        jsonResponse(400, ['error' => 'El cuerpo de la petición debe ser un objeto JSON.']);
    }

    return $data;
}

/**
 * Determina si un arreglo utiliza índices secuenciales numéricos.
 */
function esLista(array $valor): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($valor);
    }

    $esperado = 0;
    foreach ($valor as $indice => $_) {
        if ($indice !== $esperado) {
            return false;
        }
        $esperado++;
    }

    return true;
}

/**
 * Devuelve un listado de citas a partir de la petición recibida.
 */
function normalizarColeccionCitas(array $payload, string $contexto): array
{
    if ($payload === []) {
        jsonResponse(400, ['error' => "No se recibieron datos para {$contexto} citas."]);
    }

    if (isset($payload['citas'])) {
        if (!is_array($payload['citas'])) {
            jsonResponse(400, ['error' => 'El campo citas debe ser una lista de objetos.']);
        }
        if ($payload['citas'] === []) {
            jsonResponse(400, ['error' => "No se recibieron citas para {$contexto}."]);
        }

        $comunes = $payload;
        unset($comunes['citas']);

        $resultado = [];
        foreach ($payload['citas'] as $indice => $cita) {
            if (!is_array($cita)) {
                jsonResponse(400, ['error' => "Cada cita dentro de 'citas' debe ser un objeto (índice {$indice})."]);
            }
            $resultado[] = array_merge($comunes, $cita);
        }

        return $resultado;
    }

    if (esLista($payload)) {
        if ($payload === []) {
            jsonResponse(400, ['error' => "No se recibieron citas para {$contexto}."]);
        }

        $resultado = [];
        foreach ($payload as $indice => $cita) {
            if (!is_array($cita)) {
                jsonResponse(400, ['error' => "Cada elemento de la lista de citas debe ser un objeto (índice {$indice})."]);
            }
            $resultado[] = $cita;
        }

        return $resultado;
    }

    return [$payload];
}

/**
 * Valida y normaliza los datos necesarios para crear una cita.
 */
function prepararDatosCitaParaCrear(array $data): array
{
    $required = ['paciente_id', 'psicologo_id', 'creado_por', 'programado', 'costo', 'tipo'];
    foreach ($required as $campo) {
        if (!array_key_exists($campo, $data)) {
            jsonResponse(400, ['error' => "Falta el campo obligatorio: {$campo}."]);
        }
    }

    $pacienteId = filter_var($data['paciente_id'], FILTER_VALIDATE_INT);
    $psicologoId = filter_var($data['psicologo_id'], FILTER_VALIDATE_INT);
    $creadoPor = filter_var($data['creado_por'], FILTER_VALIDATE_INT);
    $tipo = trim((string) $data['tipo']);

    if ($pacienteId === false || $pacienteId <= 0) {
        jsonResponse(400, ['error' => 'El paciente_id debe ser un número entero positivo.']);
    }
    if ($psicologoId === false || $psicologoId <= 0) {
        jsonResponse(400, ['error' => 'El psicologo_id debe ser un número entero positivo.']);
    }
    if ($creadoPor === false || $creadoPor <= 0) {
        jsonResponse(400, ['error' => 'El creado_por debe ser un número entero positivo.']);
    }
    if ($tipo === '') {
        jsonResponse(400, ['error' => 'El tipo no puede estar vacío.']);
    }

    $programado = normalizarFecha((string) $data['programado'], 'programado');
    $costo = filter_var((string) $data['costo'], FILTER_VALIDATE_FLOAT);
    if ($costo === false) {
        jsonResponse(400, ['error' => 'El costo debe ser un número válido.']);
    }

    $estatus = isset($data['estatus']) ? (int) $data['estatus'] : 2;

    return [
        'paciente_id' => $pacienteId,
        'psicologo_id' => $psicologoId,
        'creado_por' => $creadoPor,
        'programado' => $programado,
        'costo' => (float) $costo,
        'estatus' => $estatus,
        'tipo' => $tipo,
    ];
}

/**
 * Prepara la información necesaria para actualizar una cita.
 */
function prepararActualizacionCita(array $data, array $actual, int $id): array
{
    if ($data === []) {
        jsonResponse(400, ['error' => 'No se recibieron datos para actualizar.']);
    }

    $usuarioLog = isset($data['usuario_id']) ? (int) $data['usuario_id'] : null;

    $campos = [];
    $tipos = '';
    $valores = [];

    $pacienteId = (int) $actual['paciente_id'];
    $programado = (string) $actual['programado'];

    if (array_key_exists('paciente_id', $data)) {
        $pacienteIdValidado = filter_var($data['paciente_id'], FILTER_VALIDATE_INT);
        if ($pacienteIdValidado === false || $pacienteIdValidado <= 0) {
            jsonResponse(400, ['error' => 'El paciente_id debe ser un número entero positivo.']);
        }
        $pacienteId = $pacienteIdValidado;
        $campos[] = 'IdNino = ?';
        $tipos .= 'i';
        $valores[] = $pacienteId;
    }

    if (array_key_exists('psicologo_id', $data)) {
        $psicologoId = filter_var($data['psicologo_id'], FILTER_VALIDATE_INT);
        if ($psicologoId === false || $psicologoId <= 0) {
            jsonResponse(400, ['error' => 'El psicologo_id debe ser un número entero positivo.']);
        }
        $campos[] = 'IdUsuario = ?';
        $tipos .= 'i';
        $valores[] = $psicologoId;
    }

    if (array_key_exists('programado', $data)) {
        $programado = normalizarFecha((string) $data['programado'], 'programado');
        $campos[] = 'Programado = ?';
        $tipos .= 's';
        $valores[] = $programado;
    }

    if (array_key_exists('costo', $data)) {
        $costo = filter_var((string) $data['costo'], FILTER_VALIDATE_FLOAT);
        if ($costo === false) {
            jsonResponse(400, ['error' => 'El costo debe ser un número válido.']);
        }
        $campos[] = 'costo = ?';
        $tipos .= 'd';
        $valores[] = (float) $costo;
    }

    if (array_key_exists('estatus', $data)) {
        $estatus = filter_var($data['estatus'], FILTER_VALIDATE_INT);
        if ($estatus === false || $estatus < 0) {
            jsonResponse(400, ['error' => 'El estatus debe ser un número entero válido.']);
        }
        $campos[] = 'Estatus = ?';
        $tipos .= 'i';
        $valores[] = $estatus;
    }

    if (array_key_exists('tipo', $data)) {
        $tipo = trim((string) $data['tipo']);
        if ($tipo === '') {
            jsonResponse(400, ['error' => 'El tipo no puede estar vacío.']);
        }
        $campos[] = 'Tipo = ?';
        $tipos .= 's';
        $valores[] = $tipo;
    }

    if ($campos === []) {
        jsonResponse(400, ['error' => 'No se proporcionaron campos válidos para actualizar.']);
    }

    return [
        'usuario_log' => $usuarioLog,
        'campos' => $campos,
        'tipos' => $tipos,
        'valores' => $valores,
        'paciente_id' => $pacienteId,
        'programado' => $programado,
        'paciente_original' => (int) $actual['paciente_id'],
        'programado_original' => (string) $actual['programado'],
    ];
}

/**
 * Normaliza y valida una fecha en formato Y-m-d H:i:s o ISO-8601.
 */
function normalizarFecha(string $valor, string $campo): string
{
    $valor = trim($valor);
    if ($valor === '') {
        jsonResponse(400, ['error' => "El campo {$campo} no puede estar vacío."]);
    }

    $formatos = ['Y-m-d H:i:s', DateTime::ATOM];
    foreach ($formatos as $formato) {
        $fecha = DateTime::createFromFormat($formato, $valor);
        if ($fecha instanceof DateTime) {
            return $fecha->format('Y-m-d H:i:s');
        }
    }

    jsonResponse(400, ['error' => "El campo {$campo} debe tener el formato 'Y-m-d H:i:s'."]);
}

/**
 * Ejecuta una actualización previamente preparada sobre la cita.
 */
function aplicarActualizacionPreparada(mysqli $conn, int $id, array $datos, bool $rollbackEnError = false): array
{
    $requiereValidacion = $datos['programado'] !== $datos['programado_original']
        || $datos['paciente_id'] !== $datos['paciente_original'];

    if ($requiereValidacion) {
        $check = $conn->prepare('SELECT COUNT(*) FROM Cita WHERE IdNino = ? AND Programado = ? AND id <> ?');
        if ($check === false) {
            if ($rollbackEnError) {
                $conn->rollback();
            }
            jsonResponse(500, ['error' => 'No fue posible validar la disponibilidad de la cita.']);
        }
        $check->bind_param('isi', $datos['paciente_id'], $datos['programado'], $id);
        $check->execute();
        $check->bind_result($duplicados);
        $check->fetch();
        $check->close();

        if ($duplicados > 0) {
            if ($rollbackEnError) {
                $conn->rollback();
            }
            jsonResponse(409, ['error' => 'Ya existe otra cita para este paciente en la fecha y hora indicadas.']);
        }
    }

    $sql = 'UPDATE Cita SET ' . implode(', ', $datos['campos']) . ' WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        if ($rollbackEnError) {
            $conn->rollback();
        }
        jsonResponse(500, ['error' => 'No fue posible preparar la actualización.']);
    }

    $tipos = $datos['tipos'] . 'i';
    $valores = $datos['valores'];
    $valores[] = $id;

    $stmt->bind_param($tipos, ...$valores);

    if (!$stmt->execute()) {
        $stmt->close();
        if ($rollbackEnError) {
            $conn->rollback();
        }
        jsonResponse(500, ['error' => 'Ocurrió un error al actualizar la cita.']);
    }
    $stmt->close();

    registrarLog(
        $conn,
        $datos['usuario_log'],
        'citas',
        'actualizar_api',
        sprintf('Se actualizó la cita #%d.', $id),
        'Cita',
        (string) $id
    );

    return obtenerCitaPorId($conn, $id);
}

/**
 * Obtiene la información de una cita por su identificador.
 */
function obtenerCitaPorId(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare(
        'SELECT id,
                IdNino      AS paciente_id,
                IdUsuario   AS psicologo_id,
                idGenerado  AS creado_por,
                fecha,
                costo,
                Programado  AS programado,
                Estatus     AS estatus,
                Tipo        AS tipo
         FROM Cita
         WHERE id = ?'
    );

    if ($stmt === false) {
        jsonResponse(500, ['error' => 'No fue posible preparar la consulta.']);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $cita = $resultado ? $resultado->fetch_assoc() : null;
    $stmt->close();

    if (!$cita) {
        return null;
    }

    $cita['costo'] = isset($cita['costo']) ? (float) $cita['costo'] : null;

    return $cita;
}

$conn = conectar();

if (!($conn instanceof mysqli) || $conn->connect_errno) {
    jsonResponse(500, ['error' => 'No se pudo establecer la conexión a la base de datos.']);
}

$conn->set_charset('utf8');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id > 0) {
            $cita = obtenerCitaPorId($conn, $id);
            if ($cita === null) {
                jsonResponse(404, ['error' => 'No se encontró la cita solicitada.']);
            }
            jsonResponse(200, ['data' => $cita]);
        }

        $resultado = $conn->query(
            'SELECT id,
                    IdNino      AS paciente_id,
                    IdUsuario   AS psicologo_id,
                    idGenerado  AS creado_por,
                    fecha,
                    costo,
                    Programado  AS programado,
                    Estatus     AS estatus,
                    Tipo        AS tipo
             FROM Cita
             ORDER BY Programado DESC'
        );

        if ($resultado === false) {
            jsonResponse(500, ['error' => 'No fue posible obtener las citas.']);
        }

        $citas = [];
        while ($fila = $resultado->fetch_assoc()) {
            $fila['costo'] = isset($fila['costo']) ? (float) $fila['costo'] : null;
            $citas[] = $fila;
        }

        jsonResponse(200, ['data' => $citas]);

    case 'POST':
        $payload = getJsonInput();
        $citasSolicitadas = normalizarColeccionCitas($payload, 'crear');

        $preparadas = [];
        $combinaciones = [];
        foreach ($citasSolicitadas as $indice => $entrada) {
            $citaPreparada = prepararDatosCitaParaCrear($entrada);
            $clave = $citaPreparada['paciente_id'] . '|' . $citaPreparada['programado'];
            if (isset($combinaciones[$clave])) {
                $indicePrevio = $combinaciones[$clave];
                jsonResponse(409, ['error' => sprintf(
                    'La cita para el paciente %d en %s está duplicada en la solicitud (índices %d y %d).',
                    $citaPreparada['paciente_id'],
                    $citaPreparada['programado'],
                    $indicePrevio,
                    $indice
                )]);
            }
            $combinaciones[$clave] = $indice;
            $preparadas[] = $citaPreparada;
        }

        $conn->begin_transaction();

        $creadas = [];
        foreach ($preparadas as $cita) {
            $check = $conn->prepare('SELECT COUNT(*) FROM Cita WHERE IdNino = ? AND Programado = ?');
            if ($check === false) {
                $conn->rollback();
                jsonResponse(500, ['error' => 'No fue posible validar la disponibilidad de la cita.']);
            }
            $check->bind_param('is', $cita['paciente_id'], $cita['programado']);
            $check->execute();
            $check->bind_result($duplicados);
            $check->fetch();
            $check->close();

            if ($duplicados > 0) {
                $conn->rollback();
                jsonResponse(409, ['error' => 'Ya existe una cita para este paciente en la fecha y hora indicadas.']);
            }

            $fechaRegistro = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');

            $stmt = $conn->prepare(
                'INSERT INTO Cita (IdNino, IdUsuario, idGenerado, fecha, costo, Programado, Estatus, Tipo) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if ($stmt === false) {
                $conn->rollback();
                jsonResponse(500, ['error' => 'No fue posible registrar la cita.']);
            }

            $stmt->bind_param(
                'iiisdsis',
                $cita['paciente_id'],
                $cita['psicologo_id'],
                $cita['creado_por'],
                $fechaRegistro,
                $cita['costo'],
                $cita['programado'],
                $cita['estatus'],
                $cita['tipo']
            );

            if (!$stmt->execute()) {
                $stmt->close();
                $conn->rollback();
                jsonResponse(500, ['error' => 'Ocurrió un error al guardar la cita.']);
            }

            $nuevoId = $stmt->insert_id;
            $stmt->close();

            registrarLog(
                $conn,
                $cita['creado_por'],
                'citas',
                'crear_api',
                sprintf('Se creó la cita #%d para el paciente %d programada el %s.', $nuevoId, $cita['paciente_id'], $cita['programado']),
                'Cita',
                (string) $nuevoId
            );

            $creadas[] = obtenerCitaPorId($conn, $nuevoId);
        }

        $conn->commit();

        if (count($creadas) === 1) {
            jsonResponse(201, ['data' => $creadas[0]]);
        }

        jsonResponse(201, ['data' => $creadas, 'count' => count($creadas)]);

    case 'PUT':
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id > 0) {
            $actual = obtenerCitaPorId($conn, $id);
            if ($actual === null) {
                jsonResponse(404, ['error' => 'La cita que deseas actualizar no existe.']);
            }

            $datos = getJsonInput();
            $preparada = prepararActualizacionCita($datos, $actual, $id);
            $citaActualizada = aplicarActualizacionPreparada($conn, $id, $preparada);
            jsonResponse(200, ['data' => $citaActualizada]);
        }

        $payload = getJsonInput();
        $citasPorActualizar = normalizarColeccionCitas($payload, 'actualizar');

        $preparadas = [];
        foreach ($citasPorActualizar as $indice => $entrada) {
            if (!array_key_exists('id', $entrada)) {
                jsonResponse(400, ['error' => sprintf('Cada cita a actualizar debe incluir su id (índice %d).', $indice)]);
            }

            $idCita = filter_var($entrada['id'], FILTER_VALIDATE_INT);
            if ($idCita === false || $idCita <= 0) {
                jsonResponse(400, ['error' => sprintf('El id de la cita debe ser un número entero positivo (índice %d).', $indice)]);
            }

            $actual = obtenerCitaPorId($conn, $idCita);
            if ($actual === null) {
                jsonResponse(404, ['error' => sprintf('La cita #%d que deseas actualizar no existe.', $idCita)]);
            }

            $datosCita = $entrada;
            unset($datosCita['id']);
            $preparada = prepararActualizacionCita($datosCita, $actual, $idCita);
            $preparada['id'] = $idCita;
            $preparadas[] = $preparada;
        }

        $conn->begin_transaction();

        $actualizadas = [];
        foreach ($preparadas as $preparada) {
            $actualizadas[] = aplicarActualizacionPreparada($conn, $preparada['id'], $preparada, true);
        }

        $conn->commit();

        if (count($actualizadas) === 1) {
            jsonResponse(200, ['data' => $actualizadas[0]]);
        }

        jsonResponse(200, ['data' => $actualizadas, 'count' => count($actualizadas)]);

    case 'DELETE':
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            jsonResponse(400, ['error' => 'Debes proporcionar un identificador válido en la consulta.']);
        }

        $actual = obtenerCitaPorId($conn, $id);
        if ($actual === null) {
            jsonResponse(404, ['error' => 'La cita que deseas eliminar no existe.']);
        }

        $data = getJsonInput();
        $usuarioLog = isset($data['usuario_id']) ? (int) $data['usuario_id'] : null;

        $stmt = $conn->prepare('DELETE FROM Cita WHERE id = ?');
        if ($stmt === false) {
            jsonResponse(500, ['error' => 'No fue posible preparar la eliminación.']);
        }

        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            $stmt->close();
            jsonResponse(500, ['error' => 'Ocurrió un error al eliminar la cita.']);
        }
        $stmt->close();

        registrarLog(
            $conn,
            $usuarioLog,
            'citas',
            'eliminar_api',
            sprintf('Se eliminó la cita #%d del paciente %d.', $id, (int) $actual['paciente_id']),
            'Cita',
            (string) $id
        );

        jsonResponse(200, ['message' => 'Cita eliminada correctamente.']);

    default:
        jsonResponse(405, ['error' => 'Método no permitido.']);
}
