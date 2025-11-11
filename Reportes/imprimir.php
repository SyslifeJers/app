<?php
require('fpdf/fpdf.php');
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../conexion.php';
$conn = conectar();

$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$tipoPid = isset($_GET['tipoPid']) ? $_GET['tipoPid'] : '';
$idNino = isset($_GET['idNino']) ? $_GET['idNino'] : '';

if ($tipoPid !== '') {
    $tipoPid = (int) $tipoPid;
    if ($tipoPid <= 0) {
        $tipoPid = '';
    }
}

if ($idNino !== '') {
    $idNino = (int) $idNino;
    if ($idNino <= 0) {
        $idNino = '';
    }
}
// Consulta SQL para la primera tabla
$sql = "SELECT ci.id,
               n.name,
               us.name as Psicologo,
               ci.costo,
               ci.Programado,
               DATE(ci.Programado) as Fecha,
               TIME(ci.Programado) as Hora,
               ci.Tipo,
               es.name as Estatus,
               ci.FormaPago
        FROM Cita ci
        INNER JOIN nino n ON n.id = ci.IdNino
        INNER JOIN Usuarios us ON us.id = ci.IdUsuario
        INNER JOIN Estatus es ON es.id = ci.Estatus
        WHERE ci.Estatus = 4 AND DATE(ci.Programado) BETWEEN ? AND ?";
