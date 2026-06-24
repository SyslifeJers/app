<?php
date_default_timezone_set('America/Mexico_City');

function fechaMexicoActual(): string {
    return (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
}

function conectar() {
    $db_host = 'localhost';
    $db_name = 'u529445062_cenere';
    $db_user = 'u529445062_jers';
    $db_pass = 'Rtx2080_';

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

    $conn->set_charset("utf8");
    $conn->query("SET time_zone = '-06:00'");

    return $conn;
}
?>
