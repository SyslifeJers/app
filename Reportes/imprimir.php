<?php
require('fpdf/fpdf.php');
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

// Conexión a la base de datos usando PDO y configurando UTF-8
$db_host = 'localhost';
$db_name = 'clini234_cerene';
$db_user = 'clini234_cerene';
$db_pass = 'tu{]ScpQ-Vcg';

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Conexión fallida: " . $e->getMessage());
}

$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

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
        WHERE ci.Estatus = 4 AND DATE(ci.Programado) BETWEEN :fecha_inicio AND :fecha_fin
        ORDER BY us.name, ci.Programado ASC";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
$stmt->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular total de citas y total de costos
$totalCitas = count($rows);
$totalCosto = array_sum(array_column($rows, 'costo'));

// Consulta para el resumen por forma de pago
$sql_summary2 = "SELECT SUM(ci.costo) as TotalCosto, 
                        COUNT(ci.id) as NumeroCitas, 
                        ci.FormaPago
                 FROM Cita ci
                 WHERE ci.Estatus = 4 AND DATE(ci.Programado) BETWEEN :fecha_inicio AND :fecha_fin
                 GROUP BY ci.FormaPago";

$stmt_summary2 = $conn->prepare($sql_summary2);
$stmt_summary2->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
$stmt_summary2->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
$stmt_summary2->execute();
$summary2_rows = $stmt_summary2->fetchAll(PDO::FETCH_ASSOC);

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
        $pdf->Cell(40,10,$row['name'],1);
        $pdf->Cell(40,10,$row['Psicologo'],1);
        $pdf->Cell(12,10,$row['costo'],1);
        $pdf->Cell(28,10,$row['Programado'],1);
        $pdf->Cell(22,10,$row['FormaPago'],1);
        $pdf->Cell(20,10,$row['Tipo'],1);
        $pdf->Ln();
    }

    // Nueva consulta SQL para la segunda tabla
    $sql2 = "SELECT usu.name, 
                    SUM(ci.costo) as TotalCosto, 
                    COUNT(ci.id) as NumeroCitas
             FROM Cita ci
             INNER JOIN Usuarios usu ON usu.id = ci.IdUsuario
             WHERE ci.Estatus = 4 AND DATE(ci.Programado) BETWEEN :fecha_inicio AND :fecha_fin
             GROUP BY IdUsuario";

    $stmt2 = $conn->prepare($sql2);
    $stmt2->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
    $stmt2->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
    $stmt2->execute();
    $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

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

    $pdf->Output();
} else {
    echo "No hay resultados";
}

$conn = null; // Cerrar conexión PDO al finalizar
?>
