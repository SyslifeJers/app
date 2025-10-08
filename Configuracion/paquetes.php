<?php
include '../Modulos/head.php';

$mensajePaquetes = $_SESSION['paquetes_mensaje'] ?? null;
$tipoMensajePaquetes = $_SESSION['paquetes_tipo'] ?? 'success';
unset($_SESSION['paquetes_mensaje'], $_SESSION['paquetes_tipo']);

$paquetes = [];
$consultaPaquetes = $conn->query('SELECT id, nombre, primer_pago_monto, saldo_adicional, activo, creado_en FROM Paquetes ORDER BY nombre');
if ($consultaPaquetes instanceof mysqli_result) {
    while ($fila = $consultaPaquetes->fetch_assoc()) {
        $paquetes[] = $fila;
    }
    $consultaPaquetes->free();
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Paquetes</h4>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#insertPaqueteModal">
                    Agregar
                </button>
            </div>
            <div class="card-body">
                <?php if ($mensajePaquetes): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($tipoMensajePaquetes, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensajePaquetes, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <?php if (!empty($paquetes)) : ?>
                        <table class="table" id="tablaPaquetes">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Primer pago</th>
                                    <th>Saldo adicional</th>
                                    <th>Activo</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paquetes as $paquete) : ?>
                                    <tr>
                                        <td><?php echo (int) $paquete['id']; ?></td>
                                        <td><?php echo htmlspecialchars($paquete['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>$<?php echo number_format((float) $paquete['primer_pago_monto'], 2); ?></td>
                                        <td>$<?php echo number_format((float) $paquete['saldo_adicional'], 2); ?></td>
                                        <td><?php echo ((int) $paquete['activo'] === 1) ? 'Sí' : 'No'; ?></td>
                                        <td><?php echo htmlspecialchars($paquete['creado_en'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <form action="toggle_paquete.php" method="post" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo (int) $paquete['id']; ?>">
                                                <input type="hidden" name="activo" value="<?php echo (int) $paquete['activo']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <?php echo ((int) $paquete['activo'] === 1) ? 'Desactivar' : 'Activar'; ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="mb-0">Aún no hay paquetes registrados.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="insertPaqueteModal" tabindex="-1" aria-labelledby="insertPaqueteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="insertPaqueteModalLabel">Crear paquete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="insertPaqueteForm" action="guardar_paquete.php" method="post">
                    <div class="mb-3">
                        <label for="paqueteNombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="paqueteNombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="paquetePrimerPago" class="form-label">Monto del primer pago</label>
                        <input type="number" class="form-control" id="paquetePrimerPago" name="primer_pago" min="0" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="paqueteSaldo" class="form-label">Saldo adicional otorgado</label>
                        <input type="number" class="form-control" id="paqueteSaldo" name="saldo_adicional" min="0" step="0.01" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php $conn->close(); ?>
<?php include '../Modulos/footer.php'; ?>
<script>
    $(document).ready(function () {
        if ($('#tablaPaquetes').length) {
            $('#tablaPaquetes').DataTable({
                language: {
                    lengthMenu: 'Número de filas _MENU_',
                    zeroRecords: 'No encontró nada, usa los filtros para pulir la búsqueda',
                    info: 'Página _PAGE_ de _PAGES_',
                    search: 'Buscar:',
                    paginate: {
                        first: 'Primero',
                        last: 'Último',
                        next: 'Siguiente',
                        previous: 'Previo'
                    },
                    infoEmpty: 'No hay registros disponibles',
                    infoFiltered: '(Buscamos en _MAX_ resultados)',
                },
            });
        }
    });
</script>
