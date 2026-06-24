<?php
require_once __DIR__ . '/../conexion.php';
$conn = conectar();

// Obtener datos del formulario
$name = $_POST['name'];
$costo = $_POST['costo'];
$sesionesTotal = isset($_POST['sesiones_total']) ? (int) $_POST['sesiones_total'] : 1;
if ($sesionesTotal < 0) {
    $sesionesTotal = 0;
}
$requiereCita = isset($_POST['requiere_cita']) ? 1 : 0;
$permiteSinFecha = isset($_POST['permite_sin_fecha']) ? 1 : 0;

// Preparar y ejecutar la consulta de inserción
$stmt = $conn->prepare("INSERT INTO Precios (id, name, costo, sesiones_total, requiere_cita, permite_sin_fecha, activo) VALUES (NULL, ?, ?, ?, ?, ?, 1)");
$stmt->bind_param("sdiii", $name, $costo, $sesionesTotal, $requiereCita, $permiteSinFecha);

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
