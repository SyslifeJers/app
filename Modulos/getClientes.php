
<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$id = $_GET['id'];
$sql = "SELECT c.`id`, c.`name`,c.`activo`, `telefono`, `correo`, GROUP_CONCAT(n.name) as Pasientes, c.fecha as Registro FROM `Clientes` c
inner join nino n on n.idtutor = c.id;";
$result = $conn->query($sql);
$user = $result->fetch_assoc();
echo json_encode($user);
$conn->close();
?>