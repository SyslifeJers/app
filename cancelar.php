<?php
// Datos de la conexión
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once 'conexion.php';
$conn = conectar();
session_start();

date_default_timezone_set('America/Mexico_City');
$fechaActual = date('Y-m-d H:i:s'); // Formato de fecha y hora actual

$idUsuario = $_SESSION['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $citaId = isset($_POST['citaId']) ? (int) $_POST['citaId'] : 0;
    $estatus = isset($_POST['estatus']) ? (int) $_POST['estatus'] : 0;
    $formaPago = $_POST['formaPago'] ?? null;

    if ($citaId <= 0 || $estatus <= 0) {
        header('Location: index.php');
        exit;
    }

    if ($formaPago === null || $formaPago === '') {
        $stmt = $conn->prepare('UPDATE Cita SET FormaPago = NULL, Estatus = ? WHERE id = ?');
        $stmt->bind_param('ii', $estatus, $citaId);
    } else {
        $stmt = $conn->prepare('UPDATE Cita SET FormaPago = ?, Estatus = ? WHERE id = ?');
        $stmt->bind_param('sii', $formaPago, $estatus, $citaId);
    }
    $stmt->execute();
    $stmt->close();

    $stmtInsert = $conn->prepare('INSERT INTO HistorialEstatus(id, fecha, idEstatus, idCita, idUsuario) VALUES (null, ?, ?, ?, ?)');
    $stmtInsert->bind_param('siii', $fechaActual, $estatus, $citaId, $idUsuario);
    $stmtInsert->execute();
    $stmtInsert->close();

    if ($estatus === 1) {
        $tablaSolicitudes = $conn->query("SHOW TABLES LIKE 'SolicitudReprogramacion'");
        if ($tablaSolicitudes instanceof mysqli_result && $tablaSolicitudes->num_rows > 0) {
            $tablaSolicitudes->free();

            $comentarioCancelacion = 'Solicitud cerrada automáticamente por cancelación de la cita.';
            $stmtCancelar = $conn->prepare("UPDATE SolicitudReprogramacion SET estatus = 'rechazada', aprobado_por = ?, fecha_respuesta = ?, comentarios = CASE WHEN comentarios IS NULL OR comentarios = '' THEN ? ELSE CONCAT(comentarios, '\n', ?) END WHERE cita_id = ? AND estatus = 'pendiente'");
            $stmtCancelar->bind_param('isssi', $idUsuario, $fechaActual, $comentarioCancelacion, $comentarioCancelacion, $citaId);
            $stmtCancelar->execute();
            $stmtCancelar->close();
        } elseif ($tablaSolicitudes instanceof mysqli_result) {
            $tablaSolicitudes->free();
        }
    }

    header('Location: index.php');
    exit;
}

$conn->close();
?>
