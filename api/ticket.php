<?php
// Habilitar reporte de errores
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../conexion.php';

// Obtener el ID de la cita desde la URL
$cita_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($cita_id == 0) {
    die("ID de cita no válido.");
}

try {
    $conn = conectar();

    // Preparar la consulta
    $sql = "SELECT ci.id, 
                n.name, 
                us.name as Psicologo, 
                ci.costo, 
                ci.Programado, 
                 DATE_FORMAT(DATE(ci.Programado), '%d-%m-%Y') as Fecha, 
                TIME(ci.Programado) as Hora, 
                ci.Tipo, 
                ci.FormaPago
            FROM Cita ci
            INNER JOIN nino n ON n.id = ci.IdNino
            INNER JOIN Usuarios us ON us.id = ci.IdUsuario
            INNER JOIN Estatus es ON es.id = ci.Estatus
            WHERE ci.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cita_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $cita = $result->fetch_assoc();
    if ($cita) {
        // Intentar conectar a la impresora USB

        // Datos de impresión
        $cerene = "Cliníca Cerene \n";
        $cerene2 = "Centro de rehabilitación neuropsicológica\n";
        $fechaImpresion = "Fecha: " . date("d-m-Y H:i:s");

        // Formatear el contenido del ticket
        $cliente = "Cliente: " . $cita['name'];
        $psicologo = "Psicólogo: " . $cita['Psicologo'];
        $costo = "Costo: $" . $cita['costo'];
        $fecha = "Fecha de cita: " . $cita['Fecha'];
        $hora = "Hora de cita: " . $cita['Hora'];
        $tipo = "Tipo de servicio: " . $cita['Tipo'];
        $formaPago = "Forma de pago: " . $cita['FormaPago'];

        // Imprimir el contenido
        $ticket = 
        $cerene .
        $cerene2.
        $fechaImpresion . "\n" . 
        $cliente . "\n" . 
        $psicologo . "\n" . 
        $costo . "\n" . 
        $fecha . "\n" . 
        $hora . "\n" . 
        $tipo . "\n" . 
        $formaPago;

// Convertir a JSON
$json_ticket = json_encode(array('ticket' => $ticket));

echo $json_ticket;
        
        // Cerrar la pestaña si no hay errores
       
    } else {
        echo "No se encontró la cita con el ID especificado.";
    }

    // Cerrar la conexión a la base de datos
    $conn->close();
} catch (Exception $e) {
    echo "No se pudo imprimir el ticket: " . $e->getMessage();
}
?>
