<?php
// Datos de la conexión
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);


$db_host = 'localhost';
$db_name = 'clini234_cerene'; // Asegúrate de colocar el nombre de tu base de datos aquí
$db_user = "clini234_cerene";
$db_pass = "tu{]ScpQ-Vcg";
session_start();
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    date_default_timezone_set('America/Mexico_City');
    $fechaActual = date('Y-m-d H:i:s'); // Formato de fecha y hora actual

    $idUsuario = $_SESSION['id'];

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $citaId = $_POST['citaId'];
        $fechaProgramada = $_POST['fechaProgramada'];

        $sql = "UPDATE `Cita` SET `Programado` = :fechaProgramada, Estatus = 3 WHERE `id` = :citaId";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':fechaProgramada' => $fechaProgramada, ':citaId' => $citaId]);

        $sqlInsert = "INSERT INTO `HistorialEstatus`(`id`, `fecha`, `idEstatus`, `idCita`, `idUsuario`) VALUES (null, :fecha, 3, :idCita, :idUsuario)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            ':fecha' => $fechaActual,
            ':idCita' => $citaId,
            ':idUsuario' => $idUsuario
        ]);

        echo "Fecha de cita actualizada correctamente.";header("Location:index.php");
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$pdo = null;
?>
