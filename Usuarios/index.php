<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
include '../Modulos/head.php';

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
if ($rolUsuario === 2) {
    http_response_code(403);
    ?>
    <div class="container mt-5">
        <div class="alert alert-warning" role="alert">
            No tienes permiso para acceder al catálogo de psicólogos.
        </div>
    </div>
    <?php
    include '../Modulos/footer.php';
    exit;
}
?>

<div class="container mt-5">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 py-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                <div>
                    <h2 class="h4 mb-1 d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-circle p-2">
                            <i class="fas fa-user-shield"></i>
                        </span>
                        Lista de Usuarios del Sistema
                    </h2>
                    <p class="text-muted mb-0 small">Consulta, registra y administra a los usuarios con acceso a la plataforma.</p>
                </div>
                <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2 w-100 w-lg-auto">
                    <div class="input-group flex-fill">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="search" id="searchInput" class="form-control" placeholder="Buscar por nombre o usuario">
                    </div>
                    <button type="button" class="btn btn-primary d-flex align-items-center justify-content-center gap-2"
                        data-bs-toggle="modal" data-bs-target="#exampleModal">
                        <i class="fas fa-user-plus"></i>
                        <span>Registrar usuario</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="myTable">
                <thead class="table-light">
                    <tr>
                        <th class="text-uppercase small text-muted">ID</th>
                        <th class="text-uppercase small text-muted">Nombre</th>
                        <th class="text-uppercase small text-muted">Usuario</th>
                        <th class="text-uppercase small text-muted">Contraseña</th>
                        <th class="text-uppercase small text-muted">Estado</th>
                        <th class="text-uppercase small text-muted">Registro</th>
                        <th class="text-uppercase small text-muted">Teléfono</th>
                        <th class="text-uppercase small text-muted">Correo</th>
                        <th class="text-uppercase small text-muted">Rol</th>
                        <th class="text-uppercase small text-muted">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sql = "SELECT usua.`id`, usua.`name`, `user`, `pass`, usua.`activo`, `registro`, `telefono`, `correo`, r.name AS rol
                        FROM `Usuarios` usua
                        INNER JOIN Rol r ON r.id = usua.IdRol";
                $result = $conn->query($sql);

                $rol = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;

                if ($result === false) {
                    echo '<tr><td colspan="10" class="text-center text-danger">No se pudo obtener la lista de usuarios.</td></tr>';
                } else {
                    while ($row = $result->fetch_assoc()) {
                        $estaActivo = (int) $row['activo'] === 1;
                        $acti = $estaActivo ? 'Activo' : 'Inactivo';
                        $rowStateClass = $estaActivo ? 'bg-success-subtle' : 'bg-secondary-subtle';

                        $fechaRegistro = '';
                        if (!empty($row['registro'])) {
                            $registroFecha = new DateTime($row['registro']);
                            $fechaRegistro = $registroFecha->format('d/m/Y');
                        }

                        $usuarioId = (int) $row['id'];
                        $nombre = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                        $usuario = htmlspecialchars($row['user'], ENT_QUOTES, 'UTF-8');
                        $password = htmlspecialchars($row['pass'], ENT_QUOTES, 'UTF-8');
                        $rolNombre = htmlspecialchars($row['rol'], ENT_QUOTES, 'UTF-8');
                        $estadoTexto = htmlspecialchars($acti, ENT_QUOTES, 'UTF-8');

                        $telefonoTexto = trim((string) ($row['telefono'] ?? ''));
                        $correoTexto = trim((string) ($row['correo'] ?? ''));

                        $telefonoHtml = $telefonoTexto !== ''
                            ? htmlspecialchars($telefonoTexto, ENT_QUOTES, 'UTF-8')
                            : '<span class="text-muted">Sin registrar</span>';

                        $correoHtml = $correoTexto !== ''
                            ? htmlspecialchars($correoTexto, ENT_QUOTES, 'UTF-8')
                            : '<span class="text-muted">Sin registrar</span>';

                        $fechaRegistroHtml = $fechaRegistro !== ''
                            ? htmlspecialchars($fechaRegistro, ENT_QUOTES, 'UTF-8')
                            : '<span class="text-muted">Sin dato</span>';

                        echo '<tr class="' . $rowStateClass . '">';
                        echo '<td class="fw-semibold">#' . htmlspecialchars((string) $usuarioId, ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>';
                        echo '<div class="d-flex flex-column">';
                        echo '<span class="fw-semibold">' . $nombre . '</span>';
                        echo '<span class="text-muted small">ID usuario: ' . htmlspecialchars((string) $usuarioId, ENT_QUOTES, 'UTF-8') . '</span>';
                        echo '</div>';
                        echo '</td>';
                        echo '<td><span class="fw-semibold">' . $usuario . '</span></td>';

                        if ($rol === 3) {
                            echo '<td><code>' . $password . '</code></td>';
                        } else {
                            echo '<td><span class="text-muted">*****</span></td>';
                        }

                        $badgeClass = $estaActivo ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis';
                        $badgeIcon = $estaActivo ? 'fa-user-check' : 'fa-user-slash';
                        echo '<td><span class="badge ' . $badgeClass . ' d-inline-flex align-items-center gap-2"><i class="fas ' . $badgeIcon . '"></i>' . $estadoTexto . '</span></td>';

                        echo '<td>' . $fechaRegistroHtml . '</td>';
                        echo '<td>' . $telefonoHtml . '</td>';
                        echo '<td>' . $correoHtml . '</td>';

                        echo '<td><span class="badge bg-primary-subtle text-primary-emphasis">' . $rolNombre . '</span></td>';

                        echo '<td class="text-nowrap">';
                        echo '<div class="btn-group" role="group" aria-label="Acciones">';
                        echo '<button class="btn btn-outline-primary btn-sm" onclick="editUser(' . $usuarioId . ')"><i class="fas fa-edit me-1"></i>Editar</button>';
                        if ($estaActivo) {
                            echo '<button class="btn btn-outline-danger btn-sm" onclick="deactivateUser(' . $usuarioId . ')"><i class="fas fa-user-slash me-1"></i>Desactivar</button>';
                        } else {
                            echo '<button class="btn btn-outline-success btn-sm" onclick="deactivateUser(' . $usuarioId . ')"><i class="fas fa-user-check me-1"></i>Activar</button>';
                        }
                        echo '</div>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    $result->free();
                }
                $conn->close();
                ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-0 text-muted small d-flex justify-content-between flex-column flex-sm-row gap-2">
            <div><i class="fas fa-info-circle me-1"></i> Usa la búsqueda para localizar rápidamente un usuario específico.</div>
            <div id="resultCount"></div>
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
                        <label for="editName" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editUser" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="editUser" name="user" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPass" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="editPass" name="pass" required>
                    </div>
                    <div class="mb-3">
                        <label for="editTelefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="editTelefono" name="telefono">
                    </div>
                    <div class="mb-3">
                        <label for="editCorreo" class="form-label">Correo</label>
                        <input type="email" class="form-control" id="editCorreo" name="correo">
                    </div>
                    <div class="mb-3">
                        <label for="editRol" class="form-label">Rol</label>
                        <select class="form-select" id="editRol" name="editRol" required>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editColor" class="form-label">Color</label>
                        <select class="form-select" id="editColor" name="color_id">
                            <option value="">Sin color asignado</option>
                        </select>
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


<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="registerForm" action="insertuser.php" method="POST" onsubmit="return validateForm();">
                <div class="modal-header">
                    <h2>Registro de Usuario</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">


                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre*</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="user" class="form-label">Usuario*</label>
                        <input type="text" class="form-control" id="user" name="user" required>
                    </div>
                    <div class="mb-3">
                        <label for="pass" class="form-label">Contraseña*</label>
                        <input type="password" class="form-control" id="pass" name="pass" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_pass" class="form-label">Confirmar Contraseña*</label>
                        <input type="password" class="form-control" id="confirm_pass" name="confirm_pass" required>
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono">
                    </div>
                    <div class="mb-3">
                        <label for="correo" class="form-label">Correo</label>
                        <input type="email" class="form-control" id="correo" name="correo">
                    </div>
                    <div class="mb-3">
                        <label for="IdRol" class="form-label">ID Rol</label>
                        <select class="form-select" id="IdRol" name="IdRol" required>
                            <!-- Las opciones se llenarán dinámicamente desde la base de datos -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="color_id" class="form-label">Color</label>
                        <select class="form-select" id="color_id" name="color_id">
                            <option value="">Sin color asignado</option>
                        </select>
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

<?php
include '../Modulos/footer.php';
?>

<script>
    $(document).ready(function () {
        $.ajax({
            url: '../getRoles.php',
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                var $select = $('#IdRol');
                $select.empty();
                $.each(data, function (index, value) {
                    $select.append($('<option>', {
                        value: value.id,
                        text: value.name
                    }));
                });
                var $select2 = $('#editRol');
                $select2.empty();
                $.each(data, function (index, value) {
                    $select2.append($('<option>', {
                        value: value.id,
                        text: value.name
                    }));
                });
                var rol = <?php echo isset($_SESSION['rol']) ? $_SESSION['rol'] : 0; ?>;

                if (rol !== 3) {
                    $('#IdRol option[value="3"]').remove();
                    $('#IdRol option[value="4"]').remove();
                    $('#editRol option[value="3"]').remove();
                    $('#editRol option[value="4"]').remove();
                }
            },
            error: function () {
                alert('Error al obtener los roles');
            }
        });

        $.ajax({
            url: '../getColores.php',
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                var $colorSelect = $('#color_id');
                var $editColorSelect = $('#editColor');

                $colorSelect.empty().append($('<option>', {
                    value: '',
                    text: 'Sin color asignado'
                }));

                $editColorSelect.empty().append($('<option>', {
                    value: '',
                    text: 'Sin color asignado'
                }));

                $.each(data, function (index, value) {
                    var option = $('<option>', {
                        value: value.id,
                        text: value.nombre + ' (' + value.codigo_hex + ')'
                    });
                    $colorSelect.append(option.clone());
                    $editColorSelect.append(option);
                });
            },
            error: function () {
                alert('Error al obtener los colores disponibles');
            }
        });
        var dataTable = $('#myTable').DataTable({
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
                infoFiltered: '(Buscamos en _MAX_ resultados)'
            },
            dom: 'tip',
            responsive: true
        });

        function updateResultCount() {
            var info = dataTable.page.info();
            var mensaje = 'Mostrando ' + info.recordsDisplay + ' usuario';
            mensaje += info.recordsDisplay === 1 ? '' : 's';
            if (info.recordsDisplay !== info.recordsTotal) {
                mensaje += ' filtrados de ' + info.recordsTotal;
            }
            $('#resultCount').text(mensaje);
        }

        $('#searchInput').on('keyup change', function () {
            dataTable.search(this.value).draw();
        });

        dataTable.on('draw', updateResultCount);
        updateResultCount();
    });
    function editUser(id) {
        fetch(`getUser.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('editId').value = data.id;
                document.getElementById('editName').value = data.name;
                document.getElementById('editUser').value = data.user;
                document.getElementById('editPass').value = data.pass;
                document.getElementById('editTelefono').value = data.telefono;
                document.getElementById('editCorreo').value = data.correo;
                document.getElementById('editRol').value = data.IdRol;
                var editColorSelect = document.getElementById('editColor');
                if (data.color_id === null || data.color_id === undefined) {
                    editColorSelect.value = '';
                } else {
                    editColorSelect.value = String(data.color_id);
                }
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
                        alert('Cambio con éxito');
                        location.reload();
                    } else {
                        alert('Error: ' + result.error);
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    }
    function validateForm() {
        var pass = document.getElementById("pass").value;
        var confirm_pass = document.getElementById("confirm_pass").value;

        if (pass.length < 6) {
            alert("La contraseña debe tener al menos 6 caracteres.");
            return false;
        }

        if (pass !== confirm_pass) {
            alert("Las contraseñas no coinciden.");
            return false;
        }

        return true;
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
</script>
