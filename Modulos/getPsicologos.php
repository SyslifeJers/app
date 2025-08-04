<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$sql = "SELECT usu.`id`,Rol.name as rol, usu.`name`, `telefono`, `correo`  FROM `Usuarios` usu
inner join Rol on Rol.id = usu.IdRol
WHERE usu.activo = 1 AND (usu.IdRol = 2);";
$result = $conn->query($sql);
$users = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($users);
$conn->close();
?>
