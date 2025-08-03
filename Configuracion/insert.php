<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

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