<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: paquetes.php');
    exit;
}

$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$primerPago = isset($_POST['primer_pago']) ? (float) $_POST['primer_pago'] : null;
$saldoAdicional = isset($_POST['saldo_adicional']) ? (float) $_POST['saldo_adicional'] : null;

if ($nombre === '' || $primerPago === null || $saldoAdicional === null || $primerPago < 0 || $saldoAdicional < 0) {
    $_SESSION['paquetes_mensaje'] = 'Proporciona un nombre y montos válidos para crear el paquete.';
    $_SESSION['paquetes_tipo'] = 'danger';
    header('Location: paquetes.php');
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/logger.php';

$conn = conectar();
$conn->set_charset('utf8');

$stmt = $conn->prepare('INSERT INTO Paquetes (nombre, primer_pago_monto, saldo_adicional, activo) VALUES (?, ?, ?, 1)');
if ($stmt === false) {
    $_SESSION['paquetes_mensaje'] = 'No fue posible preparar el registro del paquete.';
    $_SESSION['paquetes_tipo'] = 'danger';
    header('Location: paquetes.php');
    exit;
}

$stmt->bind_param('sdd', $nombre, $primerPago, $saldoAdicional);

if ($stmt->execute()) {
    $_SESSION['paquetes_mensaje'] = 'Paquete creado correctamente.';
    $_SESSION['paquetes_tipo'] = 'success';

    registrarLog(
        $conn,
        $_SESSION['id'] ?? null,
        'paquetes',
        'crear',
        sprintf('Se creó el paquete "%s" con primer pago de %.2f y saldo adicional de %.2f.', $nombre, $primerPago, $saldoAdicional),
        'Paquete',
        (string) $conn->insert_id
    );
} else {
    $_SESSION['paquetes_mensaje'] = 'Ocurrió un error al guardar el paquete: ' . $stmt->error;
    $_SESSION['paquetes_tipo'] = 'danger';
}

$stmt->close();
$conn->close();

header('Location: paquetes.php');
exit;