if (!empty($tipoPid)) {
    $sql .= " AND ci.IdUsuario = ?";
}
if (!empty($idNino)) {
    $sql .= " AND ci.IdNino = ?";
}
$sql .= " ORDER BY us.name, ci.Programado ASC";
$stmt = $conn->prepare($sql);
if (!empty($tipoPid) && !empty($idNino)) {
    $stmt->bind_param('ssii', $fecha_inicio, $fecha_fin, $tipoPid, $idNino);
} elseif (!empty($tipoPid)) {
    $stmt->bind_param('ssi', $fecha_inicio, $fecha_fin, $tipoPid);
} elseif (!empty($idNino)) {
    $stmt->bind_param('ssi', $fecha_inicio, $fecha_fin, $idNino);
} else {
    $stmt->bind_param('ss', $fecha_inicio, $fecha_fin);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calcular total de citas y total de costos
$totalCitas = count($rows);
$totalCosto = array_sum(array_column($rows, 'costo'));

// Consulta para el resumen por forma de pago
$sql_summary2 = "SELECT SUM(ci.costo) as TotalCosto,
                        COUNT(ci.id) as NumeroCitas,
                        ci.FormaPago
                 FROM Cita ci
                 WHERE ci.Estatus = 4 AND DATE(ci.Programado) BETWEEN ? AND ?";
if (!empty($tipoPid)) {
    $sql_summary2 .= " AND ci.IdUsuario = ?";
}
if (!empty($idNino)) {
    $sql_summary2 .= " AND ci.IdNino = ?";
}
$sql_summary2 .= " GROUP BY ci.FormaPago";
$stmt_summary2 = $conn->prepare($sql_summary2);
if (!empty($tipoPid) && !empty($idNino)) {
    $stmt_summary2->bind_param('ssii', $fecha_inicio, $fecha_fin, $tipoPid, $idNino);
} elseif (!empty($tipoPid)) {
    $stmt_summary2->bind_param('ssi', $fecha_inicio, $fecha_fin, $tipoPid);
} elseif (!empty($idNino)) {
    $stmt_summary2->bind_param('ssi', $fecha_inicio, $fecha_fin, $idNino);
} else {
    $stmt_summary2->bind_param('ss', $fecha_inicio, $fecha_fin);
}
$stmt_summary2->execute();
$summary2_rows = $stmt_summary2->get_result()->fetch_all(MYSQLI_ASSOC);

$totalEfectivoInicial = 0.0;
if ($fecha_inicio !== '' && $fecha_fin !== '') {
    $stmtEfectivoInicial = $conn->prepare('SELECT COALESCE(SUM(efectivo_inicial), 0) AS total_inicial FROM CorteCaja WHERE fecha BETWEEN ? AND ?');
    if ($stmtEfectivoInicial instanceof mysqli_stmt) {
        $stmtEfectivoInicial->bind_param('ss', $fecha_inicio, $fecha_fin);
        $stmtEfectivoInicial->execute();
        $stmtEfectivoInicial->bind_result($totalInicialConsulta);
        if ($stmtEfectivoInicial->fetch()) {
            $totalEfectivoInicial = (float) $totalInicialConsulta;
        }
        $stmtEfectivoInicial->close();
    }
}

$consultaEfectivo = "SELECT COALESCE(SUM(cp.monto), 0) AS total_efectivo
                     FROM CitaPagos cp
                     INNER JOIN Cita ci ON ci.id = cp.cita_id
                     WHERE ci.Estatus = 4";
$tiposEfectivo = '';
$parametrosEfectivo = [];

if ($fecha_inicio !== '' && $fecha_fin !== '') {
    $consultaEfectivo .= ' AND DATE(ci.Programado) BETWEEN ? AND ?';
    $tiposEfectivo .= 'ss';
    $parametrosEfectivo[] = $fecha_inicio;
    $parametrosEfectivo[] = $fecha_fin;
}

if (!empty($tipoPid)) {
    $consultaEfectivo .= ' AND ci.IdUsuario = ?';
    $tiposEfectivo .= 'i';
    $parametrosEfectivo[] = $tipoPid;
}

if (!empty($idNino)) {
    $consultaEfectivo .= ' AND ci.IdNino = ?';
    $tiposEfectivo .= 'i';
    $parametrosEfectivo[] = $idNino;
}

$consultaEfectivo .= ' AND LOWER(cp.metodo) = ?';
$tiposEfectivo .= 's';
$parametrosEfectivo[] = 'efectivo';

$totalEfectivoPagado = 0.0;
$stmtEfectivo = $conn->prepare($consultaEfectivo);
if ($stmtEfectivo instanceof mysqli_stmt) {
    $stmtEfectivo->bind_param($tiposEfectivo, ...$parametrosEfectivo);
    $stmtEfectivo->execute();
    $stmtEfectivo->bind_result($totalEfectivoConsulta);
    if ($stmtEfectivo->fetch()) {
        $totalEfectivoPagado = (float) $totalEfectivoConsulta;
    }
    $stmtEfectivo->close();
}

$totalEfectivoEnCaja = $totalEfectivoInicial + $totalEfectivoPagado;

if ($totalCitas > 0) {
    // Creación del PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);

    // Título del reporte
    $pdf->Cell(0, 10, 'Reporte ' . $fecha_inicio . ' - ' . $fecha_fin, 0, 1, 'C');

    // Total de citas y total de costos
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Total de citas: ' . $totalCitas, 0, 1, 'L');
    $pdf->Cell(0, 10, 'Total costos: ' . number_format($totalCosto, 2), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Efectivo inicial registrado: ' . number_format($totalEfectivoInicial, 2), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Efectivo cobrado en citas: ' . number_format($totalEfectivoPagado, 2), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Efectivo estimado en caja: ' . number_format($totalEfectivoEnCaja, 2), 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont('Arial','B',8);
    foreach ($summary2_rows as $row) {
        $formaPago = $row['FormaPago'] ? $row['FormaPago'] : 'No asignado';
        $pdf->Cell(0, 6, "Forma de Pago: " . htmlspecialchars($formaPago) . " ($ " . number_format($row['TotalCosto'], 2).")" , 0, 1, 'L');
    }
    $pdf->SetFont('Arial','B',8);

    // Títulos de columnas para la primera tabla
    $pdf->Cell(10,10,'ID',1);
    $pdf->Cell(40,10,'Nombre',1);
    $pdf->Cell(40,10,'Psicologo',1);
    $pdf->Cell(12,10,'Costo',1);
    $pdf->Cell(28,10,'Fecha de cita',1);
    $pdf->Cell(22,10,'Forma de Pago',1);
    $pdf->Cell(20,10,'Tipo',1);
    $pdf->Ln();

    // Filas de datos para la primera tabla
    foreach ($rows as $row) {
        $pdf->Cell(10,10,$row['id'],1);
        $pdf->Cell(40,10,mb_strimwidth($row['name'], 0, 22, "..."),1);
        $pdf->Cell(40,10,$row['Psicologo'],1);
        $pdf->Cell(12,10,$row['costo'],1);
        $pdf->Cell(28,10,$row['Programado'],1);
        $pdf->Cell(22,10,$row['FormaPago'],1);
        $pdf->Cell(20,10,$row['Tipo'],1);
        $pdf->Ln();
    }
    if (empty($tipoPid)) {
            // Nueva consulta SQL para la segunda tabla
    $sql2 = "SELECT usu.name,
                    SUM(ci.costo) as TotalCosto,
                    COUNT(ci.id) as NumeroCitas
             FROM Cita ci
             INNER JOIN Usuarios usu ON usu.id = ci.IdUsuario
             WHERE ci.Estatus = 4 AND DATE(ci.Programado) BETWEEN ? AND ?
             GROUP BY IdUsuario";

    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt2->execute();
    $rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

    if (count($rows2) > 0) {
        // Añadir nueva página
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);

        // Título del reporte para la segunda tabla
        $pdf->Cell(0, 10, iconv('ISO-8859-1','UTF-8','Resumen por Usuario ' . $fecha_inicio . ' - ' . $fecha_fin), 0, 1, 'C');

        $pdf->SetFont('Arial', 'B', 12);

        // Títulos de columnas para la segunda tabla
        $pdf->Cell(60,10,'Psicologo',1);
        $pdf->Cell(40,10,'Total Costo',1);
        $pdf->Cell(40,10,'Numero de Citas',1);
        $pdf->Ln();

        // Filas de datos para la segunda tabla
        foreach ($rows2 as $row2) {
            $pdf->Cell(60,10,$row2['name'],1);
            $pdf->Cell(40,10,number_format($row2['TotalCosto'], 2),1);
            $pdf->Cell(40,10,$row2['NumeroCitas'],1);
            $pdf->Ln();
        }
    }
    }


    $pdf->Output();
} else {
    echo "No hay resultados";
}

$conn->close();
?>
