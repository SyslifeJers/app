<?php
include '../Modulos/head.php';
?>

<div class="container mt-5">

</div>

<div class="row">
    <div class="col-sm-6 col-md-3">
        <div class="card card-stats card-round">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-icon">
                        <div class="icon-big text-center icon-primary bubble-shadow-small">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="col col-stats ms-3 ms-sm-0">
                        <div class="numbers">
                            <form id="filterForm" method="post">
                                <div class="mb-3">
                                    <label for="filter" class="form-label">Filtro:</label>
                                    <select id="filter" name="filter" class="form-select" onchange="filterTable()">
                                        <option value="all" <?php echo (isset($_POST['filter']) && $_POST['filter'] == 'all') ? 'selected' : ''; ?>>Todos</option>
                                        <option value="active" <?php echo (isset($_POST['filter']) && $_POST['filter'] == 'active') ? 'selected' : ''; ?>>Activos</option>
                                        <option value="inactive" <?php echo (isset($_POST['filter']) && $_POST['filter'] == 'inactive') ? 'selected' : ''; ?>>Desactivados</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card card-stats card-round">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-icon">
                        <div class="icon-big text-center icon-info bubble-shadow-small">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="col col-stats ms-3 ms-sm-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#exampleModal">
                            Registro de Cliente(Papás o tutor)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="container mt-5">

    <h2 class="mb-4">Lista de Clientes(Papás o tutor)</h2>
    <div class="table-responsive">

        <table class="table table-bordered" id="myTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Pacientes</th>
                    <th>Activo</th>
                    <th>Registro</th>
                    <th>Teléfono</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Configuración de la conexión a la base de datos
                $host = 'localhost';
                $db = 'clini234_cerene';
                $user = 'clini234_cerene';
                $pass = 'tu{]ScpQ-Vcg';
                $charset = 'utf8';

                $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                try {
                    $pdo = new PDO($dsn, $user, $pass, $options);
                } catch (\PDOException $e) {
                    throw new \PDOException($e->getMessage(), (int) $e->getCode());
                }

                // Consulta a la base de datos
                $sql = "SELECT c.`id`, 
				   c.`name`, 
				   c.`activo`, 
				   c.`telefono`, 
				   GROUP_CONCAT(n.name) as Pacientes, 
				   c.`fecha` as Registro 
			FROM `Clientes` c
			LEFT JOIN `nino` n ON n.`idtutor` = c.`id`";

                // Definir el filtro
                $filter = isset($_POST['filter']) ? $_POST['filter'] : 'all';

                // Consulta a la base de datos
                
                // Aplicar filtro según el estado
                if ($filter == 'active') {
                    $sql .= " WHERE c.`activo` = 1";
                } elseif ($filter == 'inactive') {
                    $sql .= " WHERE c.`activo` = 0";
                }

                $sql .= " GROUP BY c.`id` DESC;";

                $stmt = $pdo->query($sql);

                // Generación de filas de la tabla
                while ($row = $stmt->fetch()) {
                    $acti = $row["activo"] == 1 ? 'Sí' : 'No';
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                    echo '<td>';
                    if ($row['Pacientes'] != null) {
                        //. htmlspecialchars($row['Pasientes']) . 
                        $pasientes = explode(',', $row['Pacientes']);
                        foreach ($pasientes as $index => $pasiente) {
                            echo trim($pasiente) . '<a href="#" onclick="openModal(' . ($row['id']) . ', \'' . trim($pasiente) . '\')"> Editar</a>';
                            if ($index < count($pasientes) - 1) {
                                echo '<BR>';
                            }
                        }
                        echo '<hr><button class="btn btn-info btn-sm" onclick="openModalDatosNino(' . $row['id'] . ')">Ver detalle</button>';
                    }
                    echo '</td>';
                    echo '<td>' . htmlspecialchars($acti) . '</td>';
                    echo '<td>' . htmlspecialchars($row['Registro']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['telefono']) . '</td>';
                    echo '<td>
                        <button class="btn btn-primary btn-sm" onclick="editUser(' . $row['id'] . ')">Editar</button>';
                    if ($row["activo"] == 1) {
                        echo ' - <button class="btn btn-danger btn-sm" onclick="deactivateUser(' . $row['id'] . ')">Desactivar</button>';
                    } else {
                        echo ' - <button class="btn btn-success btn-sm" onclick="deactivateUser(' . $row['id'] . ')">Activar</button>';
                    }


                    echo ' - <button class="btn btn-info btn-sm" onclick="agregarUser(' . $row['id'] . ')">Agregar niño</button>
                      </td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="DatosNino" tabindex="-1" aria-labelledby="DatosNinoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="DatosNinoLabel">Datos de los Niños</h5>
                <button type="button" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Activo</th>
                                <th>Edad</th>
                                <th>Observación</th>
                                <th>Fecha de Ingreso</th>
                            </tr>
                        </thead>
                        <tbody id="modalTableBody">
                            <!-- Los datos se llenarán dinámicamente aquí -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>

            </div>
        </div>
    </div>
</div>
<!-- Modal para editar usuario -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editId" name="id">
                    <div class="mb-3">
                        <label for="editName" class="form-label">Nombre*</label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editTelefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="editTelefono" name="telefono">
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNino" tabindex="-1" aria-labelledby="modalNino" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="registerForm" action="agregarNino.php" method="POST">
                <div class="modal-header">
                    <h2>Agregar</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">


                    <div class="mb-3">
                        <input type="text" value="0" class="form-control" id="idTutor" name="idTutor" hidden>
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre*</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edad" class="form-label">Edad*</label>
                        <input type="number" min="1" max="100" class="form-control" id="edad" name="edad" required>
                    </div>
                    <div class="mb-3">
                        <label for="FechaIngreso" class="form-label">Fecha ingreso*</label>
                        <input type="date" class="form-control" id="FechaIngreso" name="FechaIngreso" required>
                    </div>
                    <div class="mb-3">
                        <label for="Observaciones" class="form-label">Observaciones</label>
                        <input type="text" class="form-control" id="Observaciones" name="Observaciones">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Agregar</button>

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>

                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal `id`, `name`, `telefono`, `correo` -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="registerForm" action="insertcliente.php" method="POST">
                <div class="modal-header">
                    <h2>Registro de cliente</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">


                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Registrar</button>

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>

                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="editModalpacien" tabindex="-1" role="dialog" aria-labelledby="editModalpacien"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Editar niño</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editForm" action="editar.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="id" name="id">
                    <input type="hidden" id="index" name="index">
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre">
                    </div>
                    <div class="form-group">
                        <label for="edade">Edad</label>
                        <input type="number" class="form-control" id="edade" name="edade">
                    </div>
                    <div class="form-group">
                        <label for="activoe">Activo</label>
                        <select class="form-control" id="activoe" name="activoe">
                            <option value="1">Activado</option>
                            <option value="0">Desactivado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="FechaIngresoE" class="form-label">Fecha ingreso*</label>
                        <input type="date" class="form-control" id="FechaIngresoE" name="FechaIngreso" required>
                    </div>
                    <div class="form-group">
                        <label for="ObservacionesE" class="form-label">Observaciones</label>
                        <input type="text" class="form-control" id="ObservacionesE" name="Observaciones">
                    </div>
                </div>

                <div class="modal-footer">

                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
include '../Modulos/footer.php';
?>

<script>editModalpacien
    function openModal(id, nombre) {
        $.ajax({
            url: 'getpaciente.php',
            method: 'GET',
            data: { idtutor: id, name: nombre },
            dataType: 'json',
            success: function (response) {
                document.getElementById('id').value = response.id;
                document.getElementById('nombre').value = response.name;
                document.getElementById('edade').value = response.edad;
                document.getElementById('activoe').value = response.activo;
                document.getElementById('FechaIngresoE').value = response.FechaIngreso;
                document.getElementById('ObservacionesE').value = response.Observacion;
                $('#editModalpacien').modal('show');
            },
            error: function () {
                alert('Error al obtener los datos del paciente.');
            }
        });
    }
    function agregarUser(id) {

        document.getElementById('idTutor').value = id;

        new bootstrap.Modal(document.getElementById('modalNino')).show();

    }
    function editUser(id) {
        fetch(`getCliente.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('editId').value = data.id;
                document.getElementById('editName').value = data.name;
                document.getElementById('editTelefono').value = data.telefono;
                new bootstrap.Modal(document.getElementById('editModal')).show();
            });
    }

    function deactivateUser(id) {
        if (confirm('¿Estás seguro de que deseas desactivar/activar este usuario?')) {
            fetch(`deactivateUser.php?id=${id}`, { method: 'POST' })
                .then(response => response.json())
                .then(result => {
                    console.log(result);
                    if (result.success) {
                        alert('Usuario actualizado con éxito');
                        location.reload();
                    } else {
                        alert('Error: ' + result.error);
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    }

    document.getElementById('editForm').addEventListener('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(this);
        fetch('updateUser.php', {
            method: 'POST',
            body: formData
        }).then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Usuario actualizado correctamente.');
                    location.reload();
                } else {
                    alert('Error al actualizar el usuario.');
                }
            });
    });
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
    function filterTable() {
        document.getElementById('filterForm').submit();
    }
    function openModalDatosNino(idTutor) {
        $.ajax({
            url: 'getDatosNino.php',
            type: 'GET',
            data: { idTutor: idTutor },
            success: function (data) {
                var datos = JSON.parse(data);
                var modalTableBody = $('#modalTableBody');
                modalTableBody.empty(); // Limpiar cualquier dato anterior
                datos.forEach(function (nino) {
                    var row = '<tr>' +
                        '<td>' + nino.id + '</td>' +
                        '<td>' + nino.name + '</td>' +
                        '<td>' + (nino.activo == 1 ? 'Sí' : 'No') + '</td>' +
                        '<td>' + nino.edad + '</td>' +
                        '<td>' + nino.Observacion + '</td>' +
                        '<td>' + nino.FechaIngreso + '</td>' +
                        '</tr>';
                    modalTableBody.append(row);
                });
                $('#DatosNino').modal('show');
            }
        });
    }

    function htmlspecialchars(str) {
        if (str === null || str === undefined) {
            return '';
        }
        return str.replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>