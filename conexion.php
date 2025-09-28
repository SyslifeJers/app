<?php
function conectar() {
    $db_host = 'localhost';
    $db_name = 'u529445062_cenere';
    $db_user = 'u529445062_jers';
    $db_pass = 'Rtx2080_';

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset("utf8");

    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

    return $conn;
}
?>
