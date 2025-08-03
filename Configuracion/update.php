<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

// Obtener datos del formulario
$id = $_POST['id'];
$activo = $_POST['activo'];

// Calcular el nuevo valor de activo (1 o 0)
$nuevo_activo = $activo == 1 ? 0 : 1;

// Preparar y ejecutar la consulta de actualización
$stmt = $conn->prepare("UPDATE Precios SET activo = ? WHERE id = ?");
$stmt->bind_param("ii", $nuevo_activo, $id);

if ($stmt->execute()) {
    echo "Estado actualizado correctamente";
} else {
    echo "Error: " . $stmt->error;
}

// Cerrar la conexión
$stmt->close();
$conn->close();

// Redirigir de vuelta a la página principal
header("Location: index.php");
exit;
?>