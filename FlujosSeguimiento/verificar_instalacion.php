<?php
include __DIR__ . '/../Modulos/head.php';

$tablas = [
    'exp_flujos',
    'exp_flujo_tareas',
    'exp_flujo_asignaciones',
    'exp_flujo_tareas_asignadas',
    'exp_flujo_documentos',
    'exp_flujo_historial',
];

$estado = [];
foreach ($tablas as $tabla) {
    $ok = false;
    $error = '';
    try {
        $result = $conn->query('SELECT 1 FROM `' . $tabla . '` LIMIT 1');
        $ok = $result !== false;
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        if (!$ok) {
            $error = $conn->error;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    $estado[] = ['tabla' => $tabla, 'ok' => $ok, 'error' => $error];
}
?>

<div class="page-inner">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h3 class="fw-bold mb-3">Verificacion de Flujos de Seguimiento</h3>
        <a class="btn btn-outline-secondary" href="index.php">Volver</a>
    </div>

    <div class="alert alert-info">
        Esta pantalla no usa <code>SHOW TABLES</code> ni crea estructura. Solo valida con consultas funcionales <code>SELECT 1 FROM tabla LIMIT 1</code>.
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th>Tabla</th><th>Estado</th><th>Detalle</th></tr></thead>
                    <tbody>
                        <?php foreach ($estado as $fila): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($fila['tabla']); ?></code></td>
                                <td><?php echo $fila['ok'] ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Falta / sin permiso</span>'; ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($fila['error']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-muted mb-0">Si falta estructura, ejecutar manualmente <code>../FLUJOS_SEGUIMIENTO_INSTALL_TABLES.sql</code> con respaldo previo.</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../Modulos/footer.php'; ?>
