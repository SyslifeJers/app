<?php
require('fpdf/fpdf.php');
require_once __DIR__ . '/../conexion.php';

function convertText($text)
{
    if (!function_exists('iconv')) {
        return (string) $text;
    }

    $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) $text);
    if ($converted === false) {
        return (string) $text;
    }

    return $converted;
}

$idNino = isset($_GET['idNino']) ? (int) $_GET['idNino'] : 0;

if ($idNino <= 0) {
    http_response_code(400);
    echo 'Id de niño inválido.';
    exit;
}

$conn = conectar();

$infoSql = "SELECT n.name AS nino, cli.name AS tutor
            FROM nino n
            LEFT JOIN Clientes cli ON cli.id = n.idtutor
            WHERE n.id = ?";
$infoStmt = $conn->prepare($infoSql);
$infoStmt->bind_param('i', $idNino);
$infoStmt->execute();
$infoResult = $infoStmt->get_result();
$info = $infoResult->fetch_assoc();
$infoStmt->close();

if (!$info) {
    http_response_code(404);
    echo 'No se encontró la información del niño solicitado.';
    $conn->close();
    exit;
}

$citasSql = "SELECT ci.id,
                    ci.Programado,
                    DATE(ci.Programado) AS Fecha,
                    TIME(ci.Programado) AS Hora,
                    ci.costo,
                    ci.Tipo,
                    ci.FormaPago,
                    es.name AS Estatus,
                    us.name AS Psicologo
             FROM Cita ci
             LEFT JOIN Estatus es ON es.id = ci.Estatus
             LEFT JOIN Usuarios us ON us.id = ci.IdUsuario
             WHERE ci.IdNino = ?
             ORDER BY ci.Programado ASC";
$citasStmt = $conn->prepare($citasSql);
$citasStmt->bind_param('i', $idNino);
$citasStmt->execute();
$citas = $citasStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$citasStmt->close();
$conn->close();

if (empty($citas)) {
    http_response_code(404);
    echo 'No hay citas registradas para el niño seleccionado.';
    exit;
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);

$nombreNino = convertText('Niño: ' . $info['nino']);
$tutor = convertText('Tutor: ' . ($info['tutor'] ?? 'No registrado'));
$pdf->Cell(0, 10, convertText('Histórico de citas'), 0, 1, 'C');
$pdf->Ln(2);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, $nombreNino, 0, 1, 'L');
$pdf->Cell(0, 8, $tutor, 0, 1, 'L');
$pdf->Ln(4);

$totalCitas = count($citas);
$totalCosto = array_sum(array_map('floatval', array_column($citas, 'costo')));
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, convertText('Total de citas: ' . $totalCitas), 0, 1, 'L');
$pdf->Cell(0, 8, convertText('Total cobrado: $' . number_format($totalCosto, 2)), 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(10, 8, 'ID', 1);
$pdf->Cell(26, 8, convertText('Fecha'), 1);
$pdf->Cell(16, 8, convertText('Hora'), 1);
$pdf->Cell(40, 8, convertText('Psicologo'), 1);
$pdf->Cell(18, 8, convertText('Costo'), 1);
$pdf->Cell(26, 8, convertText('Tipo'), 1);
$pdf->Cell(26, 8, convertText('Estatus'), 1);
$pdf->Cell(28, 8, convertText('Forma de pago'), 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 8);
foreach ($citas as $cita) {
    $psicologo = $cita['Psicologo'] ?? 'Sin asignar';
    if (function_exists('mb_strimwidth')) {
        $psicologo = mb_strimwidth($psicologo, 0, 18, '...');
    } else {
        if (strlen($psicologo) > 18) {
            $psicologo = substr($psicologo, 0, 15) . '...';
        }
    }

    $formaPago = $cita['FormaPago'] ?? 'No definido';

    $pdf->Cell(10, 8, $cita['id'], 1);
    $pdf->Cell(26, 8, convertText($cita['Fecha']), 1);
    $pdf->Cell(16, 8, convertText($cita['Hora']), 1);
    $pdf->Cell(40, 8, convertText($psicologo), 1);
    $pdf->Cell(18, 8, convertText('$' . number_format((float) $cita['costo'], 2)), 1);
    $pdf->Cell(26, 8, convertText($cita['Tipo'] ?? 'No definido'), 1);
    $pdf->Cell(26, 8, convertText($cita['Estatus'] ?? 'No definido'), 1);
    $pdf->Cell(28, 8, convertText($formaPago ?: 'No definido'), 1);
    $pdf->Ln();
}

$archivo = 'citas_nino_' . $idNino . '.pdf';
$pdf->Output('D', $archivo);
exit;
?>
