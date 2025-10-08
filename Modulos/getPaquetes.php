<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

$conn = conectar();
$conn->set_charset('utf8');

$paquetes = [];
$sql = "SELECT id, nombre, primer_pago_monto, saldo_adicional FROM Paquetes WHERE activo = 1 ORDER BY nombre";
if ($resultado = $conn->query($sql)) {
    while ($fila = $resultado->fetch_assoc()) {
        $paquetes[] = [
            'id' => (int) $fila['id'],
            'nombre' => $fila['nombre'],
            'primer_pago_monto' => (float) $fila['primer_pago_monto'],
            'saldo_adicional' => (float) $fila['saldo_adicional'],
        ];
    }
    $resultado->free();
}

$conn->close();

echo json_encode($paquetes, JSON_UNESCAPED_UNICODE);
