<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user'], $_SESSION['token'])) {
    header('Location: https://app.clinicacerene.com/login.php');
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Reportes/fpdf/fpdf.php';

date_default_timezone_set('America/Mexico_City');

function pdfTicketText(string $texto): string
{
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
}

function pdfTicketEstadoTexto(string $estado): string
{
    switch (strtolower(trim($estado))) {
        case 'abierto':
            return 'Abierto';
        case 'en_progreso':
            return 'En progreso';
        case 'resuelto':
            return 'Resuelto';
        case 'cerrado':
            return 'Cerrado';
        default:
            return $estado !== '' ? $estado : 'N/D';
    }
}

function pdfTicketFecha(?string $fecha): string
{
    if ($fecha === null || trim($fecha) === '') {
        return '';
    }

    try {
        return (new DateTime($fecha, new DateTimeZone('America/Mexico_City')))->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return $fecha;
    }
}

function pdfTicketPorcentaje(int $valor, int $total): string
{
    if ($total <= 0) {
        return '0%';
    }

    return number_format(($valor / $total) * 100, 1) . '%';
}

function pdfTicketRenderTitle(FPDF $pdf, string $title): void
{
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(238, 242, 255);
    $pdf->Cell(0, 8, pdfTicketText($title), 0, 1, 'L', true);
}

function pdfTicketRenderKeyValues(FPDF $pdf, array $items): void
{
    $pdf->SetFont('Arial', '', 9);
    foreach ($items as $label => $value) {
        $pdf->Cell(65, 7, pdfTicketText($label), 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 7, pdfTicketText((string) $value), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 9);
    }
}

function pdfTicketRenderSimpleRows(FPDF $pdf, array $rows, int $total): void
{
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(120, 7, pdfTicketText('Concepto'), 1, 0, 'L');
    $pdf->Cell(28, 7, pdfTicketText('Total'), 1, 0, 'C');
    $pdf->Cell(32, 7, pdfTicketText('%'), 1, 1, 'C');
    $pdf->SetFont('Arial', '', 8);

    if (empty($rows)) {
        $pdf->Cell(180, 7, pdfTicketText('Sin datos'), 1, 1, 'L');
        return;
    }

    foreach ($rows as $row) {
        $etiqueta = mb_substr((string) ($row['etiqueta'] ?? 'N/D'), 0, 58, 'UTF-8');
        $valor = (int) ($row['total'] ?? 0);
        $pdf->Cell(120, 7, pdfTicketText($etiqueta), 1, 0, 'L');
        $pdf->Cell(28, 7, (string) $valor, 1, 0, 'C');
        $pdf->Cell(32, 7, pdfTicketPorcentaje($valor, $total), 1, 1, 'C');
    }
}

$conn = conectar();
if (!($conn instanceof mysqli)) {
    exit('No fue posible conectar a la base de datos.');
}

$stmtToken = $conn->prepare('SELECT token FROM Usuarios WHERE user = ?');
if (!($stmtToken instanceof mysqli_stmt)) {
    exit('No fue posible validar la sesion.');
}
$stmtToken->bind_param('s', $_SESSION['user']);
$stmtToken->execute();
$stmtToken->store_result();
$stmtToken->bind_result($dbToken);
$stmtToken->fetch();
$stmtToken->close();

if (!isset($dbToken) || $_SESSION['token'] !== $dbToken) {
    header('Location: https://app.clinicacerene.com/login.php');
    exit;
}

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
$esAdminTickets = ($rolUsuario === 3);
$idUsuario = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;

$mesParam = isset($_GET['mes']) ? trim((string) $_GET['mes']) : date('Y-m');
$mesObj = DateTime::createFromFormat('Y-m-d', $mesParam . '-01', new DateTimeZone('America/Mexico_City'));
if (!$mesObj || $mesObj->format('Y-m') !== $mesParam) {
    $mesObj = new DateTime('first day of this month', new DateTimeZone('America/Mexico_City'));
    $mesParam = $mesObj->format('Y-m');
}

