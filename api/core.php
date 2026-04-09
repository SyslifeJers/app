<?php

declare(strict_types=1);

final class ApiHttpException extends RuntimeException
{
    public int $statusCode;
    public array $payload;

    public function __construct(int $statusCode, array $payload)
    {
        parent::__construct('API response');
        $this->statusCode = $statusCode;
        $this->payload = $payload;
    }
}

function apiIsCli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}


/**
 * Lee el body JSON para requests POST.
 */
function leerJsonBody(): array
{
    // Test harness can inject JSON body here.
    if (defined('API_TEST_MODE') && constant('API_TEST_MODE') === true && isset($GLOBALS['API_TEST_JSON_BODY'])) {
        return is_array($GLOBALS['API_TEST_JSON_BODY']) ? $GLOBALS['API_TEST_JSON_BODY'] : [];
    }

    $raw = file_get_contents('php://input');
    if ($raw === false) {
        return [];
    }

    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonResponse(400, ['error' => 'Body JSON inválido.']);
    }

    return $data;
}

/**
 * Envía una respuesta JSON y termina la ejecución.
 */
function jsonResponse(int $statusCode, array $payload): void
{
    if (defined('API_TEST_MODE') && constant('API_TEST_MODE') === true) {
        throw new ApiHttpException($statusCode, $payload);
    }

    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Normaliza un parámetro de fecha para usarlo en filtros SQL.
 */
function normalizarFechaParametro(?string $valor, DateTimeZone $tz, string $campo): ?string
{
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }

    try {
        $fecha = new DateTime($valor, $tz);
    } catch (Exception $exception) {
        jsonResponse(400, ['error' => sprintf('El parámetro %s no tiene un formato de fecha válido.', $campo)]);
    }

    $fecha->setTimezone($tz);

    return $fecha->format('Y-m-d H:i:s');
}

/**
 * Construye filtros y parámetros para rangos de fechas.
 */
function construirFiltroRango(?string $inicio, ?string $fin, string $columna): array
{
    $condiciones = [];
    $tipos = '';
    $parametros = [];

    if ($inicio !== null) {
        $condiciones[] = "{$columna} >= ?";
        $tipos .= 's';
        $parametros[] = $inicio;
    }

    if ($fin !== null) {
        $condiciones[] = "{$columna} < ?";
        $tipos .= 's';
        $parametros[] = $fin;
    }

    return [
        'condiciones' => $condiciones,
        'tipos' => $tipos,
        'parametros' => $parametros,
    ];
}

/**
 * Guarda/actualiza prospectos en la tabla de seguimiento.
 */
function registrarProspectosSeguimiento(mysqli $conn, array $prospectos): void
{
    if ($prospectos === []) {
        return;
    }

    $sql = 'INSERT INTO ProspectosSeguimiento (
                paciente_id,
                cliente_id,
                total_completadas,
                total_canceladas,
                calificacion,
                ultima_cita,
                estatus_id,
                origen_reporte,
                fecha_alta,
                fecha_actualizacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                cliente_id = VALUES(cliente_id),
                total_completadas = VALUES(total_completadas),
                total_canceladas = VALUES(total_canceladas),
                calificacion = VALUES(calificacion),
                ultima_cita = VALUES(ultima_cita),
                origen_reporte = VALUES(origen_reporte),
                fecha_actualizacion = NOW()';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        jsonResponse(500, ['error' => 'No fue posible preparar el alta de prospectos para seguimiento.']);
    }

    foreach ($prospectos as $prospecto) {
        $pacienteId = isset($prospecto['paciente_id']) ? (int) $prospecto['paciente_id'] : 0;
        if ($pacienteId <= 0) {
            continue;
        }

        $clienteId = isset($prospecto['cliente_id']) ? (int) $prospecto['cliente_id'] : null;
        $totalCompletadas = (int) ($prospecto['total_completadas'] ?? 0);
        $totalCanceladas = (int) ($prospecto['total_canceladas'] ?? 0);
        $calificacion = (float) ($prospecto['calificacion'] ?? 0);
        $ultimaCita = $prospecto['ultima_cita'] ?? null;
        $origenReporte = 'prospectos_promocion';
        $estatusCreada = 1;

        $stmt->bind_param(
            'iiiidsis',
            $pacienteId,
            $clienteId,
            $totalCompletadas,
            $totalCanceladas,
            $calificacion,
            $ultimaCita,
            $estatusCreada,
            $origenReporte
        );

        if (!$stmt->execute()) {
            $stmt->close();
            jsonResponse(500, ['error' => 'No fue posible registrar prospectos para seguimiento.']);
        }
    }

    $stmt->close();
}
