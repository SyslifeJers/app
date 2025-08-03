<?php
// getRoles.php

require_once 'conexion.php';
$conn = conectar();

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