
<?php
			ini_set('error_reporting', E_ALL);
			ini_set('display_errors', 1);
?>
<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$sql = "SELECT `id`, CONCAT( `name`, ': $' ,`costo` ) as name, `costo` FROM `Precios` WHERE `activo` = 1;";
$result = $conn->query($sql);
$users = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($users);
$conn->close();
?>
