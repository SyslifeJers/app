<?php
require('fpdf/fpdf.php');
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../conexion.php';

function normalizarFechaReporte(string $fecha): string
{
    $fecha = trim($fecha);
    if ($fecha === '') {
        return '';
    }

    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return ($dt && $dt->format('Y-m-d') === $fecha) ? $fecha : '';
}

function safeText(string $texto): string
{
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
}

function renderKeyValueLines(FPDF $pdf, array $items): void
{
    $pdf->SetFont('Arial', '', 10);
    foreach ($items as $label => $value) {
        $pdf->Cell(0, 7, safeText($label . ': ' . $value), 0, 1, 'L');
    }
}

function renderSectionTitle(FPDF $pdf, string $title): void
{
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, safeText($title), 0, 1, 'L');
}

function renderSimpleTable(FPDF $pdf, array $headers, array $widths, array $rows, array $aligns = []): void
{
    $pdf->SetFont('Arial', 'B', 8);
    foreach ($headers as $index => $header) {
        $pdf->Cell($widths[$index], 8, safeText($header), 1, 0, 'C');
    }
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 8);
    foreach ($rows as $row) {
        foreach ($row as $index => $value) {
            $align = $aligns[$index] ?? 'L';
            $pdf->Cell($widths[$index], 7, safeText((string) $value), 1, 0, $align);
        }
        $pdf->Ln();
    }
}

$conn = conectar();
if (!($conn instanceof mysqli)) {
    exit('No fue posible conectar a la base de datos.');
}

$fecha_inicio = normalizarFechaReporte((string) ($_GET['fecha_inicio'] ?? ''));
$fecha_fin = normalizarFechaReporte((string) ($_GET['fecha_fin'] ?? ''));
$tipoPid = isset($_GET['tipoPid']) ? (int) $_GET['tipoPid'] : 0;
$idNino = isset($_GET['idNino']) ? (int) $_GET['idNino'] : 0;

if ($fecha_inicio === '' || $fecha_fin === '') {
    $hoy = date('Y-m-d');
    $fecha_inicio = $hoy;
    $fecha_fin = $hoy;
}

if ($fecha_inicio > $fecha_fin) {
    [$fecha_inicio, $fecha_fin] = [$fecha_fin, $fecha_inicio];
}

$condicionesPagos = ['pr.fecha_corte BETWEEN ? AND ?'];
$tiposPagos = 'ss';
$parametrosPagos = [$fecha_inicio, $fecha_fin];

if ($tipoPid > 0) {
    $condicionesPagos[] = 'pr.psicologo_id = ?';
    $tiposPagos .= 'i';
    $parametrosPagos[] = $tipoPid;
}

if ($idNino > 0) {
    $condicionesPagos[] = 'pr.paciente_id = ?';
    $tiposPagos .= 'i';
    $parametrosPagos[] = $idNino;
}

$sqlPagos = "SELECT
                pr.id,
                pr.fecha_pago,
                pr.fecha_corte,
                pr.origen,
                pr.referencia_id,
                pr.paciente_nombre,
                pr.psicologo_nombre,
                pr.metodo_pago,
                pr.monto,
                pr.observaciones,
                pr.cita_id,
                pr.diagnostico_id,
                pr.adeudo_id,
                CASE
                    WHEN pr.cita_id IS NOT NULL THEN COALESCE(ci.costo, 0)
                    WHEN pr.diagnostico_id IS NOT NULL THEN COALESCE(d.total, 0)
                    WHEN pr.adeudo_id IS NOT NULL THEN COALESCE(ad.total, 0)
                    ELSE 0
                END AS costo_servicio
            FROM PagoResumenDiario pr
            LEFT JOIN Cita ci ON ci.id = pr.cita_id
            LEFT JOIN Diagnosticos d ON d.id = pr.diagnostico_id
            LEFT JOIN AdeudosDiagnostico ad ON ad.id = pr.adeudo_id
            WHERE " . implode(' AND ', $condicionesPagos) . ' ORDER BY pr.fecha_pago ASC, pr.id ASC';
$stmtPagos = $conn->prepare($sqlPagos);
if (!($stmtPagos instanceof mysqli_stmt)) {
    exit('No fue posible preparar la consulta del reporte.');
}

