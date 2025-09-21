<?php
include '../Modulos/head.php';

$rolUsuario = $_SESSION['rol'] ?? 0;
$mensaje = $_SESSION['solicitud_mensaje'] ?? null;
$tipoMensaje = $_SESSION['solicitud_tipo'] ?? 'success';
unset($_SESSION['solicitud_mensaje'], $_SESSION['solicitud_tipo']);

if (!in_array($rolUsuario, [3, 4])) {
    ?>
    <div class="container mt-4">
        <div class="alert alert-danger">No tienes permisos para acceder a esta sección.</div>
    </div>
    <?php
    include '../Modulos/footer.php';
    exit;
}

$tipoFiltro = $_GET['tipo'] ?? 'todas';
$filtroValido = in_array($tipoFiltro, ['todas', 'reprogramacion', 'cancelacion'], true) ? $tipoFiltro : 'todas';

$sql = "SELECT sr.id,
       sr.cita_id,
       sr.fecha_anterior,
       sr.nueva_fecha,
       sr.estatus,
       sr.tipo,
       sr.fecha_solicitud,
       sr.fecha_respuesta,
       sr.comentarios,
       c.Programado AS fecha_actual_cita,
       n.name AS paciente,
       usuP.name AS psicologo,
       solicitante.name AS solicitante,
       aprobador.name AS aprobador
FROM SolicitudReprogramacion sr
INNER JOIN Cita c ON c.id = sr.cita_id
INNER JOIN nino n ON n.id = c.IdNino
INNER JOIN Usuarios usuP ON usuP.id = c.IdUsuario
INNER JOIN Usuarios solicitante ON solicitante.id = sr.solicitado_por
LEFT JOIN Usuarios aprobador ON aprobador.id = sr.aprobado_por";

if ($filtroValido === 'reprogramacion') {
    $sql .= "\nWHERE sr.tipo = 'reprogramacion'";
} elseif ($filtroValido === 'cancelacion') {
    $sql .= "\nWHERE sr.tipo = 'cancelacion'";
}

$sql .= "\nORDER BY sr.fecha_solicitud DESC";

