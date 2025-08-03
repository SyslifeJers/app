<?php
include '../Modulos/head.php';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$tipoPid = isset($_GET['tipoPid']) ? $_GET['tipoPid'] : '';

$db_host = 'localhost';
$db_name = 'clini234_cerene';
$db_user = 'clini234_cerene';
$db_pass = 'tu{]ScpQ-Vcg';

// Crear conexión
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset("utf8");
// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

date_default_timezone_set('America/Mexico_City');
$hoy = date('Y-m-d');

// Consulta SQL
$sql = "SELECT ci.id, 
            n.name, 
            us.name as Psicologo, 
            us.id as idp,
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
        WHERE ci.Estatus = 4";

// Añadir el filtro de fecha si se seleccionó una
if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $sql .= " AND DATE(ci.Programado) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
} else {
    $fecha_inicio = $hoy;
    $fecha_fin = $hoy;
    $sql .= " AND DATE(ci.Programado) = '$hoy'";
}

if (!empty($tipoPid)) {
    $sql .= " AND ci.IdUsuario = '$tipoPid'";
}

$sql .= " ORDER BY ci.Programado ASC;";

$result = $conn->query($sql);

$sql_summary = "SELECT SUM(ci.costo) as TotalCosto, COUNT(ci.id) as NumeroCitas, FormaPago
        FROM Cita ci
        WHERE ci.Estatus = 4 ";
if (!empty($tipoPid)) {
    $sql_summary .= " AND ci.IdUsuario = '$tipoPid'";
}

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $sql_summary .= " AND DATE(ci.Programado) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
}
$sql_summary .= " GROUP BY FormaPago";
$result_summary = $conn->query($sql_summary);



$sql_summary2 = "SELECT 
    SUM(ci.costo) as TotalCosto, 
    COUNT(ci.id) as NumeroCitas,
    CASE 
        WHEN es.name = 'creada' THEN 'Inactivas'
        ELSE es.name
    END as Resumen
FROM 
    Cita ci
inner join Estatus es on es.id = ci.Estatus
WHERE ci.Estatus <> 10 ";

if (!empty($tipoPid)) {
    $sql_summary2 .= " AND ci.IdUsuario = '$tipoPid'";
}

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $sql_summary2 .= " AND DATE(ci.Programado) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
}
$sql_summary2 .= 'GROUP by ci.Estatus;';
$result_summary2 = $conn->query($sql_summary2);


