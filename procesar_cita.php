<?php
session_start();
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

        // Preparar la consulta SQL
        $sql = "INSERT INTO Cita (id, IdNino, IdUsuario, idGenerado, fecha, costo, Programado, Estatus, Tipo) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        // Asumiendo que $_SESSION['id'] y $_POST['resumenCosto'] también se necesitan
        $idCliente = $_POST['sendIdCliente'];
        $idPsicologo = $_POST['sendIdPsicologo'];
        $tipo = $_POST['resumenTipo'];
        $fechaCita = $_POST['resumenFecha'];
        $idGenerado = $_SESSION['id'];
        $estatus = 2;
        $fechaActual = date('Y-m-d H:i:s');
        $costo = $_POST['resumenCosto'];

        // Ejecutar la consulta
        $stmt->execute([$idCliente, $idPsicologo, $idGenerado, $fechaActual, $costo, $fechaCita, $estatus, $tipo]);

        // Redirigir y mostrar mensaje de éxito
        header("Location:index.php");
        echo "Cita guardada con éxito.";

    } catch (PDOException $e) {
        echo "Error al guardar la cita: " . $e->getMessage();
    }

    // Cerrar la conexión (opcional, ya que PDO lo hace automáticamente al finalizar el script)
    $conn = null;
} else {
    echo "Método de solicitud no válido.";
}
?>