
<?php
ini_set('display_errors', 1);
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $id = $_POST['idTutor'];
    $edad = $_POST['edad'];
    $FechaIngreso = $_POST['FechaIngreso'];
    $Observaciones = $_POST['Observaciones'];

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
    $conn->set_charset("utf8");
    // Preparar y vincular
    $stmt = $conn->prepare("INSERT INTO `nino`(`id`, `name`, `activo`, `edad`, `Observacion`, `FechaIngreso`, `idtutor`) VALUES (null,?,1,?,?,?,?)");
    $stmt->bind_param("sissi", $name,  $edad, $Observaciones, $FechaIngreso, $id);

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
