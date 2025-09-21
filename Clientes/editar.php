<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $edad = $_POST['edade'];
    $activo = $_POST['activoe'];
    $FechaIngreso = $_POST['FechaIngreso'];
    $Observaciones = $_POST['Observaciones'];

    require_once __DIR__ . '/../conexion.php';
    require_once __DIR__ . '/../Modulos/logger.php';
    $conn = conectar();

    $sql = "UPDATE nino SET name = ?, edad = ?, activo = ?, Observacion = ?, FechaIngreso = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssss', $nombre, $edad, $activo, $Observaciones, $FechaIngreso, $id);
    if ($stmt->execute()) {
        registrarLog(
            $conn,
            $_SESSION['id'] ?? null,
            'pacientes',
            'actualizar',
            sprintf('Se actualizaron los datos del paciente #%s (%s).', $id, $nombre),
            'Paciente',
            (string) $id
        );
        echo "Elemento actualizado correctamente.";
        header("Location:index.php");
        die();
    } else {
        echo "Error: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}
?>