<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../conexion.php';
$conn = conectar();



$sql = "SELECT usu.id , usu.name 
        FROM  Usuarios usu 
        WHERE IdRol = 2 or IdRol = 3
        GROUP BY usu.id";

$stmt = $conn->prepare($sql); // 's' indica que el parámetro es una cadena
$stmt->execute();

// Vincular los resultados a variables
$stmt->bind_result($idUsuario, $name);

$usuarios = array();
while ($stmt->fetch()) {
    $usuarios[] = array('id' => $idUsuario, 'name' => $name);
}

$stmt->close();
$conn->close();

echo json_encode($usuarios);
?>