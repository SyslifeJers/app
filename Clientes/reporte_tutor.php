<?php
ini_set('display_errors', '0');
header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
if (!in_array($rolUsuario, [3, 5], true)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'No tienes permisos para consultar este reporte.'
    ]);
    exit;
}

$tutorId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($tutorId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Identificador de tutor no válido.'
    ]);
    exit;
}

require_once __DIR__ . '/../conexion.php';
$conn = conectar();
$conn->set_charset('utf8mb4');

$response = [
    'success' => false,
    'error' => 'Ocurrió un error inesperado.'
];

$stmtTutor = $conn->prepare('SELECT id, name FROM Clientes WHERE id = ? LIMIT 1');
if (!$stmtTutor) {
    http_response_code(500);
    echo json_encode($response);
    exit;
}

$stmtTutor->bind_param('i', $tutorId);
$stmtTutor->execute();
$stmtTutor->bind_result($dbTutorId, $dbTutorNombre);
if (!$stmtTutor->fetch()) {
    $stmtTutor->close();
    $conn->close();
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'No se encontró el tutor solicitado.'
    ]);
    exit;
}
$stmtTutor->close();

$ninos = [];
$stmtNinos = $conn->prepare('SELECT id, name FROM nino WHERE idtutor = ? ORDER BY name ASC');
if ($stmtNinos) {
    $stmtNinos->bind_param('i', $tutorId);
    $stmtNinos->execute();
    $resultadoNinos = $stmtNinos->get_result();
    while ($fila = $resultadoNinos->fetch_assoc()) {
        $ninos[] = [
            'id' => isset($fila['id']) ? (int) $fila['id'] : null,
            'nombre' => $fila['name'] ?? ''
        ];
    }
    $stmtNinos->close();
}

$totalEventos = 0;
$stmtEventos = $conn->prepare('SELECT COUNT(ci.id) AS total_eventos
    FROM Cita ci
    INNER JOIN nino n ON n.id = ci.IdNino
    WHERE n.idtutor = ? AND ci.Estatus = 4');
if ($stmtEventos) {
    $stmtEventos->bind_param('i', $tutorId);
    $stmtEventos->execute();
    $stmtEventos->bind_result($eventos);
    if ($stmtEventos->fetch()) {
        $totalEventos = (int) $eventos;
    }
    $stmtEventos->close();
}

$totalRecaudado = 0.0;
$stmtRecaudado = $conn->prepare('SELECT COALESCE(SUM(cp.monto), 0) AS total_recaudado
    FROM CitaPagos cp
    INNER JOIN Cita ci ON ci.id = cp.cita_id
    INNER JOIN nino n ON n.id = ci.IdNino
    WHERE ci.Estatus = 4 AND n.idtutor = ?');
if ($stmtRecaudado) {
    $stmtRecaudado->bind_param('i', $tutorId);
    $stmtRecaudado->execute();
    $stmtRecaudado->bind_result($recaudado);
    if ($stmtRecaudado->fetch()) {
        $totalRecaudado = (float) $recaudado;
    }
    $stmtRecaudado->close();
}

$conn->close();

$response = [
    'success' => true,
    'data' => [
        'tutor' => [
            'id' => $dbTutorId,
            'nombre' => $dbTutorNombre,
        ],
        'ninos' => $ninos,
        'total_ninos' => count($ninos),
        'total_eventos' => $totalEventos,
        'total_recaudado' => $totalRecaudado,
    ],
];

echo json_encode($response);
