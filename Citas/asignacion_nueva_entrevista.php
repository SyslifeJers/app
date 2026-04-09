<?php
include '../Modulos/head.php';

require_once __DIR__ . '/../Modulos/logger.php';

$USUARIO_NUEVA_ENTREVISTA_ID = 11;

$rolActual = isset($rol) ? (int) $rol : 0;
$puedeAsignar = ($rolActual === 3 || $rolActual === 5);

$mensaje = null;

function mensajeAlerta(string $tipo, string $texto): array
{
    return ['tipo' => $tipo, 'texto' => $texto];
}

function intPost(string $campo): int
{
    $raw = $_POST[$campo] ?? '';
    if (!is_string($raw) && !is_numeric($raw)) {
        return 0;
    }
    $raw = trim((string) $raw);
    if ($raw === '' || !ctype_digit($raw)) {
        return 0;
    }
    return (int) $raw;
}

function fechaSolo(string $fechaHora): string
{
    $fechaHora = trim($fechaHora);
    if ($fechaHora === '') {
        return '';
    }

    try {
        $dt = new DateTime($fechaHora, new DateTimeZone('America/Mexico_City'));
    } catch (Exception $e) {
        return '';
    }

    return $dt->format('Y-m-d');
}

function hoySolo(): string
{
    return (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');
}

// Cargar lista de psicologos activos.
$psicologos = [];
if ($stmtPsic = $conn->prepare(
    "SELECT usu.id, usu.name\n"
    . "FROM Usuarios usu\n"
    . "INNER JOIN Rol r ON r.id = usu.IdRol\n"
    . "WHERE usu.activo = 1\n"
    . "  AND LOWER(r.name) LIKE '%psicolog%'\n"
    . "  AND usu.id <> ?\n"
    . "ORDER BY usu.name ASC"
)) {
    $stmtPsic->bind_param('i', $USUARIO_NUEVA_ENTREVISTA_ID);
    if ($stmtPsic->execute()) {
        $res = $stmtPsic->get_result();
        while ($fila = $res->fetch_assoc()) {
            $psicologos[] = [
                'id' => (int) $fila['id'],
                'name' => (string) $fila['name'],
            ];
        }
    }
    $stmtPsic->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'asignar_psicologo') {
    if (!$puedeAsignar) {
        $mensaje = mensajeAlerta('danger', 'No tienes permisos para asignar entrevistas.');
    } else {
        $citaId = intPost('cita_id');
        $nuevoPsicologoId = intPost('psicologo_id');

        if ($citaId <= 0) {
            $mensaje = mensajeAlerta('danger', 'La cita seleccionada no es valida.');
        } elseif ($nuevoPsicologoId <= 0) {
            $mensaje = mensajeAlerta('danger', 'Selecciona un psicologo valido.');
        } elseif ($nuevoPsicologoId === $USUARIO_NUEVA_ENTREVISTA_ID) {
            $mensaje = mensajeAlerta('danger', 'No puedes asignar la cita al usuario de Nueva entrevista.');
        } else {
            $conn->begin_transaction();

            try {
                // Validar psicologo.
                $esPsicologo = 0;
                if ($stmtValida = $conn->prepare(
                    "SELECT COUNT(*)\n"
                    . "FROM Usuarios usu\n"
                    . "INNER JOIN Rol r ON r.id = usu.IdRol\n"
                    . "WHERE usu.id = ? AND usu.activo = 1 AND LOWER(r.name) LIKE '%psicolog%'"
                )) {
                    $stmtValida->bind_param('i', $nuevoPsicologoId);
                    $stmtValida->execute();
                    $stmtValida->bind_result($esPsicologo);
                    $stmtValida->fetch();
                    $stmtValida->close();
                }

                if ((int) $esPsicologo <= 0) {
                    throw new Exception('El usuario seleccionado no es un psicologo activo.');
                }

                // Bloquear y obtener datos de la cita.
                $programado = null;
                $psicologoActual = null;
                $estatusActual = null;
                $pacienteNombre = null;

                if ($stmtCita = $conn->prepare(
                    'SELECT ci.Programado, ci.IdUsuario, ci.Estatus, n.name '
                    . 'FROM Cita ci '
                    . 'INNER JOIN nino n ON n.id = ci.IdNino '
                    . 'WHERE ci.id = ? FOR UPDATE'
                )) {
                    $stmtCita->bind_param('i', $citaId);
                    $stmtCita->execute();
                    $stmtCita->bind_result($programado, $psicologoActual, $estatusActual, $pacienteNombre);
                    $stmtCita->fetch();
                    $stmtCita->close();
                }

                if (!$programado) {
                    throw new Exception('No fue posible localizar la cita seleccionada.');
                }

                if ((int) $psicologoActual !== $USUARIO_NUEVA_ENTREVISTA_ID) {
                    throw new Exception('Esta cita ya no esta asignada a Nueva entrevista.');
                }

                if (!in_array((int) $estatusActual, [2, 3], true)) {
                    throw new Exception('Solo se pueden asignar citas con estatus programada o reprogramada.');
                }

                $fechaCita = fechaSolo((string) $programado);
                if ($fechaCita === '' || $fechaCita < hoySolo()) {
                    throw new Exception('Solo se pueden asignar citas con fecha mayor o igual a hoy.');
                }

                // Validar traslape (se asume duracion de 1 hora).
                $traslape = 0;
                if ($stmtTraslape = $conn->prepare(
                    'SELECT COUNT(*) '
                    . 'FROM Cita '
                    . 'WHERE IdUsuario = ? '
                    . '  AND Estatus IN (2, 3) '
                    . '  AND id <> ? '
                    . '  AND Programado < DATE_ADD(?, INTERVAL 1 HOUR) '
                    . '  AND DATE_ADD(Programado, INTERVAL 1 HOUR) > ?'
                )) {
                    $stmtTraslape->bind_param('iiss', $nuevoPsicologoId, $citaId, $programado, $programado);
                    $stmtTraslape->execute();
                    $stmtTraslape->bind_result($traslape);
                    $stmtTraslape->fetch();
                    $stmtTraslape->close();
                }

                if ((int) $traslape > 0) {
                    throw new Exception('El psicologo seleccionado ya tiene una cita que se traslapa con este horario.');
                }

                // Actualizar asignacion.
                if ($stmtUpd = $conn->prepare('UPDATE Cita SET IdUsuario = ? WHERE id = ?')) {
                    $stmtUpd->bind_param('ii', $nuevoPsicologoId, $citaId);
                    if (!$stmtUpd->execute()) {
                        $stmtUpd->close();
                        throw new Exception('No fue posible actualizar la cita.');
                    }
                    $stmtUpd->close();
                } else {
                    throw new Exception('No fue posible preparar la actualizacion de la cita.');
                }

                $usuarioLog = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
                $descripcion = sprintf(
                    'Asigno cita #%d (%s) del paciente %s al psicologo #%d (antes #%d).',
                    $citaId,
                    (string) $programado,
                    (string) $pacienteNombre,
                    $nuevoPsicologoId,
                    $USUARIO_NUEVA_ENTREVISTA_ID
                );
                registrarLog($conn, $usuarioLog, 'citas', 'asignar_nueva_entrevista', $descripcion, 'Cita', (string) $citaId);

                $conn->commit();
                $mensaje = mensajeAlerta('success', 'Cita asignada correctamente.');
            } catch (Exception $e) {
                $conn->rollback();
                $mensaje = mensajeAlerta('danger', $e->getMessage());
            }
        }
    }
}

// Cargar citas pendientes de asignacion.
$citas = [];
if ($stmtCitas = $conn->prepare(
    'SELECT ci.id, ci.Programado, ci.Tipo, ci.Estatus, es.name AS estatus_nombre, n.name AS paciente '
    . 'FROM Cita ci '
    . 'INNER JOIN nino n ON n.id = ci.IdNino '
    . 'INNER JOIN Estatus es ON es.id = ci.Estatus '
    . 'WHERE ci.IdUsuario = ? '
    . '  AND DATE(ci.Programado) >= CURDATE() '
    . '  AND ci.Estatus IN (2, 3) '
    . 'ORDER BY ci.Programado ASC'
)) {
    $stmtCitas->bind_param('i', $USUARIO_NUEVA_ENTREVISTA_ID);
    if ($stmtCitas->execute()) {
        $res = $stmtCitas->get_result();
        while ($fila = $res->fetch_assoc()) {
            $citas[] = [
                'id' => (int) $fila['id'],
                'programado' => (string) $fila['Programado'],
                'tipo' => (string) ($fila['Tipo'] ?? ''),
                'estatus' => (int) $fila['Estatus'],
                'estatus_nombre' => (string) ($fila['estatus_nombre'] ?? ''),
                'paciente' => (string) ($fila['paciente'] ?? ''),
            ];
        }
    }
    $stmtCitas->close();
}
?>

<div class="page-header">
  <h3 class="fw-bold mb-3">Asignacion de nueva entrevista</h3>
  <p class="text-muted mb-0">Solo se muestran citas con fecha mayor o igual a hoy y asignadas al usuario "Nueva entrevista" (ID <?php echo (int) $USUARIO_NUEVA_ENTREVISTA_ID; ?>).</p>
</div>

<?php if ($mensaje && isset($mensaje['texto'])) { ?>
  <div class="alert alert-<?php echo htmlspecialchars($mensaje['tipo']); ?>" role="alert">
    <?php echo htmlspecialchars($mensaje['texto']); ?>
  </div>
<?php } ?>

<?php if (!$puedeAsignar) { ?>
  <div class="alert alert-warning" role="alert">Tu rol no permite asignar entrevistas.</div>
<?php } ?>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table id="tablaAsignaciones" class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Programado</th>
            <th>Paciente</th>
            <th>Tipo</th>
            <th>Estatus</th>
            <th style="width: 140px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($citas as $cita) { ?>
          <tr>
            <td><?php echo (int) $cita['id']; ?></td>
            <td><?php echo htmlspecialchars($cita['programado']); ?></td>
            <td><?php echo htmlspecialchars($cita['paciente']); ?></td>
            <td><?php echo htmlspecialchars($cita['tipo']); ?></td>
            <td><?php echo htmlspecialchars($cita['estatus_nombre']); ?></td>
            <td>
              <button
                type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalAsignar"
                data-cita-id="<?php echo (int) $cita['id']; ?>"
                data-programado="<?php echo htmlspecialchars($cita['programado']); ?>"
                data-paciente="<?php echo htmlspecialchars($cita['paciente']); ?>"
                <?php echo $puedeAsignar ? '' : 'disabled'; ?>
              >
                Asignar
              </button>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="modalAsignar" tabindex="-1" aria-labelledby="modalAsignarLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="modalAsignarLabel">Asignar psicologo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="accion" value="asignar_psicologo">
          <input type="hidden" name="cita_id" id="asignarCitaId" value="">

          <div class="mb-2">
            <div class="text-muted" id="asignarInfo"></div>
          </div>

          <label for="asignarPsicologo" class="form-label">Nuevo psicologo</label>
          <select class="form-select" id="asignarPsicologo" name="psicologo_id" required>
            <option value="">Selecciona un psicologo...</option>
            <?php foreach ($psicologos as $psi) { ?>
              <option value="<?php echo (int) $psi['id']; ?>"><?php echo htmlspecialchars($psi['name']); ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button type="submit" class="btn btn-primary" <?php echo $puedeAsignar ? '' : 'disabled'; ?>>Asignar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn && typeof jQuery.fn.DataTable === 'function') {
      jQuery('#tablaAsignaciones').DataTable({
        order: [[1, 'asc']],
        pageLength: 25,
        language: {
          url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/es-ES.json'
        }
      });
    }

    var modalEl = document.getElementById('modalAsignar');
    if (!modalEl) return;

    modalEl.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      if (!button) return;

      var citaId = button.getAttribute('data-cita-id') || '';
      var programado = button.getAttribute('data-programado') || '';
      var paciente = button.getAttribute('data-paciente') || '';

      var citaInput = document.getElementById('asignarCitaId');
      var info = document.getElementById('asignarInfo');
      var select = document.getElementById('asignarPsicologo');

      if (citaInput) citaInput.value = citaId;
      if (info) info.textContent = 'Cita #' + citaId + ' | ' + programado + ' | ' + paciente;
      if (select) select.value = '';
    });
  });
</script>

<?php include '../Modulos/footer.php'; ?>