$inicioMes = $mesObj->format('Y-m-01 00:00:00');
$finMes = (clone $mesObj)->modify('last day of this month')->format('Y-m-d 23:59:59');
$tituloMes = $mesObj->format('m/Y');

$condiciones = ['t.created_at BETWEEN ? AND ?'];
$tipos = 'ss';
$parametros = [$inicioMes, $finMes];

if (!$esAdminTickets) {
    $condiciones[] = 't.creado_por = ?';
    $tipos .= 'i';
    $parametros[] = $idUsuario;
}

$where = implode(' AND ', $condiciones);
$resumen = [
    'total' => 0,
    'abierto' => 0,
    'en_progreso' => 0,
    'resuelto' => 0,
    'cerrado' => 0,
    'con_nino' => 0,
    'con_adjuntos' => 0,
];

$sqlResumen = "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN t.estado = 'abierto' THEN 1 ELSE 0 END) AS abierto,
        SUM(CASE WHEN t.estado = 'en_progreso' THEN 1 ELSE 0 END) AS en_progreso,
        SUM(CASE WHEN t.estado = 'resuelto' THEN 1 ELSE 0 END) AS resuelto,
        SUM(CASE WHEN t.estado = 'cerrado' THEN 1 ELSE 0 END) AS cerrado,
        SUM(CASE WHEN t.nino_id IS NOT NULL THEN 1 ELSE 0 END) AS con_nino,
        SUM(CASE WHEN adj.total_adjuntos > 0 THEN 1 ELSE 0 END) AS con_adjuntos
    FROM soporte_tickets t
    LEFT JOIN (
        SELECT ticket_id, COUNT(*) AS total_adjuntos
        FROM soporte_ticket_adjuntos
        GROUP BY ticket_id
    ) adj ON adj.ticket_id = t.id
    WHERE $where";
$stmtResumen = $conn->prepare($sqlResumen);
if ($stmtResumen instanceof mysqli_stmt) {
    $stmtResumen->bind_param($tipos, ...$parametros);
    $stmtResumen->execute();
    $rowResumen = $stmtResumen->get_result()->fetch_assoc();
    if ($rowResumen) {
        foreach ($resumen as $key => $value) {
            $resumen[$key] = isset($rowResumen[$key]) ? (int) $rowResumen[$key] : 0;
        }
    }
    $stmtResumen->close();
}

$grupos = [
    'estado' => [],
    'problema' => [],
    'area' => [],
    'usuario' => [],
    'dia' => [],
];

$consultas = [
    'estado' => "SELECT COALESCE(NULLIF(t.estado, ''), 'N/D') AS etiqueta, COUNT(*) AS total FROM soporte_tickets t WHERE $where GROUP BY etiqueta ORDER BY total DESC",
    'problema' => "SELECT COALESCE(NULLIF(t.problema_general, ''), 'Sin problema') AS etiqueta, COUNT(*) AS total FROM soporte_tickets t WHERE $where GROUP BY etiqueta ORDER BY total DESC",
    'area' => "SELECT COALESCE(NULLIF(t.area_problema, ''), 'Sin area') AS etiqueta, COUNT(*) AS total FROM soporte_tickets t WHERE $where GROUP BY etiqueta ORDER BY total DESC",
    'dia' => "SELECT DATE(t.created_at) AS etiqueta, COUNT(*) AS total FROM soporte_tickets t WHERE $where GROUP BY etiqueta ORDER BY etiqueta ASC",
];

