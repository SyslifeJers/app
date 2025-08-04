<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$id = $_GET['idTutor'];
$sql = "SELECT id, name, activo, edad, Observacion, FechaIngreso, idtutor FROM nino WHERE idtutor = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($users);

$conn->close();
?>
