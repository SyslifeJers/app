<?php
ini_set('display_errors', 1);
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $user = $_POST['user'];
    $pass = $_POST['pass'];
    $telefono = $_POST['telefono'];
    $correo = $_POST['correo'];
    $IdRol = $_POST['IdRol'];
    $colorId = isset($_POST['color_id']) && $_POST['color_id'] !== '' ? (int) $_POST['color_id'] : 0;

    // Validar la contraseña (mínimo 6 caracteres)
    if (strlen($pass) < 6) {
        die("La contraseña debe tener al menos 6 caracteres.");
    }

    require_once __DIR__ . '/../conexion.php';
    $conn = conectar();

    // Preparar y vincular
    $stmt = $conn->prepare("INSERT INTO Usuarios (name, user, pass, token, activo, registro, telefono, correo, IdRol, color_id) VALUES (?, ?, ?, '', 1, NOW(), ?, ?, ?, NULLIF(?, 0))");
    $stmt->bind_param("sssssii", $name, $user, $pass, $telefono, $correo, $IdRol, $colorId);

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
