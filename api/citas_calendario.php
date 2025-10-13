<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

/**
 * Envía una respuesta JSON estandarizada y termina la ejecución.
 */
function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$timezone = new DateTimeZone('America/Mexico_City');

/**
 * Normaliza un valor recibido como parámetro de fecha.
 */
function normalizarParametro(?string $valor, DateTimeZone $tz, string $campo): ?string
{
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }

    try {
        $fecha = new DateTime($valor);
    } catch (Exception $exception) {
        jsonResponse(400, [
            'error' => sprintf('El parámetro %s no tiene un formato de fecha válido.', $campo),
        ]);
    }

    $fecha->setTimezone($tz);

    return $fecha->format('Y-m-d H:i:s');
}

$conn = conectar();

$fechaInicio = normalizarParametro($_GET['start'] ?? null, $timezone, 'start');
$fechaFin = normalizarParametro($_GET['end'] ?? null, $timezone, 'end');

$psicologoId = null;
if (array_key_exists('psicologo_id', $_GET)) {
    $psicologoRaw = trim((string) $_GET['psicologo_id']);

    if ($psicologoRaw !== '') {
        if (!ctype_digit($psicologoRaw)) {
            jsonResponse(400, ['error' => 'El parámetro psicologo_id debe ser un número entero positivo.']);
        }

        $psicologoId = (int) $psicologoRaw;

        if ($psicologoId <= 0) {
            jsonResponse(400, ['error' => 'El parámetro psicologo_id debe ser mayor que cero.']);
        }
    }
}

$condiciones = [];
$tipos = '';
$parametros = [];

if ($fechaInicio !== null) {
    $condiciones[] = 'ci.Programado >= ?';
    $tipos .= 's';
    $parametros[] = $fechaInicio;
}

if ($fechaFin !== null) {
    $condiciones[] = 'ci.Programado < ?';
    $tipos .= 's';
    $parametros[] = $fechaFin;
}

$condiciones[] = 'ci.Estatus IN (1, 2, 3, 4)';

if ($psicologoId !== null) {
    $condiciones[] = 'ci.IdUsuario = ?';
    $tipos .= 'i';
    $parametros[] = $psicologoId;
}

$sql = 'SELECT ci.id,
               ci.Programado,
               ci.Tipo,
               ci.FormaPago,
               ci.costo,
               ci.IdUsuario AS psicologo_id,
               n.name  AS paciente,
               us.name AS psicologo,
               co.codigo_hex AS psicologo_color,
               es.name AS estatus
        FROM Cita ci
        INNER JOIN nino n ON n.id = ci.IdNino
        INNER JOIN Usuarios us ON us.id = ci.IdUsuario
        LEFT JOIN colores co ON co.id = us.color_id
        INNER JOIN Estatus es ON es.id = ci.Estatus';

if ($condiciones !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}

$sql .= ' ORDER BY ci.Programado ASC';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    jsonResponse(500, ['error' => 'No fue posible preparar la consulta de citas.']);
}

if ($tipos !== '') {
    $stmt->bind_param($tipos, ...$parametros);
}

if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(500, ['error' => 'No fue posible ejecutar la consulta de citas.']);
}

$resultado = $stmt->get_result();
$eventos = [];

while ($fila = $resultado->fetch_assoc()) {
    $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $fila['Programado'], $timezone);

    if ($inicio === false) {
        continue;
    }

    $fin = clone $inicio;
    $fin->modify('+1 hour');

    $eventos[] = [
        'id' => (int) $fila['id'],
        'paciente' => $fila['paciente'],
        'psicologo' => $fila['psicologo'],
        'psicologo_id' => (int) $fila['psicologo_id'],
        'psicologo_color' => $fila['psicologo_color'] ?? null,
        'programado' => $inicio->format(DateTime::ATOM),
        'termina' => $fin->format(DateTime::ATOM),
        'estatus' => $fila['estatus'],
        'tipo' => $fila['Tipo'],
        'forma_pago' => $fila['FormaPago'],
        'costo' => $fila['costo'] !== null ? (float) $fila['costo'] : null,
    ];
}

$stmt->close();
$conn->close();

jsonResponse(200, ['data' => $eventos]);
