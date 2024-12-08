<?php
// Datos de conexión a la base de datos
$db_host = 'localhost';
$db_name = 'clini234_cerene';
$db_user = 'clini234_cerene';
$db_pass = 'tu{]ScpQ-Vcg';

// Conectar a la base de datos
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Obtener datos del formulario
$name = $_POST['name'];
$costo = $_POST['costo'];

// Preparar y ejecutar la consulta de inserción
$stmt = $conn->prepare("INSERT INTO Precios (id, name, costo, activo) VALUES (NULL, ?, ?, 1)");
$stmt->bind_param("sd", $name, $costo);

if ($stmt->execute()) {
    echo "Nuevo precio agregado correctamente";
    header("Location:index.php");
} else {
    echo "Error: " . $stmt->error;
}

// Cerrar la conexión
$stmt->close();
$conn->close();
?>