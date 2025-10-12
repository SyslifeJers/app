<?php
			ini_set('error_reporting', E_ALL);
			ini_set('display_errors', 1);
?>
<?php
require_once 'conexion.php';
$conn = conectar();

$sql = "SELECT nin.id, CONCAT ( nin.name, ' Tutor: ', cli.name) AS name FROM nino nin INNER JOIN Clientes cli ON nin.idtutor = cli.id WHERE nin.activo = 1 AND cli.activo=1 order by  nin.name;";
$result = $conn->query($sql);
$users = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($users);
$conn->close();
?>
