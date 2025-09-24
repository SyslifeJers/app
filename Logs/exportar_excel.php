<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['token'])) {
    header('Location: https://app.clinicacerene.com/login.php');
    exit();
}

require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$stmt = $conn->prepare('SELECT token FROM Usuarios WHERE user = ?');
$stmt->bind_param('s', $_SESSION['user']);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($dbToken);
$stmt->fetch();

if ($stmt->num_rows === 0 || $_SESSION['token'] !== $dbToken) {
    header('Location: https://app.clinicacerene.com/login.php');
    exit();
}

$stmt->close();

$rolUsuario = $_SESSION['rol'] ?? 0;
if ($rolUsuario !== 3) {
    header('Location: /index.php');
    exit();
}

$tablaLogsExiste = false;
if ($resultado = $conn->query("SHOW TABLES LIKE 'LogSistema'")) {
    $tablaLogsExiste = $resultado->num_rows > 0;
    $resultado->free();
}

if (!$tablaLogsExiste) {
    header('Content-Type: text/plain; charset=UTF-8');
    http_response_code(404);
    echo 'La tabla de logs no está disponible.';
    exit();
}

$limite = 500;
$registros = [];
if ($stmtLogs = $conn->prepare('SELECT ls.fecha, us.name AS usuario_nombre, ls.modulo, ls.accion, ls.descripcion, ls.entidad, ls.referencia, ls.ip FROM LogSistema ls LEFT JOIN Usuarios us ON us.id = ls.usuario_id ORDER BY ls.fecha DESC LIMIT ?')) {
    $stmtLogs->bind_param('i', $limite);
    $stmtLogs->execute();
    $resultadoLogs = $stmtLogs->get_result();
    while ($fila = $resultadoLogs->fetch_assoc()) {
        $registros[] = $fila;
    }
    $stmtLogs->close();
}

$nombreArchivo = 'logs_sistema_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
echo "<table border='1'>";
echo '<thead>';
echo '<tr>';
echo '<th>Fecha</th>';
echo '<th>Usuario</th>';
echo '<th>Módulo</th>';
echo '<th>Acción</th>';
echo '<th>Descripción</th>';
echo '<th>Entidad</th>';
echo '<th>Referencia</th>';
echo '<th>IP</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

if (empty($registros)) {
    echo "<tr><td colspan='8'>No se encontraron registros para exportar.</td></tr>";
} else {
    foreach ($registros as $registro) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($registro['fecha'], ENT_QUOTES, 'UTF-8') . '</td>';
        $usuario = $registro['usuario_nombre'] ?? 'Sin registro';
        echo '<td>' . htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($registro['modulo'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($registro['accion'], ENT_QUOTES, 'UTF-8') . '</td>';
        $descripcion = nl2br(htmlspecialchars($registro['descripcion'], ENT_QUOTES, 'UTF-8'));
        echo '<td>' . $descripcion . '</td>';
        echo '<td>' . htmlspecialchars($registro['entidad'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($registro['referencia'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($registro['ip'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
}

echo '</tbody>';
echo '</table>';

$conn->close();
exit();
