<?php
            $host = 'localhost';
            $db   = 'clini234_cerene';
            $user = 'clini234_cerene';
            $pass = 'tu{]ScpQ-Vcg';
$charset = 'utf8mb4';

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

$id = $_GET['idTutor'];
$sql = "SELECT id, name, activo, edad, Observacion, FechaIngreso, idtutor FROM nino WHERE idtutor = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id ]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($users);
?>
