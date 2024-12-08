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

// Primero obtenemos el valor actual de 'activo'
$stmt = $pdo->prepare("SELECT activo FROM Usuarios WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if ($user) {
    // Invertimos el valor de 'activo'
    $new_activo = $user['activo'] == 1 ? 0 : 1;

    // Actualizamos el valor de 'activo' en la base de datos
    $stmt = $pdo->prepare("UPDATE Usuarios SET activo = ? WHERE id = ?");
    $success = $stmt->execute([$new_activo, $id]);

    if ($success) {
        echo json_encode(['success' => true, 'new_activo' => $new_activo]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to execute the update query']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'User not found']);
}
?>
