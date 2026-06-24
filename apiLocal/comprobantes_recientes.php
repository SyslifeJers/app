<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion.php';

function obtenerPagosRecientes(mysqli $conn, array $origenes): array
{
    if ($origenes === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($origenes), '?'));
    $types = str_repeat('s', count($origenes));

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
            WHERE pr.origen IN ($placeholders)
            ORDER BY pr.fecha_pago DESC, pr.id DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la consulta de comprobantes.');
    }

    $stmt->bind_param($types, ...$origenes);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $row['concepto'] = construirConceptoPago($row);
            $rows[] = $row;
        }
        $result->free();
    }

    $stmt->close();
    return $rows;
}

function construirConceptoPago(array $row): string
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

try {
    $conn = conectar();
    $conn->set_charset('utf8mb4');

    $data = [
        'citas' => obtenerPagosRecientes($conn, ['cita']),
        'sin_cita' => obtenerPagosRecientes($conn, ['sin_cita']),
        'diagnosticos' => obtenerPagosRecientes($conn, ['diagnostico', 'adeudo_diagnostico']),
    ];

    echo json_encode([
        'success' => true,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $conn->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
