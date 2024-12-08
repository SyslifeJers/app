<?php
ini_set('display_errors', 1);
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $telefono = $_POST['telefono'];
    $correo = $_POST['correo'];

    // Conectar a la base de datos
	$servername = "localhost";
	$username = "clini234_cerene";
	$password = "tu{]ScpQ-Vcg";
	$dbname = "clini234_cerene";


    // Crear conexión
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8");
    // Verificar conexión
    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

    // Preparar y vincular
    $stmt = $conn->prepare("INSERT INTO `Clientes`(`id`, `name`, `activo`, `fecha`, `telefono`, `correo`, `tipo`) VALUES (null,?,1,NOW(),?,?,0)");
    $stmt->bind_param("sss", $name,  $telefono, $correo);

    // Ejecutar la consulta
    if ($stmt->execute()) {
        header("Location:index.php");
		die();
    } else {
        echo "Error: " . $stmt->error;
    }

    // Cerrar la conexión
    $stmt->close();
    $conn->close(); 
}
?>
