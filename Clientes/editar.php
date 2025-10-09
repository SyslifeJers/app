<?php
declare(strict_types=1);

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $nombre = isset($_POST['nombre']) ? trim((string) $_POST['nombre']) : '';
    $edad = isset($_POST['edade']) ? (int) $_POST['edade'] : 0;
    $FechaIngreso = isset($_POST['FechaIngreso']) ? (string) $_POST['FechaIngreso'] : '';
    $Observaciones = isset($_POST['Observaciones']) ? trim((string) $_POST['Observaciones']) : '';

    if ($id <= 0) {
        header('Location: index.php');
        exit;
    }

    require_once __DIR__ . '/../conexion.php';
    require_once __DIR__ . '/../Modulos/logger.php';
    $conn = conectar();

    $rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
    $puedeGestionarActivaciones = in_array($rolUsuario, [3, 5], true);

    $activo = null;
    if ($puedeGestionarActivaciones && isset($_POST['activoe'])) {
        $activo = ((int) $_POST['activoe'] === 1) ? 1 : 0;
    } else {
        $estadoStmt = $conn->prepare('SELECT activo FROM nino WHERE id = ?');
        $estadoStmt->bind_param('i', $id);
        $estadoStmt->execute();
        $estadoStmt->bind_result($activoActual);
        if ($estadoStmt->fetch()) {
            $activo = (int) $activoActual;
        }
        $estadoStmt->close();

        if ($activo === null) {
            $conn->close();
            header('Location: index.php');
            exit;
        }
    }

    $sql = 'UPDATE nino SET name = ?, edad = ?, activo = ?, Observacion = ?, FechaIngreso = ? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('siissi', $nombre, $edad, $activo, $Observaciones, $FechaIngreso, $id);

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
        header('Location:index.php');
        $stmt->close();
        $conn->close();
        exit;
    }

    $stmt->close();
    $conn->close();
    echo 'Error: ' . $conn->error;
}
?>