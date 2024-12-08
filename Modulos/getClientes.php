
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

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT c.`id`, c.`name`,c.`activo`, `telefono`, `correo`, GROUP_CONCAT(n.name) as Pasientes, c.fecha as Registro FROM `Clientes` c
inner join nino n on n.idtutor = c.id;");
$stmt->execute([$id]);
$user = $stmt->fetch();
echo json_encode($user);
?>