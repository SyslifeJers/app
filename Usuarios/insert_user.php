<?php
ini_set('display_errors', 1);
session_start();

$ROL_PRACTICANTE = 6;
if (isset($_SESSION['rol']) && (int) $_SESSION['rol'] === $ROL_PRACTICANTE) {
    http_response_code(403);
    die('No tienes permisos para crear usuarios.');
}

if (!isset($_SESSION['id']) || !isset($_SESSION['token'])) {
    http_response_code(401);
    die('No autenticado.');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $user = $_POST['user'];
    $pass = $_POST['pass'];
    $telefono = $_POST['telefono'];
    $correo = $_POST['correo'];
    $IdRol = (int) ($_POST['IdRol'] ?? 0);

    $rolSesion = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
    $esVentas = ($rolSesion === 1);
    if ($esVentas && $IdRol !== $ROL_PRACTICANTE) {
        http_response_code(403);
        die('Solo puedes registrar usuarios con rol Practicante.');
    }

    // Validar la contraseña (mínimo 6 caracteres)
    if (strlen($pass) < 6) {
        die("La contraseña debe tener al menos 6 caracteres.");
    }

    require_once __DIR__ . '/../conexion.php';
    require_once __DIR__ . '/../Modulos/logger.php';
    $conn = conectar();

    // Preparar y vincular
    $stmt = $conn->prepare("INSERT INTO Usuarios (name, user, pass, token, activo, registro, telefono, correo, IdRol) VALUES (?, ?, ?, '', 1, NOW(), ?, ?, ?)");
    $stmt->bind_param("sssssi", $name, $user, $pass, $telefono, $correo, $IdRol);

    // Ejecutar la consulta
    if ($stmt->execute()) {
        $nuevoUsuarioId = $conn->insert_id;
        registrarLog(
            $conn,
            $_SESSION['id'] ?? null,
            'usuarios',
            'crear',
            sprintf('Se creó el usuario "%s" (ID %d) con rol %s.', $user, $nuevoUsuarioId, $IdRol),
            'Usuario',
            (string) $nuevoUsuarioId
        );
        header("Location: ../usuarios.php");
                die();
    } else {
        echo "Error: " . $stmt->error;
    }

    // Cerrar la conexión
    $stmt->close();
    $conn->close(); 
}
?>
