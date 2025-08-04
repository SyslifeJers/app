<?php

include '../Modulos/head.php';
?>


<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Costos</h4>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#insertModal">
            Agregar 
        </button>
            </div>
            <div class="card-body">
            <div class="table-responsive">

                <?php
                
                $sql = "SELECT `id`, `name`, `costo`, `activo` FROM `Precios`";
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
                                    <th>Activo</th>
                                    <th>Opciones</th>
                                </tr>
                            </thead>
                            <tbody>";
                
                    while ($row = $result->fetch_assoc()) {
                        $acti = $row["activo"] == 1 ? 'Sí' : 'No';
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['costo']) . '</td>';
                        echo '<td>' . htmlspecialchars($acti) . '</td>';
                        echo "<td>
                                <form action='update.php' method='post'>
                                    <input type='hidden' name='id' value='{$row['id']}'>
                                    <input type='hidden' name='activo' value='{$row['activo']}'>
                                    <button type='submit' class='btn btn-sm btn-primary'>Cambiar ";
                        echo $row["activo"] == 1 ? 'Desactivado' : 'Activo';
                        echo "</button>
                                </form>
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
                        <button type="submit" class="btn btn-primary">Guardar</button>
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
    });
</script>