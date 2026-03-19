<?php

declare(strict_types=1);

define('API_TEST_MODE', true);

require_once __DIR__ . '/core.php';

function runCase(string $name, string $method, array $get, array $jsonBody = []): void
{
    $_GET = $get;
    $_SERVER['REQUEST_METHOD'] = $method;
    $GLOBALS['API_TEST_JSON_BODY'] = $jsonBody;

    echo "\n=== {$name} ===\n";

    try {
        // This will throw ApiHttpException via jsonResponse().
        require __DIR__ . '/../analitica.php';
        echo "(no response)\n";
    } catch (ApiHttpException $e) {
        echo "Status: {$e->statusCode}\n";
        echo json_encode($e->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "\n";
    }
}

runCase('Unknown reporte', 'GET', ['reporte' => 'no_existe']);
runCase('Citas basico', 'GET', ['reporte' => 'citas_basico']);
runCase('Mensajes comunicacion global (hoy)', 'GET', ['reporte' => 'mensajes_comunicacion', 'scope' => 'global', 'limit' => 3]);

// Prueba con escritura (opcional):
// ANALITICA_RUN_WRITES=1 php CereneSeguimientos/DB/api/analitica/test_runner.php
if (getenv('ANALITICA_RUN_WRITES') === '1') {
    runCase(
        'Guardar mensaje comunicacion global (dummy)',
        'POST',
        ['reporte' => 'guardar_mensaje_comunicacion'],
        ['reporte' => 'guardar_mensaje_comunicacion', 'scope' => 'global', 'estatus_id' => 7, 'plantilla' => 'Hola [nombre] 😊\nClinica Cerene te saluda. Ultima: [ultima_cita].', 'mensaje_renderizado' => '']
    );
}
