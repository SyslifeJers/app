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

    $host = 'localhost';
    $db = 'clini234_cerene';
    $user = 'clini234_cerene';
    $pass = 'tu{]ScpQ-Vcg';

    try {
        $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("set names utf8mb3");

        $sql = "INSERT INTO Cita (id, IdNino, IdUsuario, idGenerado, fecha, costo, Programado, Estatus, Tipo) 
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

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
        $check->execute([$idCliente, $fechaCita]);
        if ($check->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Ya existe una cita registrada para este paciente en esa fecha y hora.']);
            exit;
        }

        $stmt->execute([$idCliente, $idPsicologo, $idGenerado, $fechaActual, $costo, $fechaCita, $estatus, $tipo]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
