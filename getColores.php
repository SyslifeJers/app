<?php
header('Content-Type: application/json');

require_once __DIR__ . '/conexion.php';

$conn = conectar();
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible conectar a la base de datos.']);
    exit;
}

$sql = 'SELECT id, nombre, codigo_hex FROM colores ORDER BY nombre ASC';
$resultado = $conn->query($sql);

if ($resultado === false) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible obtener el catálogo de colores.']);
    $conn->close();
    exit;
}

$colores = $resultado->fetch_all(MYSQLI_ASSOC);

$conn->close();

echo json_encode($colores);
