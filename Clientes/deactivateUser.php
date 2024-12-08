<?php
$host = 'localhost';
$db = 'clini234_cerene';
$user = 'clini234_cerene';
$pass = 'tu{]ScpQ-Vcg';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID is required']);
    exit;
}

$id = $_GET['id'];

// Obtener el valor actual de activo
$stmt = $pdo->prepare("SELECT activo FROM Clientes WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Client not found']);
    exit;
}

// Determinar el nuevo valor de activo
$nuevoActivo = $row['activo'] == 1 ? 0 : 1;

// Actualizar el valor de activo
$stmt = $pdo->prepare("UPDATE Clientes SET activo = ? WHERE id = ?");
$success = $stmt->execute([$nuevoActivo, $id]);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to execute the query']);
}
?>
