<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
include '../Modulos/head.php';
?>

<div class="container mt-5">

</div>
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
    Registro de Usuario
</button>


<div class="container mt-5">
    <h2 class="mb-4">Lista de Usuarios</h2>
    <div class="table-responsive">

        <table class="table table-sm table-bordered" id="myTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Contraseña</th>
                    <th>Activo</th>
                    <th>Registro</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT usua.`id`, usua.`name`, `user`, `pass`, usua.`activo`, `registro`, `telefono`, `correo`, r.name AS rol
                    FROM `Usuarios` usua
                    INNER JOIN Rol r ON r.id = usua.IdRol";
                $result = $conn->query($sql);

                $rol = isset($_SESSION['rol']) ? $_SESSION['rol'] : 0;
                // Generación de filas de la tabla
                while ($row = $result->fetch_assoc()) {
                    $acti = $row["activo"] == 1 ? 'Sí' : 'No';
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['user']) . '</td>';
                    if ($rol == 3) {
                        echo '<td>' . htmlspecialchars($row['pass']) . '</td>';
                    } else {
                        echo '<td>*****</td>'; // Opcional: Mostrar asteriscos u otra indicación de ocultamiento
                    }
                    echo '<td>' . htmlspecialchars($acti) . '</td>';
                    echo '<td>' . htmlspecialchars($row['registro']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['telefono']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['correo']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['rol']) . '</td>';
                    echo '<td>
                        <button class="btn btn-primary btn-sm" onclick="editUser(' . $row['id'] . ')">Editar</button>';
                        if ($row["activo"] == 1){
                            echo ' - <button class="btn btn-danger btn-sm" onclick="deactivateUser(' . $row['id'] . ')">Desactivar</button>' ;
                         }  else{
                            echo ' - <button class="btn btn-success btn-sm" onclick="deactivateUser(' . $row['id'] . ')">Activar</button>' ;
                         }
                        
                    echo '</td>';
                    echo '</tr>';
                }
                $conn->close();
                ?>
            </tbody>
        </table>
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
                $.each(data, function (index, value) {
                    $select.append($('<option>', {
                        value: value.id,
                        text: value.name
                    }));
                });
                var $select2 = $('#editRol');
                $.each(data, function (index, value) {
                    $select2.append($('<option>', {
                        value: value.id,
                        text: value.name
                    }));
                });
                var rol = <?php echo isset($_SESSION['rol']) ? $_SESSION['rol'] : 0; ?>;

                if (rol !== 3) {
                    $('#IdRol option[value="3"]').remove();
                    $('#editRol option[value="3"]').remove();
                }
            },
            error: function () {
                alert('Error al obtener los roles');
            }
        });

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
                new bootstrap.Modal(document.getElementById('editModal')).show();
            });
    }

    function deactivateUser(id) {
        debugger
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