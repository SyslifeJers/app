<?php
include '../Modulos/head.php';

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
$puedeGestionarActivaciones = in_array($rolUsuario, [3, 5], true);
$puedeAjustarSaldoDirecto = $puedeGestionarActivaciones;
$puedeSolicitarAjusteSaldo = $rolUsuario > 0;
$textoBotonSaldoDetalle = $puedeAjustarSaldoDirecto ? 'Ajustar saldo' : 'Solicitar ajuste de saldo';
$textoTituloModalSaldo = $puedeAjustarSaldoDirecto ? 'Ajustar saldo del paciente' : 'Solicitar ajuste de saldo';
$textoBotonModalSaldo = $puedeAjustarSaldoDirecto ? 'Aplicar ajuste' : 'Enviar solicitud';
$ocultarNinosInactivos = ($rolUsuario === 1);
?>

<div class="container mt-5">

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

</div>

<div class="container mt-5">

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 py-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                <div>
                    <h2 class="h4 mb-1 d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-circle p-2">
                            <i class="fas fa-user-friends"></i>
                        </span>
                        Lista de Clientes (Papás o tutores)
                    </h2>
                    <p class="text-muted mb-0 small">Consulta rápidamente a los tutores, su estado y a los pacientes asignados.</p>
                </div>
                <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2">
                    <button type="button"
                        class="btn btn-primary d-flex align-items-center justify-content-center gap-2 w-100 w-lg-auto"
                        data-bs-toggle="modal" data-bs-target="#exampleModal">
                        <i class="fas fa-user-plus"></i>
                        <span>Registrar tutor</span>
                    </button>
                    <form id="filterForm" method="post" class="d-flex w-100 w-lg-auto">
                        <label class="visually-hidden" for="filter">Filtrar por estado</label>
                        <div class="input-group flex-fill">
                            <span class="input-group-text"><i class="fas fa-filter"></i></span>
                            <select id="filter" name="filter" class="form-select" onchange="filterTable()">
                                <option value="all" <?php echo (isset($_POST['filter']) && $_POST['filter'] == 'all') ? 'selected' : ''; ?>>Todos</option>
                                <option value="active" <?php echo (isset($_POST['filter']) && $_POST['filter'] == 'active') ? 'selected' : ''; ?>>Activos</option>
                                <option value="inactive" <?php echo (isset($_POST['filter']) && $_POST['filter'] == 'inactive') ? 'selected' : ''; ?>>Desactivados</option>
                            </select>
                        </div>
                    </form>
                    <div class="input-group flex-fill">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="search" id="searchInput" class="form-control" placeholder="Buscar por nombre o teléfono">
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0" id="myTable">
                <thead class="table-light">
                    <tr>
                        <th class="text-uppercase small text-muted">ID</th>
                        <th class="text-uppercase small text-muted">Nombre</th>
                        <th class="text-uppercase small text-muted">Pacientes</th>
                        <th class="text-uppercase small text-muted">Teléfono</th>
                        <th class="text-uppercase small text-muted">Acciones</th>
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
                       FROM `Clientes` c";


                    $sql .= " LEFT JOIN `nino` n ON n.`idtutor` = c.`id`";
      
                // Definir el filtro
                $filter = isset($_POST['filter']) ? $_POST['filter'] : 'all';

                // Aplicar filtro según el estado
                if ($filter == 'active') {
                    $sql .= " WHERE c.`activo` = 1";
                } elseif ($filter == 'inactive') {
                    $sql .= " WHERE c.`activo` = 0";
                }

                $sql .= " GROUP BY c.`id`";

                $sql .= " ORDER BY c.`activo` DESC, c.`id` DESC";
                $result = $conn->query($sql);

                if ($result === false) {
                    echo '<tr><td colspan="6" class="text-center text-danger">No se pudo obtener la lista de clientes.</td></tr>';
                } else {
                    while ($row = $result->fetch_assoc()) {
                    $fechaRegistro = '';
                    if (!empty($row['Registro'])) {
                        $registroFecha = new DateTime($row['Registro']);
                        $fechaRegistro = $registroFecha->format('d/m/Y');
                    }
                    $rowStateClass = $row['activo'] == 1 ? 'bg-success-subtle' : 'bg-secondary-subtle';
                    echo '<tr class="' . $rowStateClass . '" data-search-text="' . htmlspecialchars(strtolower($row['name'] . ' ' . $row['telefono']), ENT_QUOTES, 'UTF-8') . '">';
                    echo '<td class="fw-semibold">#' . htmlspecialchars($row['id']) . '</td>';
                    echo '<td>';
                    echo '<div class="d-flex flex-column">';
                    echo '<span class="fw-semibold">' . htmlspecialchars($row['name']) . '</span>';
                    echo '<span class="text-muted small">Tutor ID: ' . htmlspecialchars($row['id']) . '</span>';
                    echo '<span class="text-muted small">Fecha de Registro: ' . $fechaRegistro . '</span>';
                    echo '</div>';
                    echo '</td>';
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
                            $pacienteNombreAttr = htmlspecialchars($pacienteNombre, ENT_QUOTES, 'UTF-8');
                            $pacienteSaldoAttr = htmlspecialchars((string) $pacienteSaldo, ENT_QUOTES, 'UTF-8');

                            echo '<div class="mb-3">';
                            echo '<div class="d-flex flex-column gap-2">';
                            echo '<a href="#" class="child-detail-trigger text-decoration-none fw-semibold d-inline-flex align-items-center gap-2"'
                                . ' data-tutor-id="' . (int) $row['id'] . '"'
                                . ' data-nino-id="' . $pacienteId . '"'
                                . ' data-paciente-nombre="' . $pacienteNombreAttr . '"'
                                . ' data-paciente-saldo="' . $pacienteSaldoAttr . '"'
                                . ' data-tutor-nombre="' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '"'
                                . ' onclick="openModalDatosNino(this); return false;">'
                                . '<i class="fas fa-child text-info"></i>'
                                . '<span>' . $pacienteNombreHtml . '</span>'
                                . '</a>';
                            echo '<span class="badge align-self-start bg-secondary-subtle text-secondary-emphasis"><i class="fas fa-wallet me-1"></i>' . $pacienteSaldoHtml . '</span>';
                            echo '</div>';
                            echo '<div class="d-flex flex-wrap gap-3 mt-2">';
                            echo '</div>';
                            echo '</div>';

                            if ($index < $totalPacientes - 1) {
                                echo '<hr class="my-2">';
                            }
                        }

                    }
                    else {
                        echo '<span class="text-muted small">Sin pacientes registrados.</span>';
                    }
                    echo '</td>';
                    $telefono = !empty($row['telefono']) ? $row['telefono'] : 'Sin registrar';
                    echo '<td>';
                    echo '<div class="d-flex flex-column">';
                    echo '<span class="fw-semibold">' . htmlspecialchars($telefono) . '</span>';
                    echo '<span class="text-muted small"><i class="fas fa-phone me-1"></i>Contacto</span>';
                    echo '</div>';
                    echo '</td>';
                    echo '<td class="text-nowrap">';
                    echo '<div class="btn-group" role="group" aria-label="Acciones">';
                    echo '<button class="btn btn-outline-primary btn-sm" onclick="editUser(' . $row['id'] . ')"><i class="fas fa-edit me-1"></i>Editar</button>';

                    if ($puedeGestionarActivaciones) {
                        if ($row["activo"] == 1) {
                            echo '<button class="btn btn-outline-danger btn-sm" onclick="deactivateUser(' . $row['id'] . ')"><i class="fas fa-user-slash me-1"></i>Desactivar</button>';
                        } else {
                            echo '<button class="btn btn-outline-success btn-sm" onclick="deactivateUser(' . $row['id'] . ')"><i class="fas fa-user-check me-1"></i>Activar</button>';
                        }
                    }

                    echo '<button class="btn btn-outline-info btn-sm" onclick="agregarUser(' . $row['id'] . ')"><i class="fas fa-child me-1"></i>Agregar niño</button>';
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
            <div><i class="fas fa-info-circle me-1"></i> Usa el filtro o la búsqueda para localizar un tutor específico.</div>
            <div id="resultCount"></div>
        </div>
    </div>
