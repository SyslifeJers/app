
<?php
ini_set('display_errors', 1);
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $id = $_POST['idTutor'];
    $edad = $_POST['edad'];
    $FechaIngreso = $_POST['FechaIngreso'];
    $Observaciones = $_POST['Observaciones'];

    require_once __DIR__ . '/../conexion.php';
    $conn = conectar();
    $conn->set_charset("utf8");
    // Preparar y vincular
    $stmt = $conn->prepare("INSERT INTO `nino`(`id`, `name`, `activo`, `edad`, `Observacion`, `FechaIngreso`, `idtutor`) VALUES (null,?,1,?,?,?,?)");
    $stmt->bind_param("sissi", $name,  $edad, $Observaciones, $FechaIngreso, $id);

    // Ejecutar la consulta
    if ($stmt->execute()) {
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
