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
                $sql = "SELECT c.`id`,
                                   c.`name`,
                                   c.`activo`,
                                   c.`telefono`,
                                   GROUP_CONCAT(
                                       CONCAT_WS('::', n.id, n.name, COALESCE(n.saldo_paquete, 0))
                                       SEPARATOR '||'
                                   ) as Pacientes,
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

                $result = $conn->query($sql);

                // Generación de filas de la tabla
                $rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;

                while ($row = $result->fetch_assoc()) {
                    $acti = $row["activo"] == 1 ? 'Sí' : 'No';
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                    echo '<td>';
                    if ($row['Pacientes'] !== null) {
                        $pacientes = explode('||', (string) $row['Pacientes']);
                        $totalPacientes = count($pacientes);

                        foreach ($pacientes as $index => $pacienteRaw) {
                            $pacienteRaw = trim($pacienteRaw);
                            if ($pacienteRaw === '') {
                                continue;
                            }

                            $partes = explode('::', $pacienteRaw);
                            $pacienteId = isset($partes[0]) ? (int) $partes[0] : 0;
                            $pacienteNombre = $partes[1] ?? '';
                            $pacienteSaldo = isset($partes[2]) ? (float) $partes[2] : 0.0;

                            $pacienteNombreHtml = htmlspecialchars($pacienteNombre, ENT_QUOTES, 'UTF-8');
                            $pacienteSaldoHtml = '$' . number_format($pacienteSaldo, 2);
                            $pacienteNombreJs = json_encode($pacienteNombre, JSON_HEX_APOS | JSON_HEX_QUOT);
                            $pacienteSaldoJs = json_encode($pacienteSaldo);

                            echo '<div class="mb-3">';
                            echo '<div class="fw-semibold">' . $pacienteNombreHtml . '</div>';
                            echo '<div class="text-muted small">Saldo: ' . $pacienteSaldoHtml . '</div>';
                            echo '<div class="d-flex flex-wrap gap-3 mt-2">';
                            echo '<a href="#" class="link-primary text-decoration-none" onclick="openModal(' . (int) $row['id'] . ', ' . $pacienteNombreJs . '); return false;">Editar</a>';

                            if (in_array($rolUsuario, [3, 5], true)) {
                                echo '<a href="#" class="text-success text-decoration-none" onclick="openSaldoModal(' . $pacienteId . ', ' . $pacienteNombreJs . ', ' . $pacienteSaldoJs . '); return false;">Agregar saldo</a>';
                            }
                            echo '</div>';
                            echo '</div>';

                            if ($index < $totalPacientes - 1) {
                                echo '<hr class="my-2">';
                            }
                        }

                        echo '<hr><button class="btn btn-info btn-sm" onclick="openModalDatosNino(' . (int) $row['id'] . ')">Ver detalle</button>';
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
                $result->free();
                $conn->close();
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
<div class="modal fade" id="modalSaldo" tabindex="-1" aria-labelledby="modalSaldoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="saldoForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSaldoLabel">Agregar saldo al paciente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="saldoAlert" class="alert d-none" role="alert"></div>
                    <input type="hidden" id="saldoPacienteId">
                    <div class="mb-3">
                        <label class="form-label">Paciente</label>
                        <input type="text" class="form-control" id="saldoPacienteNombre" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Saldo actual</label>
                        <input type="text" class="form-control" id="saldoPacienteActual" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="saldoMonto">Monto a agregar</label>
                        <input type="number" class="form-control" id="saldoMonto" min="0" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="saldoComentario">Comentario (opcional)</label>
                        <textarea class="form-control" id="saldoComentario" rows="2" maxlength="255"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agregar saldo</button>
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
    const saldoModalElement = document.getElementById('modalSaldo');
    const saldoForm = document.getElementById('saldoForm');
    const saldoAlert = document.getElementById('saldoAlert');
    const saldoPacienteIdInput = document.getElementById('saldoPacienteId');
    const saldoPacienteNombreInput = document.getElementById('saldoPacienteNombre');
    const saldoPacienteActualInput = document.getElementById('saldoPacienteActual');
    const saldoMontoInput = document.getElementById('saldoMonto');
    const saldoComentarioInput = document.getElementById('saldoComentario');
    const saldoSubmitButton = saldoForm ? saldoForm.querySelector('button[type="submit"]') : null;
    const formatoMoneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

    function formatCurrency(value) {
        const numericValue = Number.parseFloat(value);
        return formatoMoneda.format(Number.isFinite(numericValue) ? numericValue : 0);
    }

    function hideSaldoAlert() {
        if (!saldoAlert) {
            return;
        }

        saldoAlert.classList.add('d-none');
        saldoAlert.classList.remove('alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        saldoAlert.textContent = '';
    }

    function showSaldoAlert(message, variant) {
        if (!saldoAlert) {
            return;
        }

        const variants = ['alert-success', 'alert-danger', 'alert-warning', 'alert-info'];
        saldoAlert.classList.remove('d-none');
        saldoAlert.classList.remove(...variants);

        const tone = variant ? 'alert-' + variant : 'alert-info';
        saldoAlert.classList.add(tone);
        saldoAlert.textContent = message;
    }

    function openSaldoModal(pacienteId, nombre, saldoActual) {
        if (!saldoForm || !saldoModalElement || !saldoPacienteIdInput || !saldoPacienteNombreInput || !saldoPacienteActualInput || !saldoMontoInput) {
            return;
        }

        hideSaldoAlert();

        saldoPacienteIdInput.value = pacienteId || '';
        saldoPacienteNombreInput.value = nombre || '';
        saldoPacienteActualInput.value = formatCurrency(saldoActual);
        saldoMontoInput.value = '';

        if (saldoComentarioInput) {
            saldoComentarioInput.value = '';
        }

        if (saldoSubmitButton) {
            saldoSubmitButton.disabled = false;
        }

        const modalInstance = bootstrap.Modal.getOrCreateInstance(saldoModalElement);
        modalInstance.show();
    }
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
    if (saldoForm) {
        saldoForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!saldoPacienteIdInput || !saldoMontoInput) {
                return;
            }

            const pacienteId = Number.parseInt(saldoPacienteIdInput.value, 10);
            const monto = Number.parseFloat(saldoMontoInput.value);
            const comentario = saldoComentarioInput ? saldoComentarioInput.value.trim() : '';

            if (!Number.isInteger(pacienteId) || pacienteId <= 0) {
                showSaldoAlert('No se encontró el identificador del paciente.', 'danger');
                return;
            }

            if (!Number.isFinite(monto) || monto <= 0) {
                showSaldoAlert('Ingresa un monto válido mayor a cero.', 'warning');
                return;
            }

            hideSaldoAlert();

            if (saldoSubmitButton) {
                saldoSubmitButton.disabled = true;
            }

            fetch('agregarSaldo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    nino_id: pacienteId,
                    monto: monto,
                    comentario: comentario
                })
            })
                .then(function (response) {
                    if (!response.ok) {
                        return response.json()
                            .then(function (payload) {
                                const message = payload && payload.error ? payload.error : 'No se pudo actualizar el saldo.';
                                throw new Error(message);
                            })
                            .catch(function (error) {
                                if (error instanceof Error) {
                                    throw error;
                                }
                                throw new Error('No se pudo actualizar el saldo.');
                            });
                    }

                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success) {
                        const errorMessage = payload && payload.error ? payload.error : 'No se pudo actualizar el saldo.';
                        throw new Error(errorMessage);
                    }

                    showSaldoAlert('Saldo actualizado correctamente.', 'success');

                    if (saldoPacienteActualInput && Object.prototype.hasOwnProperty.call(payload, 'nuevoSaldo')) {
                        saldoPacienteActualInput.value = formatCurrency(payload.nuevoSaldo);
                    }

                    const modalInstance = bootstrap.Modal.getInstance(saldoModalElement);
                    window.setTimeout(function () {
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                        window.location.reload();
                    }, 1200);
                })
                .catch(function (error) {
                    console.error(error);
                    showSaldoAlert(error.message || 'No se pudo actualizar el saldo.', 'danger');
                })
                .finally(function () {
                    if (saldoSubmitButton) {
                        saldoSubmitButton.disabled = false;
                    }
                });
        });
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
    window.openSaldoModal = openSaldoModal;
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