</div>
<div class="modal fade" id="DatosNino" tabindex="-1" aria-labelledby="DatosNinoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="DatosNinoLabel">Detalle del paciente</h5>
                    <small class="text-muted" id="DatosNinoSubtitulo">Consulta y administra la información del menor.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="detalleNinoContenido" class="d-flex flex-column gap-3">
                    <div id="detalleNinoAlert" class="alert alert-warning d-none" role="alert"></div>
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted small">Nombre</dt>
                        <dd class="col-sm-8 fw-semibold" id="detalleNinoNombre">-</dd>
                        <dt class="col-sm-4 text-muted small">Tutor</dt>
                        <dd class="col-sm-8" id="detalleNinoTutor">-</dd>
                        <dt class="col-sm-4 text-muted small">Estado</dt>
                        <dd class="col-sm-8" id="detalleNinoEstado">-</dd>
                        <dt class="col-sm-4 text-muted small">Edad</dt>
                        <dd class="col-sm-8" id="detalleNinoEdad">-</dd>
                        <dt class="col-sm-4 text-muted small">Fecha de ingreso</dt>
                        <dd class="col-sm-8" id="detalleNinoFecha">-</dd>
                        <dt class="col-sm-4 text-muted small">Saldo</dt>
                        <dd class="col-sm-8" id="detalleNinoSaldo">-</dd>
                        <dt class="col-sm-4 text-muted small">Observaciones</dt>
                        <dd class="col-sm-8" id="detalleNinoObservaciones">-</dd>
                    </dl>
                </div>
            </div>
            <div class="modal-footer flex-column flex-sm-row gap-2">
                <button type="button" class="btn btn-secondary w-100 w-sm-auto" data-bs-dismiss="modal">Cerrar</button>
                <div class="d-flex gap-2 w-100 w-sm-auto">
                    <button type="button" class="btn btn-outline-primary flex-fill" id="detalleEditarBtn">
                        <i class="fas fa-edit me-1"></i>Editar
                    </button>
                    <?php if ($puedeSolicitarAjusteSaldo): ?>
                        <button type="button" class="btn btn-outline-success flex-fill" id="detalleSaldoBtn">
                            <i class="fas fa-wallet me-1"></i><?php echo htmlspecialchars($textoBotonSaldoDetalle, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endif; ?>
                </div>
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

<div class="modal fade" id="modalNino" tabindex="-1" aria-labelledby="modalNinoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <form id="registerForm" action="agregarNino.php" method="POST" class="child-register-form needs-validation" novalidate>
                <div class="modal-header bg-primary text-white">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center me-3"
                            style="width: 48px; height: 48px;">
                            <i class="fas fa-child fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0" id="modalNinoLabel">Agregar paciente infantil</h5>
                            <small class="text-white-50">Completa la información para registrar a un nuevo niño.</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body bg-light">
                    <input type="text" value="0" class="form-control" id="idTutor" name="idTutor" hidden>

                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Nombre*</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-signature"></i></span>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Nombre completo"
                                required>
                            <div class="invalid-feedback">Por favor, ingresa el nombre del niño.</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edad" class="form-label fw-semibold">Edad*</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-birthday-cake"></i></span>
                                <input type="number" min="1" max="100" class="form-control" id="edad" name="edad"
                                    placeholder="Edad en años" required>
                                <div class="invalid-feedback">La edad debe estar entre 1 y 100 años.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="FechaIngreso" class="form-label fw-semibold">Fecha ingreso*</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                <input type="date" class="form-control" id="FechaIngreso" name="FechaIngreso" required>
                                <div class="invalid-feedback">Selecciona la fecha de ingreso.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="Observaciones" class="form-label fw-semibold">Observaciones</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-comment-dots"></i></span>
                            <textarea class="form-control" id="Observaciones" name="Observaciones" rows="3"
                                placeholder="Información adicional opcional"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 d-flex justify-content-between">
                    <div class="text-muted small d-flex align-items-center">
                        <i class="fas fa-info-circle me-2"></i>
                        <span>Los campos marcados con * son obligatorios.</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cerrar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Agregar
                        </button>
                    </div>
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
                    <h5 class="modal-title" id="modalSaldoLabel"><?php echo htmlspecialchars($textoTituloModalSaldo, ENT_QUOTES, 'UTF-8'); ?></h5>
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
                        <label class="form-label" for="saldoMonto">Monto del ajuste</label>
                        <input type="number" class="form-control" id="saldoMonto" step="0.01" required>
                        <div class="form-text">Usa valores positivos para aumentar el saldo o negativos para reducirlo.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="saldoPacientePrevisto">Saldo estimado después del ajuste</label>
                        <input type="text" class="form-control" id="saldoPacientePrevisto" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="saldoComentario">Comentario (opcional)</label>
                        <textarea class="form-control" id="saldoComentario" rows="2" maxlength="255"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($textoBotonModalSaldo, ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal `id`, `name`, `telefono`, `correo` -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="registerForm" action="insertcliente.php" method="POST">
                <div class="modal-header">
                    <div>
                        <h2 class="mb-0">Ingreso de registro</h2>
                        <small class="text-muted">Datos del tutor o papá</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">Nombre(s) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                    placeholder="Nombre(s)" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Apellido paterno <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                    placeholder="Apellido paterno" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="second_last_name" class="form-label">Apellido materno <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card-alt"></i></span>
                                <input type="text" class="form-control" id="second_last_name" name="second_last_name"
                                    placeholder="Apellido materno" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="telefono" class="form-label">Teléfono <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="telefono" name="telefono"
                                    placeholder="Ej. 55 1234 5678" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="correo" class="form-label">Correo electrónico</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="correo" name="correo"
                                    placeholder="correo@ejemplo.com">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto text-muted small">
                        <span class="text-danger">*</span> Campos obligatorios
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Registrar
                    </button>
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
                    <?php if ($puedeGestionarActivaciones): ?>
                        <div class="form-group">
                            <label for="activoe" class="form-label">Estado</label>
                            <select class="form-control" id="activoe" name="activoe">
                                <option value="1">Activado</option>
                                <option value="0">Desactivado</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="estadoe" class="form-label">Estado</label>
                            <input type="text" class="form-control" id="estadoe" readonly>
                            <input type="hidden" id="activoe" name="activoe">
                        </div>
                    <?php endif; ?>
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
    const modalNinoElement = document.getElementById('modalNino');
    const modalNinoForm = modalNinoElement ? modalNinoElement.querySelector('.child-register-form') : null;
    const saldoModalElement = document.getElementById('modalSaldo');
    const saldoForm = document.getElementById('saldoForm');
    const saldoAlert = document.getElementById('saldoAlert');
    const saldoPacienteIdInput = document.getElementById('saldoPacienteId');
    const saldoPacienteNombreInput = document.getElementById('saldoPacienteNombre');
    const saldoPacienteActualInput = document.getElementById('saldoPacienteActual');
    const saldoPacientePrevistoInput = document.getElementById('saldoPacientePrevisto');
    const saldoMontoInput = document.getElementById('saldoMonto');
    const saldoComentarioInput = document.getElementById('saldoComentario');
    const saldoSubmitButton = saldoForm ? saldoForm.querySelector('button[type="submit"]') : null;
    const puedeAjustarSaldoDirecto = <?php echo $puedeAjustarSaldoDirecto ? 'true' : 'false'; ?>;
    const mensajeMontoInvalido = puedeAjustarSaldoDirecto
        ? 'Ingresa un monto distinto de cero para ajustar el saldo.'
        : 'Ingresa un monto distinto de cero para solicitar el ajuste.';
    const mensajeExitoSaldo = puedeAjustarSaldoDirecto
        ? 'Saldo actualizado correctamente.'
        : 'Solicitud enviada para aprobación. El saldo se actualizará cuando sea atendida.';
    const mensajeErrorSaldo = puedeAjustarSaldoDirecto
        ? 'No se pudo ajustar el saldo.'
        : 'No se pudo registrar la solicitud.';
    const saldoEndpoint = puedeAjustarSaldoDirecto ? 'ajustarSaldo.php' : 'solicitarAjusteSaldo.php';
    const formatoMoneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

    function formatCurrency(value) {
        const numericValue = Number.parseFloat(value);
        return formatoMoneda.format(Number.isFinite(numericValue) ? numericValue : 0);
    }

    if (modalNinoForm) {
        modalNinoForm.addEventListener('submit', function (event) {
            if (!modalNinoForm.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            modalNinoForm.classList.add('was-validated');
        });

        if (modalNinoElement) {
            modalNinoElement.addEventListener('hidden.bs.modal', function () {
                modalNinoForm.classList.remove('was-validated');
                modalNinoForm.reset();
            });
        }
    }

    function hideSaldoAlert() {
        if (!saldoAlert) {
            return;
        }

        saldoAlert.classList.add('d-none');
        saldoAlert.classList.remove('alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        saldoAlert.textContent = '';
    }

    function actualizarSaldoPrevisto() {
        if (!saldoPacienteActualInput || !saldoPacientePrevistoInput) {
            return;
        }

        const saldoActualNumerico = Number.parseFloat(saldoPacienteActualInput.dataset.saldoNumerico || '0');
        const montoNumerico = saldoMontoInput ? Number.parseFloat(saldoMontoInput.value) : Number.NaN;
        let saldoPrevisto = saldoActualNumerico;

        if (!Number.isNaN(montoNumerico)) {
            saldoPrevisto = saldoActualNumerico + montoNumerico;
        }

        saldoPacientePrevistoInput.value = formatCurrency(saldoPrevisto);
        saldoPacientePrevistoInput.dataset.saldoPrevisto = String(saldoPrevisto);
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

    if (saldoMontoInput) {
        saldoMontoInput.addEventListener('input', actualizarSaldoPrevisto);
        saldoMontoInput.addEventListener('change', actualizarSaldoPrevisto);
    }

    function openSaldoModal(pacienteOrigen, nombreArgumento, saldoArgumento) {
        if (!saldoForm || !saldoModalElement || !saldoPacienteIdInput || !saldoPacienteNombreInput || !saldoPacienteActualInput || !saldoMontoInput) {
            return;
        }

        let pacienteId = pacienteOrigen;
        let nombre = nombreArgumento;
        let saldoActual = saldoArgumento;

        if (pacienteOrigen && typeof pacienteOrigen === 'object' && 'dataset' in pacienteOrigen) {
            const dataset = pacienteOrigen.dataset;
            if (dataset.pacienteId) {
                const parsedId = Number.parseInt(dataset.pacienteId, 10);
                pacienteId = Number.isNaN(parsedId) ? dataset.pacienteId : parsedId;
            }
            if (dataset.pacienteNombre) {
                nombre = dataset.pacienteNombre;
            }
            if (dataset.pacienteSaldo) {
                const parsedSaldo = Number.parseFloat(dataset.pacienteSaldo);
                saldoActual = Number.isNaN(parsedSaldo) ? dataset.pacienteSaldo : parsedSaldo;
            }
        }

        hideSaldoAlert();

        saldoPacienteIdInput.value = pacienteId || '';
        saldoPacienteNombreInput.value = nombre || '';

        const saldoNumerico = Number.parseFloat(saldoActual);
        const saldoNormalizado = Number.isNaN(saldoNumerico) ? 0 : saldoNumerico;
        saldoPacienteActualInput.value = formatCurrency(saldoNormalizado);
        saldoPacienteActualInput.dataset.saldoNumerico = String(saldoNormalizado);
        saldoMontoInput.value = '';

        if (saldoComentarioInput) {
            saldoComentarioInput.value = '';
        }

        if (saldoPacientePrevistoInput) {
            saldoPacientePrevistoInput.value = formatCurrency(saldoNormalizado);
            saldoPacientePrevistoInput.dataset.saldoPrevisto = String(saldoNormalizado);
        }

        if (saldoSubmitButton) {
            saldoSubmitButton.disabled = false;
        }

        const modalInstance = bootstrap.Modal.getOrCreateInstance(saldoModalElement);
        modalInstance.show();

        actualizarSaldoPrevisto();
    }
    function openModal(origen, nombreArgumento) {
        let id = origen;
        let nombre = nombreArgumento;

        if (origen && typeof origen === 'object' && 'dataset' in origen) {
            const dataset = origen.dataset;
            if (dataset.tutorId) {
                const parsedId = Number.parseInt(dataset.tutorId, 10);
                id = Number.isNaN(parsedId) ? dataset.tutorId : parsedId;
            }
            if (dataset.pacienteNombre) {
                nombre = dataset.pacienteNombre;
            }
        }

        if (id === undefined || id === null || id === '') {
            console.error('No se proporcionó el identificador del tutor.');
            return;
        }

        $.ajax({
            url: 'getpaciente.php',
            method: 'GET',
            data: { idtutor: id, name: nombre || '' },
            dataType: 'json',
            success: function (response) {
                document.getElementById('id').value = response.id;
                document.getElementById('nombre').value = response.name;
                document.getElementById('edade').value = response.edad;
                const activoField = document.getElementById('activoe');
                if (activoField) {
                    activoField.value = response.activo;
                }
                const estadoField = document.getElementById('estadoe');
                if (estadoField) {
                    const activoActual = Number.parseInt(response.activo, 10) === 1 ? 'Activado' : 'Desactivado';
                    estadoField.value = activoActual;
                }
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

            if (!Number.isFinite(monto) || Math.abs(monto) < 0.01) {
                showSaldoAlert(mensajeMontoInvalido, 'warning');
                return;
            }

            hideSaldoAlert();

            if (saldoSubmitButton) {
                saldoSubmitButton.disabled = true;
            }

            fetch(saldoEndpoint, {
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
                                const message = payload && payload.error ? payload.error : mensajeErrorSaldo;
                                throw new Error(message);
                            })
                            .catch(function (error) {
                                if (error instanceof Error) {
                                    throw error;
                                }
                                throw new Error(mensajeErrorSaldo);
                            });
                    }

                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success) {
                        const errorMessage = payload && payload.error ? payload.error : mensajeErrorSaldo;
                        throw new Error(errorMessage);
                    }

                    showSaldoAlert(mensajeExitoSaldo, 'success');

                    if (puedeAjustarSaldoDirecto) {
                        if (saldoPacienteActualInput && Object.prototype.hasOwnProperty.call(payload, 'nuevoSaldo')) {
                            const nuevoSaldoNumerico = Number.parseFloat(payload.nuevoSaldo);
                            if (!Number.isNaN(nuevoSaldoNumerico)) {
                                saldoPacienteActualInput.value = formatCurrency(nuevoSaldoNumerico);
                                saldoPacienteActualInput.dataset.saldoNumerico = String(nuevoSaldoNumerico);
                                if (saldoPacientePrevistoInput) {
                                    saldoPacientePrevistoInput.value = formatCurrency(nuevoSaldoNumerico);
                                    saldoPacientePrevistoInput.dataset.saldoPrevisto = String(nuevoSaldoNumerico);
                                }
                            }
                        }
                    } else {
                        if (saldoPacienteActualInput && Object.prototype.hasOwnProperty.call(payload, 'saldoActual')) {
                            const saldoActualNumerico = Number.parseFloat(payload.saldoActual);
                            if (!Number.isNaN(saldoActualNumerico)) {
                                saldoPacienteActualInput.value = formatCurrency(saldoActualNumerico);
                                saldoPacienteActualInput.dataset.saldoNumerico = String(saldoActualNumerico);
                            }
                        }

                        if (saldoPacientePrevistoInput && Object.prototype.hasOwnProperty.call(payload, 'saldoSolicitado')) {
                            const saldoSolicitadoNumerico = Number.parseFloat(payload.saldoSolicitado);
                            if (!Number.isNaN(saldoSolicitadoNumerico)) {
                                saldoPacientePrevistoInput.value = formatCurrency(saldoSolicitadoNumerico);
                                saldoPacientePrevistoInput.dataset.saldoPrevisto = String(saldoSolicitadoNumerico);
                            }
                        }
                    }

                    if (saldoMontoInput) {
                        saldoMontoInput.value = '';
                    }

                    if (saldoComentarioInput) {
                        saldoComentarioInput.value = '';
                    }

                    actualizarSaldoPrevisto();
                })
                .catch(function (error) {
                    console.error(error);
                    showSaldoAlert(error.message || mensajeErrorSaldo, 'danger');
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
        const table = $('#myTable').DataTable({
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
            dom: 'tip',
            ordering: false
        });

        const searchInput = document.getElementById('searchInput');
        const resultCount = document.getElementById('resultCount');

        function updateResultCount() {
            if (!resultCount) {
                return;
            }
            const info = table.page.info();
            const total = info.recordsTotal || 0;
            const visibles = info.recordsDisplay || 0;
            const label = visibles === total
                ? `Mostrando ${visibles} ${visibles === 1 ? 'cliente' : 'clientes'}`
                : `Mostrando ${visibles} de ${total} clientes`;
            resultCount.textContent = label;
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                table.search(this.value).draw();
            });
        }

        table.on('draw', updateResultCount);
        updateResultCount();
    });
    function filterTable() {
        document.getElementById('filterForm').submit();
    }
    window.openModal = openModal;
    window.openSaldoModal = openSaldoModal;
    const detalleModalElement = document.getElementById('DatosNino');
    const detalleNombre = document.getElementById('detalleNinoNombre');
    const detalleTutor = document.getElementById('detalleNinoTutor');
    const detalleEstado = document.getElementById('detalleNinoEstado');
    const detalleEdad = document.getElementById('detalleNinoEdad');
    const detalleFecha = document.getElementById('detalleNinoFecha');
    const detalleSaldo = document.getElementById('detalleNinoSaldo');
    const detalleObservaciones = document.getElementById('detalleNinoObservaciones');
    const detalleAlert = document.getElementById('detalleNinoAlert');
    const detalleEditarBtn = document.getElementById('detalleEditarBtn');
    const detalleSaldoBtn = document.getElementById('detalleSaldoBtn');

    function resetDetalleNino() {
        if (detalleNombre) { detalleNombre.textContent = '-'; }
        if (detalleTutor) { detalleTutor.textContent = '-'; }
        if (detalleEstado) { detalleEstado.textContent = '-'; }
        if (detalleEdad) { detalleEdad.textContent = '-'; }
        if (detalleFecha) { detalleFecha.textContent = '-'; }
        if (detalleSaldo) { detalleSaldo.textContent = '-'; }
        if (detalleObservaciones) { detalleObservaciones.textContent = '-'; }
        if (detalleEditarBtn) {
            detalleEditarBtn.disabled = true;
            detalleEditarBtn.onclick = null;
        }
        if (detalleSaldoBtn) {
            detalleSaldoBtn.disabled = true;
            detalleSaldoBtn.onclick = null;
        }
    }

    function hideDetalleAlert() {
        if (!detalleAlert) {
            return;
        }
        detalleAlert.classList.add('d-none');
        detalleAlert.textContent = '';
    }

    function showDetalleAlert(message) {
        if (!detalleAlert) {
            return;
        }
        detalleAlert.classList.remove('d-none');
        detalleAlert.textContent = message;
    }

    function encontrarDetalleNino(datos, ninoId, pacienteNombre) {
        if (!Array.isArray(datos) || datos.length === 0) {
            return null;
        }

        if (Number.isInteger(ninoId) && ninoId > 0) {
            const porId = datos.find(function (item) {
                return Number.parseInt(item.id, 10) === ninoId;
            });
            if (porId) {
                return porId;
            }
        }

        if (pacienteNombre) {
            const porNombre = datos.find(function (item) {
                return (item.name || '').toLowerCase() === pacienteNombre.toLowerCase();
            });
            if (porNombre) {
                return porNombre;
            }
        }

        return datos[0];
    }

    function openModalDatosNino(origen) {
        let tutorId = origen;
        let ninoId = null;
        let pacienteNombre = '';
        let pacienteSaldo = '';
        let tutorNombre = '';

        if (origen && typeof origen === 'object' && 'dataset' in origen) {
            const dataset = origen.dataset;
            if (dataset.tutorId) {
                const parsedTutor = Number.parseInt(dataset.tutorId, 10);
                tutorId = Number.isNaN(parsedTutor) ? dataset.tutorId : parsedTutor;
            }
            if (dataset.ninoId) {
                const parsedNino = Number.parseInt(dataset.ninoId, 10);
                ninoId = Number.isNaN(parsedNino) ? null : parsedNino;
            }
            if (dataset.pacienteNombre) {
                pacienteNombre = dataset.pacienteNombre;
            }
            if (dataset.pacienteSaldo) {
                pacienteSaldo = dataset.pacienteSaldo;
            }
            if (dataset.tutorNombre) {
                tutorNombre = dataset.tutorNombre;
            }
        }

        if (typeof tutorId === 'string') {
            const parsed = Number.parseInt(tutorId, 10);
            tutorId = Number.isNaN(parsed) ? 0 : parsed;
        }

        resetDetalleNino();
        hideDetalleAlert();

        if (!Number.isInteger(tutorId) || tutorId <= 0) {
            showDetalleAlert('No se pudo identificar al tutor del paciente.');
            if (detalleModalElement) {
                bootstrap.Modal.getOrCreateInstance(detalleModalElement).show();
            }
            return;
        }

        $.ajax({
            url: 'getDatosNino.php',
            type: 'GET',
            data: { idTutor: tutorId },
            success: function (data) {
                let datos = [];
                if (Array.isArray(data)) {
                    datos = data;
                } else {
                    try {
                        datos = JSON.parse(data);
                    } catch (error) {
                        datos = [];
                    }
                }

                if (!Array.isArray(datos) || datos.length === 0) {
                    showDetalleAlert('No se encontró información para el paciente seleccionado.');
                }

                const detalle = encontrarDetalleNino(datos, ninoId, pacienteNombre);

                const nombreDetalle = detalle && detalle.name ? detalle.name : pacienteNombre;
                if (detalleNombre) {
                    detalleNombre.textContent = nombreDetalle || 'Sin nombre';
                }

                if (detalleTutor) {
                    detalleTutor.textContent = tutorNombre || ('Tutor #' + tutorId);
                }

                if (detalleEstado) {
                    const estado = detalle && Object.prototype.hasOwnProperty.call(detalle, 'activo')
                        ? (Number.parseInt(detalle.activo, 10) === 1 ? 'Activo' : 'Desactivado')
                        : 'Sin registro';
                    detalleEstado.textContent = estado;
                }

                if (detalleEdad) {
                    const edad = detalle && Object.prototype.hasOwnProperty.call(detalle, 'edad') ? detalle.edad : '';
                    detalleEdad.textContent = edad ? edad + ' años' : 'Sin registrar';
                }

                if (detalleFecha) {
                    const fecha = detalle && Object.prototype.hasOwnProperty.call(detalle, 'FechaIngreso')
                        ? detalle.FechaIngreso
                        : '';
                    detalleFecha.textContent = fecha ? fecha : 'Sin registrar';
                }

                const saldoNumerico = (function () {
                    if (pacienteSaldo !== '') {
                        const saldoDesdeDataset = Number.parseFloat(pacienteSaldo);
                        if (!Number.isNaN(saldoDesdeDataset)) {
                            return saldoDesdeDataset;
                        }
                    }
                    if (detalle && Object.prototype.hasOwnProperty.call(detalle, 'saldo_paquete')) {
                        const saldoDetalle = Number.parseFloat(detalle.saldo_paquete);
                        if (!Number.isNaN(saldoDetalle)) {
                            return saldoDetalle;
                        }
                    }
                    return 0;
                }());

                if (detalleSaldo) {
                    detalleSaldo.textContent = formatCurrency(saldoNumerico);
                }

                if (detalleObservaciones) {
                    const observaciones = detalle && Object.prototype.hasOwnProperty.call(detalle, 'Observacion')
                        ? detalle.Observacion
                        : '';
                    detalleObservaciones.textContent = observaciones ? observaciones : 'Sin observaciones registradas.';
                }

                const nombreParaAcciones = nombreDetalle || pacienteNombre;
                const idParaAcciones = detalle && Object.prototype.hasOwnProperty.call(detalle, 'id')
                    ? Number.parseInt(detalle.id, 10)
                    : (Number.isInteger(ninoId) ? ninoId : 0);

                if (detalleEditarBtn) {
                    detalleEditarBtn.disabled = !nombreParaAcciones;
                    if (nombreParaAcciones) {
                        detalleEditarBtn.onclick = function () {
                            openModal({
                                dataset: {
                                    tutorId: tutorId,
                                    pacienteNombre: nombreParaAcciones
                                }
                            });
                        };
                    }
                }

                if (detalleSaldoBtn) {
                    detalleSaldoBtn.disabled = !nombreParaAcciones || !idParaAcciones;
                    if (!detalleSaldoBtn.disabled) {
                        detalleSaldoBtn.onclick = function () {
                            openSaldoModal({
                                dataset: {
                                    pacienteId: idParaAcciones,
                                    pacienteNombre: nombreParaAcciones,
                                    pacienteSaldo: saldoNumerico
                                }
                            });
                        };
                    }
                }

                if (detalleModalElement) {
                    bootstrap.Modal.getOrCreateInstance(detalleModalElement).show();
                }
            },
            error: function () {
                showDetalleAlert('Ocurrió un error al obtener la información del paciente.');
                if (detalleModalElement) {
                    bootstrap.Modal.getOrCreateInstance(detalleModalElement).show();
                }
            }
        });
    }

    window.openModalDatosNino = openModalDatosNino;

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