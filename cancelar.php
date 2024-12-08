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
        $estatus = $_POST['estatus'];
        $formaPago = $_POST['formaPago'];

        // Actualizar la cita con la nueva fecha programada y la forma de pago
        $sql = "UPDATE `Cita` SET  `FormaPago` = :formaPago, Estatus = :estatus WHERE `id` = :citaId";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':formaPago' => $formaPago,
            ':citaId' => $citaId,
            ':estatus' => $estatus
        ]);

        // Insertar en el historial de estatus
        $sqlInsert = "INSERT INTO `HistorialEstatus`(`id`, `fecha`, `idEstatus`, `idCita`, `idUsuario`) VALUES (null, :fecha, 3, :idCita, :idUsuario)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            ':fecha' => $fechaActual,
            ':idCita' => $citaId,
            ':idUsuario' => $idUsuario
        ]);

        echo "Fecha de cita y forma de pago actualizadas correctamente.";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$pdo = null;
?>