foreach ($consultas as $nombreGrupo => $sqlGrupo) {
    $stmtGrupo = $conn->prepare($sqlGrupo);
    if (!($stmtGrupo instanceof mysqli_stmt)) {
        continue;
    }
    $stmtGrupo->bind_param($tipos, ...$parametros);
    $stmtGrupo->execute();
    $resultadoGrupo = $stmtGrupo->get_result();
    while ($row = $resultadoGrupo->fetch_assoc()) {
        $etiqueta = (string) ($row['etiqueta'] ?? 'N/D');
        if ($nombreGrupo === 'estado') {
            $etiqueta = pdfTicketEstadoTexto($etiqueta);
        }
        $grupos[$nombreGrupo][] = ['etiqueta' => $etiqueta, 'total' => (int) ($row['total'] ?? 0)];
    }
    $stmtGrupo->close();
}

if ($esAdminTickets) {
    $sqlUsuario = "SELECT COALESCE(NULLIF(u.name, ''), u.user, 'Sin usuario') AS etiqueta, COUNT(*) AS total
        FROM soporte_tickets t
        LEFT JOIN Usuarios u ON u.id = t.creado_por
        WHERE $where
        GROUP BY etiqueta
        ORDER BY total DESC";
    $stmtUsuario = $conn->prepare($sqlUsuario);
    if ($stmtUsuario instanceof mysqli_stmt) {
        $stmtUsuario->bind_param($tipos, ...$parametros);
        $stmtUsuario->execute();
        $resultadoUsuario = $stmtUsuario->get_result();
        while ($row = $resultadoUsuario->fetch_assoc()) {
            $grupos['usuario'][] = ['etiqueta' => (string) ($row['etiqueta'] ?? 'Sin usuario'), 'total' => (int) ($row['total'] ?? 0)];
        }
        $stmtUsuario->close();
    }
}

$sqlTickets = "SELECT t.id,
        t.estado,
        t.problema_general,
        t.area_problema,
        t.created_at,
        u.user AS creador_usuario,
        u.name AS creador_nombre,
        n.name AS nino_nombre,
        COALESCE(adj.total_adjuntos, 0) AS total_adjuntos,
        COALESCE(msg.total_mensajes, 0) AS total_mensajes
    FROM soporte_tickets t
    LEFT JOIN Usuarios u ON u.id = t.creado_por
    LEFT JOIN nino n ON n.id = t.nino_id
    LEFT JOIN (
        SELECT ticket_id, COUNT(*) AS total_adjuntos
        FROM soporte_ticket_adjuntos
        GROUP BY ticket_id
    ) adj ON adj.ticket_id = t.id
    LEFT JOIN (
        SELECT ticket_id, COUNT(*) AS total_mensajes
        FROM soporte_ticket_mensajes
        GROUP BY ticket_id
    ) msg ON msg.ticket_id = t.id
    WHERE $where
    ORDER BY t.created_at DESC, t.id DESC
    LIMIT 300";
$tickets = [];
$stmtTickets = $conn->prepare($sqlTickets);
if ($stmtTickets instanceof mysqli_stmt) {
    $stmtTickets->bind_param($tipos, ...$parametros);
    $stmtTickets->execute();
    $resultadoTickets = $stmtTickets->get_result();
    while ($row = $resultadoTickets->fetch_assoc()) {
        $tickets[] = $row;
    }
    $stmtTickets->close();
}

$pdf = new FPDF('P', 'mm', 'Letter');
$pdf->SetMargins(14, 12, 14);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();

$logo = __DIR__ . '/../logo.png';
if (is_file($logo)) {
    $pdf->Image($logo, 14, 10, 28);
}

