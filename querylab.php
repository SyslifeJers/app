<?php
require_once 'conexion.php';
$conn = conectar();

// Procesar consulta si se envió el formulario
$results = [];
$error = '';
$query = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = $_POST['query'];
    
    if (!empty($query)) {
        try {
            $result = $conn->query($query);
            
            if ($result === TRUE) {
                // Para consultas que no devuelven resultados (INSERT, UPDATE, DELETE)
                $results[] = ["success" => "Consulta ejecutada correctamente. Filas afectadas: " . $conn->affected_rows];
            } elseif ($result) {
                // Para consultas SELECT que devuelven resultados
                while ($row = $result->fetch_assoc()) {
                    $results[] = $row;
                }
                $result->free();
            } else {
                $error = "Error en la consulta: " . $conn->error;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Probador de Consultas MySQL</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        textarea { width: 100%; height: 100px; margin-bottom: 10px; }
        button { padding: 8px 15px; background: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
        .error { color: red; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Probador de Consultas MySQL</h1>
        
        <form method="post">
            <textarea name="query" placeholder="Escribe tu consulta SQL aquí"><?= htmlspecialchars($query) ?></textarea><br>
            <button type="submit">Ejecutar Consulta</button>
        </form>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($results)): ?>
            <h2>Resultados:</h2>
            <table>
                <thead>
                    <tr>
                        <?php if (!empty($results[0]) && !isset($results[0]['success'])): ?>
                            <?php foreach ($results[0] as $key => $value): ?>
                                <th><?= htmlspecialchars($key) ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <?php if (isset($row['success'])): ?>
                                <td colspan="100%"><?= htmlspecialchars($row['success']) ?></td>
                            <?php else: ?>
                                <?php foreach ($row as $value): ?>
                                    <td><?= htmlspecialchars($value) ?></td>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
// Cerrar conexión
$conn->close();
?>