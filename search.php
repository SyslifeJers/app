<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$db = 'clini234_cerene';
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

// Preparar la consulta SQL con parámetros
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sql = "SELECT nin.id, nin.name, cli.name AS cliente_name 
        FROM nino nin
        INNER JOIN Clientes cli ON nin.idtutor = cli.id
        WHERE nin.activo = 1 AND nin.name LIKE ?";

// Usar prepared statements para evitar SQL injection
$stmt = $pdo->prepare($sql);
$stmt->execute(["%$search%"]);
$results = $stmt->fetchAll();

// Mostrar los resultados en una tabla HTML
if ($results) {
    echo "<table border='1' class=\"table mt-3\">";
    echo "<tr><th>ID</th><th>Nombre</th><th>Tutor</th><th>Opción</th></tr>";
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['cliente_name']) . "</td>";
        echo  "<td><button class=\"btn btn-sm btn-success\" onclick='nini(" . $row["id"] . ")'> <span class=\"btn-label\">
                          <i class=\"fa fa-check\"></i>
                        </span>  Seleccionar</button></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No se encontraron resultados.";
}
