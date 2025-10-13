<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$sql = "SELECT usu.`id`, Rol.name AS rol, usu.`name`, `telefono`, `correo`, co.codigo_hex AS color_hex
FROM `Usuarios` usu
INNER JOIN Rol ON Rol.id = usu.IdRol
LEFT JOIN colores co ON co.id = usu.color_id
WHERE usu.activo = 1 AND (usu.IdRol = 2);";
$result = $conn->query($sql);
$users = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($users);
$conn->close();
?>
