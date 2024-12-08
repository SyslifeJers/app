<?php
// El contenido del ticket a imprimir
$contenido_ticket = "Este es el contenido del ticket que deseas imprimir.";

// Nombre del archivo temporal
$archivo_temporal = tempnam(sys_get_temp_dir(), 'ticket_') . '.txt';

// Escribir el contenido en el archivo temporal
file_put_contents($archivo_temporal, $contenido_ticket);

// Nombre de la impresora USB (normalmente es algo como "Impresora XYZ")
$nombre_impresora = "POS-80";

// Comando para imprimir el archivo temporal
$comando = 'print /D:"' . $nombre_impresora . '" ' . $archivo_temporal;

// Ejecutar el comando
exec($comando, $output, $return_var);

if ($return_var == 0) {
    echo "Ticket enviado a la impresora.";
} else {
    echo "Error al enviar el ticket a la impresora.";
}

// Eliminar el archivo temporal
unlink($archivo_temporal);
?>