$pdf->SetFont('Arial', 'B', 15);
$pdf->Cell(0, 8, pdfTicketText('Reporte mensual de tickets'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, pdfTicketText('Periodo: ' . $tituloMes), 0, 1, 'C');
$pdf->Cell(0, 6, pdfTicketText('Generado: ' . date('d/m/Y H:i')), 0, 1, 'C');

pdfTicketRenderTitle($pdf, 'Resumen general');
pdfTicketRenderKeyValues($pdf, [
    'Total de tickets' => (string) $resumen['total'],
    'Abiertos' => $resumen['abierto'] . ' (' . pdfTicketPorcentaje($resumen['abierto'], $resumen['total']) . ')',
    'En progreso' => $resumen['en_progreso'] . ' (' . pdfTicketPorcentaje($resumen['en_progreso'], $resumen['total']) . ')',
    'Resueltos' => $resumen['resuelto'] . ' (' . pdfTicketPorcentaje($resumen['resuelto'], $resumen['total']) . ')',
    'Cerrados' => $resumen['cerrado'] . ' (' . pdfTicketPorcentaje($resumen['cerrado'], $resumen['total']) . ')',
    'Con nino asociado' => (string) $resumen['con_nino'],
    'Con adjuntos' => (string) $resumen['con_adjuntos'],
]);

pdfTicketRenderTitle($pdf, 'Tickets por estado');
pdfTicketRenderSimpleRows($pdf, $grupos['estado'], $resumen['total']);

pdfTicketRenderTitle($pdf, 'Tickets por problema');
pdfTicketRenderSimpleRows($pdf, $grupos['problema'], $resumen['total']);

pdfTicketRenderTitle($pdf, 'Tickets por area');
pdfTicketRenderSimpleRows($pdf, $grupos['area'], $resumen['total']);

pdfTicketRenderTitle($pdf, $esAdminTickets ? 'Tickets por usuario' : 'Tickets por dia');
pdfTicketRenderSimpleRows($pdf, $esAdminTickets ? $grupos['usuario'] : $grupos['dia'], $resumen['total']);

$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, pdfTicketText('Detalle de tickets del mes'), 0, 1, 'L');
$pdf->SetFont('Arial', 'B', 7);
$pdf->SetFillColor(238, 242, 255);
$widths = $esAdminTickets ? [14, 24, 38, 30, 28, 28, 15, 15] : [14, 24, 45, 35, 32, 17, 17];
$headers = $esAdminTickets ? ['ID', 'Estado', 'Problema', 'Area', 'Nino', 'Usuario', 'Adj', 'Msg'] : ['ID', 'Estado', 'Problema', 'Area', 'Nino', 'Adj', 'Msg'];
foreach ($headers as $index => $header) {
    $pdf->Cell($widths[$index], 7, pdfTicketText($header), 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 7);
if (empty($tickets)) {
    $pdf->Cell(0, 7, pdfTicketText('Sin tickets en el mes seleccionado.'), 1, 1, 'L');
} else {
    foreach ($tickets as $ticket) {
        $creador = trim((string) ($ticket['creador_nombre'] ?? ''));
        if ($creador === '') {
            $creador = (string) ($ticket['creador_usuario'] ?? '');
        }
        $row = [
            '#' . (int) ($ticket['id'] ?? 0),
            pdfTicketEstadoTexto((string) ($ticket['estado'] ?? '')),
            mb_substr((string) ($ticket['problema_general'] ?? ''), 0, 24, 'UTF-8'),
            mb_substr((string) ($ticket['area_problema'] ?? ''), 0, 20, 'UTF-8'),
            mb_substr((string) ($ticket['nino_nombre'] ?? ''), 0, 18, 'UTF-8'),
        ];
        if ($esAdminTickets) {
            $row[] = mb_substr($creador, 0, 18, 'UTF-8');
        }
        $row[] = (string) (int) ($ticket['total_adjuntos'] ?? 0);
        $row[] = (string) (int) ($ticket['total_mensajes'] ?? 0);

        foreach ($row as $index => $value) {
            $align = in_array($headers[$index], ['ID', 'Adj', 'Msg'], true) ? 'C' : 'L';
            $pdf->Cell($widths[$index], 6, pdfTicketText((string) $value), 1, 0, $align);
        }
        $pdf->Ln();
    }
}

$conn->close();

$nombreArchivo = 'reporte_tickets_' . $mesParam . '.pdf';
$pdf->Output('I', $nombreArchivo);
