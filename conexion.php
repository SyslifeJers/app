<?php
function conectar() {
    $db_host = 'localhost';
    $db_name = 'clini234_cerene';
    $db_user = 'clini234_cerene';
    $db_pass = 'tu{]ScpQ-Vcg';

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset("utf8");

    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

    return $conn;
}
?>