$date_inicio = new DateTime($fecha_inicio);
$date_fin = new DateTime($fecha_fin);

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="row">
            <div class="col-12 col-sm-6 col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body p-3 text-center">
                        <div class="text-muted mb-3">Rango</div>
                        <div class="h3 m-0">
                            <?php echo $date_inicio->format('d/m/Y') . ' - ' . $date_fin->format('d/m/Y'); ?>
                        </div>

                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body">

                        <?php 
                        
                                                    $totalCostoGeneral = 0;
                                                    $totalCitasGeneral = 0;
                        if ($result_summary->num_rows > 0) {
                            // Crear un contenedor div
                            echo "<div>";

                            // Recorrer los resultados
                            while($row = $result_summary->fetch_assoc()) {
                                $formaPago = $row['FormaPago'] ? $row['FormaPago'] : 'No asignado';
                                
                                // Acumular los totales
                                $totalCostoGeneral += $row['TotalCosto'];
                                $totalCitasGeneral += $row['NumeroCitas'];
                                
                                echo "<div>";
                                echo "<b>Forma de Pago: " . htmlspecialchars($formaPago) . "</b><br>";
                                echo "Total Costo: $" . number_format($row['TotalCosto'], 2) . "<br>";
                                echo "Número de Citas: " . $row['NumeroCitas'] . "<br>";
                                echo "</div><br>";
                            }
                        
                            // Cerrar el contenedor div
                            echo "</div>";
                        } else {
                            echo "No se encontraron resultados.";
                        }
                        ?>
                                   
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mt-2">
                            <?php
                            if ($result_summary2->num_rows > 0) {
                                // Iniciar el contenido del div
                                $output = '<div >';

                                // Recorrer los resultados y construir el contenido HTML
                                while ($row = $result_summary2->fetch_assoc()) {
                                    $output .= '<p><b>' . $row['Resumen'] . '</b><br>Cantidad: ' . $row['NumeroCitas'] . ' ($ ' . $row['TotalCosto'] . ')</p>';
                                }

                                // Cerrar el contenido del div
                                $output .= '</div>';

                                // Mostrar el contenido del div
                                echo $output;
                            } else {
                                echo '<div id="summary">No se encontraron resultados.</div>';
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body">

                                                <div class="d-flex justify-content-between">
                            <div>
                                <h5><b>Total</b></h5>
                            </div>
                            <h3 class="text-info fw-bold">$<?php echo number_format($totalCostoGeneral, 2) ?></h3>
                        </div>
            
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5><b>Citas concluidas</b></h5>
                            </div>
                            <h3 class="text-success fw-bold"><?php echo $totalCitasGeneral ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Filtros</h4>
                <div class="col-md-12 col-lg-12">
                    <div class="table-responsive">
                        <form method="GET" action="">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th scope="col">Fecha de inicio:</th>
                                        <th scope="col">Fecha de fin:</th>
                                        <th scope="col">Psicólogo</th>
                                        <th scope="col"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="date" id="fecha_inicio" name="fecha_inicio"
                                                class="form-control" value="<?php if (!empty($fecha_inicio)) {
                                                    echo $fecha_inicio;
                                                } ?>">
                                        </td>
                                        <td><input type="date" id="fecha_fin" name="fecha_fin" class="form-control"
                                                value="<?php if (!empty($fecha_fin)) {
                                                    echo $fecha_fin;
                                                } ?>"></td>
                                        <td><select class="form-select" id="tipoPid" name="tipoPid">
                                            </select></td>
                                        <td><button class="btn btn-success btn-border" type="submit">
                                                Buscar
                                            </button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <h4 class="card-title">Lista citas</h4>
                <div class="table-responsive">
                    <?php
                    if ($result->num_rows > 0) {
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
                        foreach ($rows as $row) {
                            echo "<tr>
                                <td>" . $row["Fecha"] . "</td>
                                <td>" . $row["id"] . "</td>
                                <td>" . $row["name"] . "</td>
                                <td>" . $row["Psicologo"] . "</td>
                                <td>" . $row["costo"] . "</td>
                                <td>" . $row["Hora"] . "</td>
                                <td>" . $row["Tipo"] . "</td>
                                <td>" . $row["Estatus"] . "</td>
                                <td>" . $row["FormaPago"] . "</td>
                              </tr>";
                        }
                        echo "</tbody></table>";
                    } else {
                        echo "0 resultados";
                    }
                    $conn->close();
                    ?>
                </div>
            </div>
            <hr>
            <div class="form-group">
                <form id="reporteForm">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-label">
                            <i class="fa fa-info"></i>
                        </span>
                        Imprimir Reporte de
                        <?php if (!empty($fecha_inicio) && !empty($fecha_fin)) {
                            echo $fecha_inicio . ' a ' . $fecha_fin;
                        } ?>
                    </button>
                </form>
            </div>
            <iframe id="reporteFrame" style="width: 100%; height: 600px; margin-top: 20px;"></iframe>
        </div>
    </div>
</div>

<hr>

<?php
include '../Modulos/footer.php';
?>
<script>
    document.getElementById('reporteForm').addEventListener('submit', function (event) {
        event.preventDefault();
        const fecha_inicio = document.getElementById('fecha_inicio').value;
        const fecha_fin = document.getElementById('fecha_fin').value;
        const tipoPid = document.getElementById('tipoPid').value;
        const url = 'imprimir.php?fecha_inicio=' + fecha_inicio + '&fecha_fin=' + fecha_fin + '&tipoPid=' + tipoPid;
        document.getElementById('reporteFrame').src = url;
    });

    $(document).ready(function () {
        $.ajax({

            url: 'getUsu.php',
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                var select = $('#tipoPid');
                select.empty(); // Vaciar el select
                select.append('<option value="">Todos</option>'); // Opción predeterminada
                $.each(data, function (index, usuario) {
                    select.append('<option value="' + usuario.id + '">' + usuario.name + '</option>');
                });
                var tipoPid = '<?php echo $tipoPid; ?>';
                if (tipoPid !== '') {
                    select.val(tipoPid);
                }
            }
        });
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