$stmtPagos->bind_param($tiposPagos, ...$parametrosPagos);
$stmtPagos->execute();
$movimientos = $stmtPagos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPagos->close();

$condicionesCitas = ['ci.Estatus = 4', 'DATE(ci.Programado) BETWEEN ? AND ?'];
$tiposCitas = 'ss';
$parametrosCitas = [$fecha_inicio, $fecha_fin];

if ($tipoPid > 0) {
    $condicionesCitas[] = 'ci.IdUsuario = ?';
    $tiposCitas .= 'i';
    $parametrosCitas[] = $tipoPid;
}

if ($idNino > 0) {
    $condicionesCitas[] = 'ci.IdNino = ?';
    $tiposCitas .= 'i';
    $parametrosCitas[] = $idNino;
}

$sqlCitas = 'SELECT COUNT(*) AS total_citas, COALESCE(SUM(ci.costo), 0) AS total_costos FROM Cita ci WHERE ' . implode(' AND ', $condicionesCitas);
$stmtCitas = $conn->prepare($sqlCitas);
$stmtCitas->bind_param($tiposCitas, ...$parametrosCitas);
$stmtCitas->execute();
$resumenCitas = $stmtCitas->get_result()->fetch_assoc() ?: ['total_citas' => 0, 'total_costos' => 0];
$stmtCitas->close();

$stmtInicial = $conn->prepare('SELECT COALESCE(SUM(efectivo_inicial), 0) AS total_inicial FROM CorteCaja WHERE fecha BETWEEN ? AND ?');
$stmtInicial->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmtInicial->execute();
$totalEfectivoInicial = (float) (($stmtInicial->get_result()->fetch_assoc()['total_inicial'] ?? 0));
$stmtInicial->close();

$totalRecibido = 0.0;
$totalMovimientos = count($movimientos);
$resumenMetodo = [];
$resumenPsicologo = [];
$resumenOrigen = [];

foreach ($movimientos as $movimiento) {
    $monto = (float) ($movimiento['monto'] ?? 0);
    $metodo = trim((string) ($movimiento['metodo_pago'] ?? 'Sin metodo'));
    $psicologo = trim((string) ($movimiento['psicologo_nombre'] ?? 'Sin asignar'));
    $origen = trim((string) ($movimiento['origen'] ?? 'sin_origen'));

    if ($metodo === '') {
        $metodo = 'Sin metodo';
    }
    if ($psicologo === '') {
        $psicologo = 'Sin asignar';
    }
    if ($origen === '') {
        $origen = 'sin_origen';
    }

    if (!isset($resumenMetodo[$metodo])) {
        $resumenMetodo[$metodo] = 0.0;
    }
    if (!isset($resumenPsicologo[$psicologo])) {
        $resumenPsicologo[$psicologo] = 0;
    }
    if (!isset($resumenOrigen[$origen])) {
        $resumenOrigen[$origen] = 0.0;
    }

    $resumenMetodo[$metodo] += $monto;
    $resumenPsicologo[$psicologo]++;
    $resumenOrigen[$origen] += $monto;
    $totalRecibido += $monto;
}

arsort($resumenMetodo);
arsort($resumenPsicologo);
arsort($resumenOrigen);

$totalEfectivoRecibido = $resumenMetodo['Efectivo'] ?? 0.0;
$totalEfectivoEnCaja = $totalEfectivoInicial + $totalEfectivoRecibido;

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 12);

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, safeText('Reporte de flujo de dinero'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, safeText('Periodo: ' . $fecha_inicio . ' al ' . $fecha_fin), 0, 1, 'C');

renderSectionTitle($pdf, 'Resumen general');
renderKeyValueLines($pdf, [
    'Total recibido' => '$' . number_format($totalRecibido, 2),
    'Movimientos de pago' => (string) $totalMovimientos,
    'Citas finalizadas' => (string) ((int) ($resumenCitas['total_citas'] ?? 0)),
    'Total valor de citas' => '$' . number_format((float) ($resumenCitas['total_costos'] ?? 0), 2),
    'Efectivo inicial' => '$' . number_format($totalEfectivoInicial, 2),
    'Efectivo recibido' => '$' . number_format($totalEfectivoRecibido, 2),
    'Efectivo estimado en caja' => '$' . number_format($totalEfectivoEnCaja, 2),
]);

