
<?php
			ini_set('error_reporting', E_ALL);
			ini_set('display_errors', 1);
?>
<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$sql = "SELECT `id`, `name` AS nombre, CONCAT(`name`, ': $', `costo`) AS name, `costo`, COALESCE(`sesiones_total`, 1) AS sesiones_total, COALESCE(`requiere_cita`, 1) AS requiere_cita, COALESCE(`permite_sin_fecha`, 0) AS permite_sin_fecha FROM `Precios` WHERE `activo` = 1;";
$result = $conn->query($sql);
$users = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($users);
$conn->close();
?>
