<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

function construirConceptoComprobante(array $row): string
{
    $origen = (string) ($row['origen'] ?? '');
    if ($origen === 'cita') {
        $citaId = isset($row['cita_id']) && $row['cita_id'] !== null ? (int) $row['cita_id'] : 0;
        return $citaId > 0 ? 'Cita / paquete #' . $citaId : 'Cita / paquete';
    }
    if ($origen === 'sin_cita') {
        return 'Pago sin cita';
    }
    if ($origen === 'diagnostico') {
        $diagnosticoId = isset($row['diagnostico_id']) && $row['diagnostico_id'] !== null ? (int) $row['diagnostico_id'] : (int) ($row['referencia_id'] ?? 0);
        return $diagnosticoId > 0 ? 'Diagnostico #' . $diagnosticoId : 'Diagnostico';
    }
    if ($origen === 'adeudo_diagnostico') {
        $adeudoId = isset($row['adeudo_id']) && $row['adeudo_id'] !== null ? (int) $row['adeudo_id'] : (int) ($row['referencia_id'] ?? 0);
        return $adeudoId > 0 ? 'Adeudo diagnostico #' . $adeudoId : 'Adeudo diagnostico';
    }
    return $origen !== '' ? $origen : 'Pago';
}

$pagoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($pagoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de pago no valido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = conectar();
    $conn->set_charset('utf8mb4');

    $sql = "SELECT
                pr.id,
                pr.origen,
                pr.referencia_id,
                pr.cita_id,
                pr.diagnostico_id,
                pr.adeudo_id,
                pr.paciente_id,
                pr.paciente_nombre,
                pr.psicologo_id,
                pr.psicologo_nombre,
                pr.monto,
                pr.metodo_pago,
                pr.fecha_pago,
                pr.observaciones,
                COALESCE(u.name, '') AS registrado_por_nombre
            FROM PagoResumenDiario pr
            LEFT JOIN Usuarios u ON u.id = pr.registrado_por
            WHERE pr.id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta del comprobante.');
    }

    $stmt->bind_param('i', $pagoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    if (!is_array($row)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Comprobante no encontrado.'], JSON_UNESCAPED_UNICODE);
        $conn->close();
        exit;
    }

    $row['concepto'] = construirConceptoComprobante($row);

    echo json_encode([
        'success' => true,
        'comprobante' => $row,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $conn->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