$resultSolicitudes = $conn->query($sql);
if ($resultSolicitudes === false) {
    $errorMensaje = $conn->error;
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Solicitudes de reprogramación</h4>
            </div>
            <div class="card-body">
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($tipoMensaje, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($errorMensaje)): ?>
                    <div class="alert alert-danger" role="alert">
                        Error al cargar las solicitudes: <?php echo htmlspecialchars($errorMensaje, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php else: ?>
                    <div class="mb-3">
                        <div class="btn-group" role="group" aria-label="Filtro de solicitudes">
                            <?php
                            $opcionesFiltro = [
                                'todas' => 'Todas',
                                'reprogramacion' => 'Reprogramaciones',
                                'cancelacion' => 'Cancelaciones'
                            ];
                            foreach ($opcionesFiltro as $valor => $etiqueta):
                                $activo = $filtroValido === $valor ? 'btn-primary' : 'btn-outline-primary';
                                $urlFiltro = $valor === 'todas' ? 'solicitudes.php' : ('solicitudes.php?tipo=' . $valor);
                            ?>
                                <a href="<?php echo htmlspecialchars($urlFiltro, ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo $activo; ?> btn-sm"><?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table" id="solicitudesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cita</th>
                                    <th>Paciente</th>
                                    <th>Psicólogo</th>
                                    <th>Fecha anterior</th>
                                    <th>Nueva fecha</th>
                                    <th>Solicitó</th>
                                    <th>Tipo</th>
                                    <th>Estatus</th>
                                    <th>Fecha solicitud</th>
                                    <th>Respuesta</th>
                                    <th>Comentarios</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $resultSolicitudes->fetch_assoc()): ?>
                                    <?php
                                        $tipoSolicitud = $row['tipo'] ?? 'reprogramacion';
                                        $tipoTexto = $tipoSolicitud === 'cancelacion' ? 'Cancelación' : 'Reprogramación';
                                        $estatus = $row['estatus'];
                                        $badgeClass = 'bg-secondary';
                                        $estatusTexto = 'Desconocido';
                                        switch ($estatus) {
                                            case 'pendiente':
                                                $badgeClass = 'bg-warning text-dark';
                                                $estatusTexto = 'Pendiente';
                                                break;
                                            case 'aprobada':
                                                $badgeClass = 'bg-success';
                                                $estatusTexto = 'Aprobada';
                                                break;
                                            case 'rechazada':
                                                $badgeClass = 'bg-danger';
                                                $estatusTexto = 'Rechazada';
                                                break;
                                        }

                                        $comentarios = $row['comentarios'] ?? '';
                                        $comentarios = trim($comentarios) !== '' ? $comentarios : 'Sin comentarios';
                                        $fechaRespuesta = $row['fecha_respuesta'] ? $row['fecha_respuesta'] : '-';
                                        $aprobador = $row['aprobador'] ? $row['aprobador'] : '-';
                                        $nuevaFecha = $tipoSolicitud === 'cancelacion' ? 'No aplica' : $row['nueva_fecha'];
                                        $mensajeConfirmacion = $tipoSolicitud === 'cancelacion'
                                            ? '¿Deseas aprobar la solicitud de cancelación?'
                                            : '¿Deseas aprobar la solicitud de reprogramación?';
                                        $textoBotonAprobar = $tipoSolicitud === 'cancelacion' ? 'Aprobar cancelación' : 'Aprobar';
                                        $textoBotonRechazar = $tipoSolicitud === 'cancelacion' ? 'Rechazar cancelación' : 'Rechazar';
                                    ?>
                                    <tr>
                                        <td><?php echo (int) $row['id']; ?></td>
                                        <td><?php echo (int) $row['cita_id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['paciente'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['psicologo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['fecha_anterior'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($nuevaFecha, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['solicitante'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($tipoTexto, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $estatusTexto; ?></span></td>
                                        <td><?php echo htmlspecialchars($row['fecha_solicitud'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if ($fechaRespuesta !== '-'): ?>
                                                <?php echo htmlspecialchars($fechaRespuesta, ENT_QUOTES, 'UTF-8'); ?><br>
                                                <small><?php echo htmlspecialchars($aprobador, ENT_QUOTES, 'UTF-8'); ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($comentarios, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if ($estatus === 'pendiente'): ?>
                                                <div class="d-flex flex-column gap-2">
                                                    <form method="post" action="procesar_solicitud.php" onsubmit="return confirm('<?php echo htmlspecialchars($mensajeConfirmacion, ENT_QUOTES, 'UTF-8'); ?>');">
                                                        <input type="hidden" name="solicitud_id" value="<?php echo (int) $row['id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-success btn-sm"><?php echo htmlspecialchars($textoBotonAprobar, ENT_QUOTES, 'UTF-8'); ?></button>
                                                    </form>
                                                    <form method="post" action="procesar_solicitud.php" class="d-flex flex-column gap-1">
                                                        <input type="hidden" name="solicitud_id" value="<?php echo (int) $row['id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="text" name="comentarios" class="form-control form-control-sm" placeholder="Comentarios (opcional)">
                                                        <button type="submit" class="btn btn-danger btn-sm"><?php echo htmlspecialchars($textoBotonRechazar, ENT_QUOTES, 'UTF-8'); ?></button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Sin acciones</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include '../Modulos/footer.php';
?>
<script>
    $(document).ready(function () {
        $('#solicitudesTable').DataTable({
            language: {
                lengthMenu: 'Número de filas _MENU_',
                zeroRecords: 'No se encontraron solicitudes',
                info: 'Página _PAGE_ de _PAGES_',
                search: 'Buscar:',
                paginate: {
                    first: 'Primero',
                    last: 'Último',
                    next: 'Siguiente',
                    previous: 'Previo'
                },
                infoEmpty: 'No hay registros disponibles',
                infoFiltered: '(Filtrado de _MAX_ registros)'
            }
        });
    });
</script>
