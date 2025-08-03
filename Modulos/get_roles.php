<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

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