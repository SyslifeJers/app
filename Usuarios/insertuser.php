<?php
ini_set('display_errors', 1);
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $user = $_POST['user'];
    $pass = $_POST['pass'];
    $telefono = $_POST['telefono'];
    $correo = $_POST['correo'];
    $IdRol = $_POST['IdRol'];

    // Validar la contraseña (mínimo 6 caracteres)
    if (strlen($pass) < 6) {
        die("La contraseña debe tener al menos 6 caracteres.");
    }

    // Conectar a la base de datos
	$servername = "localhost";
	$username = "clini234_cerene";
	$password = "tu{]ScpQ-Vcg";
	$dbname = "clini234_cerene";


    // Crear conexión
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Verificar conexión
    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

    // Preparar y vincular
    $stmt = $conn->prepare("INSERT INTO Usuarios (name, user, pass, token, activo, registro, telefono, correo, IdRol) VALUES (?, ?, ?, '', 1, NOW(), ?, ?, ?)");
    $stmt->bind_param("sssssi", $name, $user, $pass, $telefono, $correo, $IdRol);

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
