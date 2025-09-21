<?php
ini_set('display_errors', 1);
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $telefono = $_POST['telefono'];
    $correo = $_POST['correo'];

    require_once __DIR__ . '/../conexion.php';
    require_once __DIR__ . '/../Modulos/logger.php';
    $conn = conectar();
    $conn->set_charset("utf8");

    // Preparar y vincular
    $stmt = $conn->prepare("INSERT INTO `Clientes`(`id`, `name`, `activo`, `fecha`, `telefono`, `correo`, `tipo`) VALUES (null,?,1,NOW(),?,?,0)");
    $stmt->bind_param("sss", $name,  $telefono, $correo);

    // Ejecutar la consulta
    if ($stmt->execute()) {
        $nuevoClienteId = $conn->insert_id;
        registrarLog(
            $conn,
            $_SESSION['id'] ?? null,
            'clientes',
            'crear',
            sprintf('Se registró el cliente "%s" (ID %d).', $name, $nuevoClienteId),
            'Cliente',
            (string) $nuevoClienteId
        );
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
