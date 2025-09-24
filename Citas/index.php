<?php
include '../Modulos/head.php';

function normalizarFecha($fecha)
{
    if (empty($fecha)) {
        return '';
    }

    $fecha = trim($fecha);
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);

    return ($dt && $dt->format('Y-m-d') === $fecha) ? $fecha : '';
}

date_default_timezone_set('America/Mexico_City');
$hoy = date('Y-m-d');
$aplicarFiltros = isset($_GET['aplicar_filtros']);

$fechaInicio = $aplicarFiltros ? ($_GET['fecha_inicio'] ?? '') : $hoy;
$fechaFin = $aplicarFiltros ? ($_GET['fecha_fin'] ?? '') : $hoy;
$statusSeleccionado = $aplicarFiltros ? ($_GET['status'] ?? '') : '';

$fechaInicio = normalizarFecha($fechaInicio);
$fechaFin = normalizarFecha($fechaFin);
$statusSeleccionado = trim($statusSeleccionado);
$estatusDisponibles = ['Cancelada', 'Creada', 'Reprogramado', 'Finalizada'];

if ($statusSeleccionado !== '' && !in_array($statusSeleccionado, $estatusDisponibles, true)) {
    $statusSeleccionado = '';
}

if ($fechaInicio && $fechaFin && $fechaInicio > $fechaFin) {
    [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
}

$condiciones = [];
$tipos = '';
$parametros = [];

if ($statusSeleccionado === '') {
    $condiciones[] = 'ci.Estatus IN (1, 4)';
}

if ($fechaInicio !== '') {
    $condiciones[] = 'DATE(ci.Programado) >= ?';
    $tipos .= 's';
    $parametros[] = $fechaInicio;
}

if ($fechaFin !== '') {
    $condiciones[] = 'DATE(ci.Programado) <= ?';
    $tipos .= 's';
    $parametros[] = $fechaFin;
}

if ($statusSeleccionado !== '') {
    $condiciones[] = 'es.name = ?';
    $tipos .= 's';
    $parametros[] = $statusSeleccionado;
}

if (empty($condiciones)) {
    $condiciones[] = '1 = 1';
}

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
        WHERE " . implode(' AND ', $condiciones) . '
        ORDER BY ci.Programado ASC';

$stmt = $conn->prepare($sql);
$errorConsulta = '';

if ($stmt === false) {
    $errorConsulta = $conn->error;
    $result = false;
} else {
    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$parametros);
    }

    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">Citas</h4>
                <a class="btn btn-outline-primary btn-sm" href="calendario.php">
                    <i class="far fa-calendar-alt me-1"></i>Ver calendario
                </a>
            </div>
            <div class="card-body">
                <form id="filtersForm" class="row mb-3 g-3" method="get">
                    <input type="hidden" name="aplicar_filtros" value="1">
                    <div class="col-md-3">
                        <label for="min-date" class="form-label">Fecha Inicio:</label>
                        <input type="date" class="form-control" id="min-date" name="fecha_inicio" value="<?php echo htmlspecialchars($fechaInicio, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="max-date" class="form-label">Fecha Fin:</label>
                        <input type="date" class="form-control" id="max-date" name="fecha_fin" value="<?php echo htmlspecialchars($fechaFin, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="status-filter" class="form-label">Estatus:</label>
                        <select class="form-select" id="status-filter" name="status">
                            <option value="" <?php echo $statusSeleccionado === '' ? 'selected' : ''; ?>>Todos</option>
                            <option value="Cancelada" <?php echo $statusSeleccionado === 'Cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                            <option value="Creada" <?php echo $statusSeleccionado === 'Creada' ? 'selected' : ''; ?>>Creada</option>
                            <option value="Reprogramado" <?php echo $statusSeleccionado === 'Reprogramado' ? 'selected' : ''; ?>>Reprogramado</option>
                            <option value="Finalizada" <?php echo $statusSeleccionado === 'Finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-black w-100" id="filter-button">Filtrar</button>
                    </div>
                </form>
                <div class="table-responsive">

                    <?php
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
                    } elseif ($errorConsulta !== '') {
                        echo "<div class='alert alert-danger'>Ocurrió un error al consultar las citas. Por favor intenta de nuevo.</div>";
                    } else {
                        echo "<div class='alert alert-info'>No se encontraron resultados con los filtros seleccionados.</div>";
                    }

                    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                        $stmt->close();
                    }

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
        var $table = $('#myTable');

        if ($table.length) {
            $table.DataTable({
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
