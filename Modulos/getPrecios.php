
<?php
			ini_set('error_reporting', E_ALL);
			ini_set('display_errors', 1);
?>
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

$sql = "SELECT `id`, CONCAT( `name`, ': $' ,`costo` ) as name, `costo` FROM `Precios` WHERE `activo` = 1;";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($users);
?>
