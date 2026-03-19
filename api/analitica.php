<?php

require_once __DIR__ . '/analitica/core.php';

// Headers solo en modo web.
if (!apiIsCli() && !(defined('API_TEST_MODE') && constant('API_TEST_MODE') === true)) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (defined('API_TEST_MODE') && constant('API_TEST_MODE') === true) {
        throw new ApiHttpException(204, []);
    }

    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../conexion.php';

// Convertir errores fatales a JSON.
set_exception_handler(static function (Throwable $e): void {
    // Si ya es una respuesta controlada
    if ($e instanceof ApiHttpException) {
        jsonResponse($e->statusCode, $e->payload);
    }

    // En tests, exponer el detalle.
    if (defined('API_TEST_MODE') && constant('API_TEST_MODE') === true) {
        jsonResponse(500, [
            'error' => 'Unhandled exception',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    jsonResponse(500, ['error' => 'Error interno.']);
});

$timezone = new DateTimeZone('America/Mexico_City');

$jsonBody = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonBody = leerJsonBody();
}

$reporte = isset($_GET['reporte']) ? trim((string) $_GET['reporte']) : trim((string) ($jsonBody['reporte'] ?? 'citas_basico'));
if ($reporte === '') {
    $reporte = 'citas_basico';
}

$fechaInicio = normalizarFechaParametro($_GET['fecha_inicio'] ?? null, $timezone, 'fecha_inicio');
$fechaFin = normalizarFechaParametro($_GET['fecha_fin'] ?? null, $timezone, 'fecha_fin');

$conn = conectar();
if (!($conn instanceof mysqli) || $conn->connect_errno) {
    jsonResponse(500, ['error' => 'No se pudo establecer la conexión a la base de datos.']);
}

$conn->set_charset('utf8mb4');

$handlersDir = __DIR__ . '/analitica/handlers';
switch ($reporte) {
    case 'guardar_seguimiento_prospecto':
        require $handlersDir . '/guardar_seguimiento_prospecto.php';
        break;
    case 'guardar_mensaje_comunicacion':
        require $handlersDir . '/guardar_mensaje_comunicacion.php';
        break;
    case 'mensajes_comunicacion':
        require $handlersDir . '/mensajes_comunicacion.php';
        break;
    case 'prospectos_seguimiento_historial':
        require $handlersDir . '/prospectos_seguimiento_historial.php';
        break;
    case 'citas_basico':
        require $handlersDir . '/citas_basico.php';
        break;
    case 'clientes_basico':
        require $handlersDir . '/clientes_basico.php';
        break;
    case 'cancelaciones_frecuentes':
        require $handlersDir . '/cancelaciones_frecuentes.php';
        break;
    case 'prospectos_promocion':
        require $handlersDir . '/prospectos_promocion.php';
        break;
    case 'prospectos_seguimiento':
        require $handlersDir . '/prospectos_seguimiento.php';
        break;
    case 'clientes_adherencia':
    case 'pacientes_adherencia':
        require $handlersDir . '/pacientes_adherencia.php';
        break;
    case 'catalogo_estatus_prospecto':
        require $handlersDir . '/catalogo_estatus_prospecto.php';
        break;
    case 'catalogo_promociones':
        require $handlersDir . '/catalogo_promociones.php';
        break;
    default:
        jsonResponse(400, [
            'error' => 'Reporte no reconocido. Usa: citas_basico, clientes_basico, cancelaciones_frecuentes, prospectos_promocion, prospectos_seguimiento, prospectos_seguimiento_historial, guardar_seguimiento_prospecto, guardar_mensaje_comunicacion, mensajes_comunicacion, catalogo_estatus_prospecto, catalogo_promociones, pacientes_adherencia.',
        ]);
}
