<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$id = $_GET['idtutor'];
$name = $_GET['name'];
$sql = "SELECT id, name, edad, activo, idtutor, `Observacion`, `FechaIngreso` FROM nino WHERE idtutor = ? AND name = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $id, $name);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
echo json_encode($user);

$conn->close();
?>
