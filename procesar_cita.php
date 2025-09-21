<?php
session_start();
header('Content-Type: application/json');
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $required = ['sendIdCliente', 'sendIdPsicologo', 'resumenTipo', 'resumenFecha', 'resumenCosto'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Falta el campo: $field"]);
            exit;
        }
    }

    require_once 'conexion.php';
    require_once __DIR__ . '/Modulos/logger.php';
    $conn = conectar();
    $conn->set_charset('utf8');

    $sql = "INSERT INTO Cita (id, IdNino, IdUsuario, idGenerado, fecha, costo, Programado, Estatus, Tipo)
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)";

    $idCliente = $_POST['sendIdCliente'];
    $idPsicologo = $_POST['sendIdPsicologo'];
    $tipo = $_POST['resumenTipo'];
    $fechaCita = $_POST['resumenFecha'];
    $idGenerado = $_SESSION['id'];
    $estatus = 2;
    $fechaActual = date('Y-m-d H:i:s');
    $costo = $_POST['resumenCosto'];

    // Revisión rápida: evitar duplicados con misma fecha y usuario
    $check = $conn->prepare("SELECT COUNT(*) FROM Cita WHERE IdNino = ? AND Programado = ?");
    $check->bind_param('is', $idCliente, $fechaCita);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya existe una cita registrada para este paciente en esa fecha y hora.']);
        exit;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiisdsis', $idCliente, $idPsicologo, $idGenerado, $fechaActual, $costo, $fechaCita, $estatus, $tipo);
    $stmt->execute();
    $nuevaCitaId = $conn->insert_id;
    $stmt->close();

    registrarLog(
        $conn,
        $idGenerado,
        'citas',
        'crear',
        sprintf(
            'Se creó la cita #%d para el paciente %d con el psicólogo %d programada el %s.',
            $nuevaCitaId,
            $idCliente,
            $idPsicologo,
            $fechaCita
        ),
        'Cita',
        (string) $nuevaCitaId
    );

    echo json_encode(['success' => true]);
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
