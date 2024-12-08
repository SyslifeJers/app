<?php
            $host = 'localhost';
            $db   = 'clini234_cerene';
            $user = 'clini234_cerene';
            $pass = 'tu{]ScpQ-Vcg';
$charset = 'utf8mb4';
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

$id = $_POST['id'];
$name = $_POST['name'];
$user = $_POST['user'];
$pass = $_POST['pass'];
$telefono = $_POST['telefono'];
$correo = $_POST['correo'];
$IdRol = $_POST['editRol'];

$stmt = $pdo->prepare("UPDATE Usuarios SET name = ?, user = ?, pass = ?, telefono = ?, correo = ?, IdRol = ? WHERE id = ?");
$success = $stmt->execute([$name, $user, $pass, $telefono, $correo, $IdRol, $id]);

echo json_encode(['success' => $success]);
?>