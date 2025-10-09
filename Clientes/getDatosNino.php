<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;

$id = isset($_GET['idTutor']) ? (int) $_GET['idTutor'] : 0;

if ($id <= 0) {
    echo json_encode([]);
    $conn->close();
    exit;
}

$sql = "SELECT id, name, activo, edad, Observacion, FechaIngreso, idtutor FROM nino WHERE idtutor = ?";

if ($rolUsuario === 1) {
    $sql .= " AND activo = 1";
}

$sql .= " ORDER BY name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($users, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$stmt->close();
$conn->close();
?>
