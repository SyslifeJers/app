
<?php
ini_set('display_errors', 1);
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $id = $_POST['idTutor'];
    $edad = $_POST['edad'];
    $FechaIngreso = $_POST['FechaIngreso'];
    $Observaciones = $_POST['Observaciones'];

    require_once __DIR__ . '/../conexion.php';
    require_once __DIR__ . '/../Modulos/logger.php';
    $conn = conectar();
    $conn->set_charset("utf8");
    // Preparar y vincular
    $stmt = $conn->prepare("INSERT INTO `nino`(`id`, `name`, `activo`, `edad`, `Observacion`, `FechaIngreso`, `idtutor`) VALUES (null,?,1,?,?,?,?)");
    $stmt->bind_param("sissi", $name,  $edad, $Observaciones, $FechaIngreso, $id);

    // Ejecutar la consulta
    if ($stmt->execute()) {
        $ninoId = $conn->insert_id;
        registrarLog(
            $conn,
            $_SESSION['id'] ?? null,
            'pacientes',
            'crear',
            sprintf('Se agregó el paciente "%s" (ID %d) al tutor #%d.', $name, $ninoId, $id),
            'Paciente',
            (string) $ninoId
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
