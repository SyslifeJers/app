<?php
// getRoles.php

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

// Consulta SQL
$sql = "SELECT id, name FROM Rol";
$result = $conn->query($sql);

$roles = array();

if ($result->num_rows > 0) {
    // Salida de datos de cada fila
    while($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
} 

// Cerrar conexión
$conn->close();

// Devolver resultados en formato JSON
echo json_encode($roles);
?>