renderSectionTitle($pdf, 'Ingreso por metodo');
$rowsMetodo = [];
foreach ($resumenMetodo as $metodo => $monto) {
    $rowsMetodo[] = [$metodo, '$' . number_format($monto, 2)];
}
if ($rowsMetodo === []) {
    $rowsMetodo[] = ['Sin movimientos', '$0.00'];
}
renderSimpleTable($pdf, ['Metodo', 'Monto'], [110, 60], $rowsMetodo, ['L', 'R']);

renderSectionTitle($pdf, 'Ingreso por origen');
$rowsOrigen = [];
foreach ($resumenOrigen as $origen => $monto) {
    $rowsOrigen[] = [$origen, '$' . number_format($monto, 2)];
}
if ($rowsOrigen === []) {
    $rowsOrigen[] = ['Sin movimientos', '$0.00'];
}
renderSimpleTable($pdf, ['Origen', 'Monto'], [110, 60], $rowsOrigen, ['L', 'R']);

renderSectionTitle($pdf, 'Ingreso por psicologo');
$rowsPsicologo = [];
foreach ($resumenPsicologo as $psicologo => $numeroCitas) {
    $rowsPsicologo[] = [$psicologo, (string) $numeroCitas];
}
if ($rowsPsicologo === []) {
    $rowsPsicologo[] = ['Sin movimientos', '0'];
}
renderSimpleTable($pdf, ['Psicologo', 'Numero de citas'], [110, 60], $rowsPsicologo, ['L', 'C']);

renderSectionTitle($pdf, 'Detalle de movimientos');
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(20, 8, 'Fecha', 1, 0, 'C');
$pdf->Cell(34, 8, 'Paciente', 1, 0, 'C');
$pdf->Cell(32, 8, 'Psicologo', 1, 0, 'C');
$pdf->Cell(22, 8, 'Origen', 1, 0, 'C');
$pdf->Cell(22, 8, 'Metodo', 1, 0, 'C');
$pdf->Cell(20, 8, 'Costo serv.', 1, 0, 'C');
$pdf->Cell(20, 8, 'Recibido', 1, 1, 'C');

$pdf->SetFont('Arial', '', 7);
if ($movimientos === []) {
    $pdf->Cell(170, 8, safeText('No hay movimientos en el periodo seleccionado.'), 1, 1, 'C');
} else {
    foreach ($movimientos as $movimiento) {
        $fechaPago = (string) ($movimiento['fecha_pago'] ?? '');
        $fechaPago = $fechaPago !== '' ? substr($fechaPago, 0, 16) : '';
        $paciente = mb_strimwidth((string) ($movimiento['paciente_nombre'] ?? ''), 0, 18, '...');
        $psicologo = mb_strimwidth((string) ($movimiento['psicologo_nombre'] ?? 'Sin asignar'), 0, 17, '...');
        $origen = (string) ($movimiento['origen'] ?? '') . ' #' . (int) ($movimiento['referencia_id'] ?? 0);
        $origen = mb_strimwidth($origen, 0, 13, '...');
        $metodo = mb_strimwidth((string) ($movimiento['metodo_pago'] ?? ''), 0, 12, '...');
        $costoServicio = '$' . number_format((float) ($movimiento['costo_servicio'] ?? 0), 2);
        $monto = '$' . number_format((float) ($movimiento['monto'] ?? 0), 2);

        $pdf->Cell(20, 7, safeText($fechaPago), 1, 0, 'L');
        $pdf->Cell(34, 7, safeText($paciente), 1, 0, 'L');
        $pdf->Cell(32, 7, safeText($psicologo), 1, 0, 'L');
        $pdf->Cell(22, 7, safeText($origen), 1, 0, 'L');
        $pdf->Cell(22, 7, safeText($metodo), 1, 0, 'L');
        $pdf->Cell(20, 7, safeText($costoServicio), 1, 0, 'R');
        $pdf->Cell(20, 7, safeText($monto), 1, 1, 'R');
    }
}

$pdf->Output();
$conn->close();
