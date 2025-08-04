<?php include '../Modulos/head.php'; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Citas</h4>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="min-date" class="form-label">Fecha Inicio:</label>
                        <input type="date" class="form-control" id="min-date">
                    </div>
                    <div class="col-md-3">
                        <label for="max-date" class="form-label">Fecha Fin:</label>
                        <input type="date" class="form-control" id="max-date">
                    </div>
                    <div class="col-md-3">
                        <label for="status-filter" class="form-label">Estatus:</label>
                        <select class="form-select" id="status-filter">
                            <option value="">Todos</option>
                            <option value="Cancelada">Cancelada</option>
                            <option value="Creada">Creada</option>
                            <option value="Reprogramado">Reprogramado</option>
                            <option value="Finalizada">Finalizada</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-black w-100" id="filter-button">Filtrar</button>
                    </div>
                </div>
                <div class="table-responsive">

                    <?php
                    $sql = "SELECT ci.id,
                    n.name,
                    us.name as Psicologo,
                    ci.costo,
                    ci.Programado,
                    DATE(ci.Programado) as Fecha,
                    TIME(ci.Programado) as Hora,
                    ci.Tipo,
                    es.name as Estatus,
                    ci.FormaPago
                    FROM Cita ci
                    INNER JOIN nino n ON n.id = ci.IdNino
                    INNER JOIN Usuarios us ON us.id = ci.IdUsuario
                    INNER JOIN Estatus es ON es.id = ci.Estatus
                    WHERE (ci.Estatus = 1 OR ci.Estatus = 4)
                    ORDER BY ci.Programado ASC;";

                    $result = $conn->query($sql);
                    
                    // Establecer zona horaria
                    date_default_timezone_set('America/Mexico_City');
                    $hoy = date('Y-m-d');

                    // Consulta SQL para filtrar desde la fecha actual hacia adelante
                   

               

                    // Verificar si hay resultados y generar la tabla HTML
                    if ($result && $result->num_rows > 0) {
                        echo "<table border='1' id='myTable'>
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Id</th>
                                        <th>Paciente</th>
                                        <th>Psicólogo</th>
                                        <th>Costo</th>
                                        <th>Hora</th>
                                        <th>Tipo</th>
                                        <th>Estatus</th> 
                                        <th>Forma de pago</th>
                                     
                                    </tr>
                                </thead>
                                <tbody>";
                        // Recorrer los resultados y mostrarlos en la tabla
                        while ($row = $result->fetch_assoc())  {
                            echo "<tr>
                                    <td>{$row['Fecha']}</td>
                                    <td>{$row['id']}</td>
                                    <td>{$row['name']}</td>
                                    <td>{$row['Psicologo']}</td>
                                    <td>{$row['costo']}</td>
                                    <td>{$row['Hora']}</td>
                                    <td>{$row['Tipo']}</td>
                                    <td>{$row['Estatus']}</td>
                                    <td>{$row['FormaPago']}</td>

                                  </tr>";
                        }
                        echo "</tbody></table>";
                    } else {
                        echo "0 resultados";
                    }

                    // Cerrar conexión
                    $conn->close();
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<hr>
<!-- Formulario para generar el reporte -->
<form id="reporteForm">
    <input type="hidden" id="fecha_inicio" name="fecha_inicio">
    <input type="hidden" id="fecha_fin" name="fecha_fin">
    <button type="submit" class="btn btn-black">Generar Reporte</button>
</form>

<!-- Iframe para mostrar el reporte -->
<iframe id="reporteFrame" style="width: 100%; height: 600px;"></iframe>
<?php include '../Modulos/footer.php'; ?>

<script>
    document.getElementById('reporteForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const fecha_inicio = document.getElementById('min-date').value;
    const fecha_fin = document.getElementById('max-date').value;
    const url = '../Reportes/imprimir.php?fecha_inicio=' + fecha_inicio + '&fecha_fin=' + fecha_fin;
    document.getElementById('reporteFrame').src = url;
});
    $(document).ready(function () {
        // Establecer la fecha de hoy en los campos de fecha de inicio y fin
        var hoy = new Date().toISOString().split('T')[0];
        $('#min-date').val(hoy);
        $('#max-date').val(hoy);

        var table = $('#myTable').DataTable({
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

        // Filtro por rango de fechas
        $.fn.dataTable.ext.search.push(
            function (settings, data, dataIndex) {
                var minDate = $('#min-date').val();
                var maxDate = $('#max-date').val();
                var programado = data[0]; // Fecha está en la primera columna (índice 0)

                if (
                    (minDate === "" && maxDate === "") ||
                    (minDate === "" && programado <= maxDate) ||
                    (minDate <= programado && maxDate === "") ||
                    (minDate <= programado && programado <= maxDate)
                ) {
                    return true;
                }
                return false;
            }
        );

        // Filtro por estatus
        $('#filter-button').on('click', function () {
            var statusValue = $('#status-filter').val();
            table.column(7).search(statusValue).draw(); // Estatus está en la octava columna (índice 7)
            table.draw();
        });

        // Aplicar filtros al cambiar las fechas
        $('#min-date, #max-date').on('change', function () {
            table.draw();
        });

        // Aplicar filtros inicialmente
        table.draw();
    });
</script>
