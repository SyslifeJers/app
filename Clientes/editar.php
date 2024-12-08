<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $edad = $_POST['edade'];
    $activo = $_POST['activoe'];
    $FechaIngreso = $_POST['FechaIngreso'];
    $Observaciones = $_POST['Observaciones'];

    // Datos de conexión
    $host = 'localhost';
    $db = 'clini234_cerene';
    $user = 'clini234_cerene';
    $pass = 'tu{]ScpQ-Vcg';

    try {
        // Crear la conexión
        $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("set names utf8mb3");
        // Preparar la consulta
        $sql = "UPDATE nino SET name = ?, edad = ?, activo = ?, Observacion =?, FechaIngreso = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nombre, $edad, $activo, $Observaciones,$FechaIngreso, $id]);

        // Redirigir o mostrar mensaje de éxito
        echo "Elemento actualizado correctamente.";

        header("Location:index.php");
		die();
   
        // header('Location: index.php'); // Redirigir a la página principal después de actualizar
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    // Cerrar la conexión
    $conn = null;
}
?>