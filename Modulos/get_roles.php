<?php
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

// Consultar los roles activos
$sql = "SELECT id, name FROM Rol WHERE activo = 1";
$result = $conn->query($sql);

// Generar las opciones para el select
$options = '';
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $options .= '<option value="' . $row["id"] . '">' . $row["name"] . '</option>';
    }
}
echo $options;

// Cerrar la conexión
$conn->close();
?>