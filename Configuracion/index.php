<?php

include '../Modulos/head.php';
?>


<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Costos v1</h4>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#insertModal">
            Agregar 
        </button>
            </div>
            <div class="card-body">
            <div class="table-responsive">

                <?php
                
                $sql = "SELECT p.`id`,
                               p.`name`,
                               p.`costo`,
                               COALESCE(p.`sesiones_total`, 1) AS sesiones_total,
                               COALESCE(p.`requiere_cita`, 1) AS requiere_cita,
                               COALESCE(p.`permite_sin_fecha`, 0) AS permite_sin_fecha,
                               p.`activo`,
                               (SELECT COUNT(*) FROM ServiciosPaciente sp WHERE sp.precio_id = p.id) AS servicios_asignados,
                               (SELECT COUNT(*) FROM Cita ci WHERE LOWER(TRIM(ci.Tipo)) = LOWER(TRIM(p.name))) AS citas_asignadas
                        FROM `Precios` p";
                $result = $conn->query($sql);
                
                date_default_timezone_set('America/Mexico_City');
                $hoy = date('Y-m-d');
                
                // Verificar si hay resultados y generar la tabla HTML
                if ($result && $result->num_rows > 0) {
                    echo "<table border='1' id=\"myTable\">
                            <thead>
                                <tr>
                                    <th>Id</th>
                                     <th>Tipo</th>
                                     <th>Precio</th>
                                     <th>Citas</th>
                                     <th>Requiere cita</th>
                                     <th>Sin fecha</th>
                                     <th>Activo</th>
                                     <th>Uso</th>
                                     <th>Opciones</th>
                                </tr>
                            </thead>
                            <tbody>";
                
                    while ($row = $result->fetch_assoc()) {
                        $acti = $row["activo"] == 1 ? 'Sí' : 'No';
                        $enUso = ((int) $row['servicios_asignados'] + (int) $row['citas_asignadas']) > 0;
                        $requiereCita = ((int) $row['requiere_cita']) === 1 ? 'Sí' : 'No';
                        $permiteSinFecha = ((int) $row['permite_sin_fecha']) === 1 ? 'Sí' : 'No';
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['costo']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['sesiones_total']) . '</td>';
                        echo '<td>' . htmlspecialchars($requiereCita) . '</td>';
                        echo '<td>' . htmlspecialchars($permiteSinFecha) . '</td>';
                        echo '<td>' . htmlspecialchars($acti) . '</td>';
                        echo '<td>' . ($enUso ? '<span class="badge bg-warning text-dark">En uso</span>' : '<span class="badge bg-success">Libre</span>') . '</td>';
                        echo "<td>
                                <div class='d-flex flex-wrap gap-1'>
                                <form action='update.php' method='post'>
                                    <input type='hidden' name='id' value='{$row['id']}'>
                                    <input type='hidden' name='activo' value='{$row['activo']}'>
                                    <button type='submit' class='btn btn-sm btn-primary'>Cambiar ";
                        echo $row["activo"] == 1 ? 'Desactivado' : 'Activo';
                        echo "</button>
                                </form>";
                        $payloadEditar = htmlspecialchars(json_encode([
                            'id' => (int) $row['id'],
                            'name' => (string) $row['name'],
                            'costo' => (float) $row['costo'],
                            'sesiones_total' => (int) $row['sesiones_total'],
                            'requiere_cita' => (int) $row['requiere_cita'],
                            'permite_sin_fecha' => (int) $row['permite_sin_fecha'],
                        ]), ENT_QUOTES, 'UTF-8');
                        echo "<button type='button' class='btn btn-sm btn-outline-secondary editar-precio-btn' data-precio='{$payloadEditar}' data-bs-toggle='modal' data-bs-target='#editarModal'>Editar</button>";
                        if (!$enUso) {
                            echo "<form action='eliminar_precio.php' method='post' onsubmit=\"return confirm('¿Eliminar este costo?');\">
                                      <input type='hidden' name='id' value='{$row['id']}'>
                                      <button type='submit' class='btn btn-sm btn-outline-danger'>Eliminar</button>
                                  </form>";
                        }
                        echo "</div>
                              </td>";
                        echo '</tr>';
                    }
                
                    echo "</tbody></table>";
                } else {
                    echo "0 resultados";
                }
                $conn->close();
                ?>
            </div>
            </div>
        </div>
    </div>
</div>







<hr>
   <!-- Modal -->
   <div class="modal fade" id="insertModal" tabindex="-1" aria-labelledby="insertModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="insertModalLabel">Agregar Nuevo Precio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="insertForm" action="insert.php" method="post">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="costo" class="form-label">Costo</label>
                            <input type="number" class="form-control" id="costo" name="costo" required>
                        </div>
                        <div class="mb-3">
                            <label for="sesiones_total" class="form-label">Numero de citas</label>
                            <input type="number" class="form-control" id="sesiones_total" name="sesiones_total" min="0" step="1" value="1" required>
                            <div class="form-text">Usa 0 para servicio sin cita, 1 para cita normal, 2 o mas para servicios con sesiones.</div>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="requiere_cita" name="requiere_cita" value="1" checked>
                            <label class="form-check-label" for="requiere_cita">Requiere cita</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="permite_sin_fecha" name="permite_sin_fecha" value="1">
                            <label class="form-check-label" for="permite_sin_fecha">Permitir registrar sin fecha</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editarModal" tabindex="-1" aria-labelledby="editarModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarModalLabel">Editar Precio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editarForm" action="editar_precio.php" method="post">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_costo" class="form-label">Costo</label>
                            <input type="number" class="form-control" id="edit_costo" name="costo" min="0" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_sesiones_total" class="form-label">Numero de citas</label>
                            <input type="number" class="form-control" id="edit_sesiones_total" name="sesiones_total" min="0" step="1" required>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="edit_requiere_cita" name="requiere_cita" value="1">
                            <label class="form-check-label" for="edit_requiere_cita">Requiere cita</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="edit_permite_sin_fecha" name="permite_sin_fecha" value="1">
                            <label class="form-check-label" for="edit_permite_sin_fecha">Permitir registrar sin fecha</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php
include '../Modulos/footer.php';
?>
<script>
    $(document).ready(function () {
        $('#myTable').DataTable({
            language: {
                lengthMenu: 'Número de filas _MENU_',
                zeroRecords: 'No encontró nada, usa los filtros para pulir la busqueda',
                info: 'Página _PAGE_ de _PAGES_',
                search: 'Buscar:',
                paginate: {
                    first: 'Primero',
                    last: 'Ultimo',
                    next: 'Siguiente',
                    previous: 'Previo'
                },
                infoEmpty: 'No hay registros disponibles',
                infoFiltered: '(Buscamos en _MAX_ resultados)',
            },
        });

        $('.editar-precio-btn').on('click', function () {
            var payload = $(this).data('precio');
            if (typeof payload === 'string') {
                try {
                    payload = JSON.parse(payload);
                } catch (e) {
                    payload = null;
                }
            }
            if (!payload) {
                return;
            }
            $('#edit_id').val(payload.id || '');
            $('#edit_name').val(payload.name || '');
            $('#edit_costo').val(payload.costo || 0);
            $('#edit_sesiones_total').val(payload.sesiones_total || 0);
            $('#edit_requiere_cita').prop('checked', Number(payload.requiere_cita) === 1);
            $('#edit_permite_sin_fecha').prop('checked', Number(payload.permite_sin_fecha) === 1);
        });
    });
</script>
