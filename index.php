<?php

include 'Modulos/head.php';

$rolUsuario = $_SESSION['rol'] ?? 0;
$mensajeReprogramacion = $_SESSION['reprogramacion_mensaje'] ?? null;
$tipoReprogramacion = $_SESSION['reprogramacion_tipo'] ?? 'success';
unset($_SESSION['reprogramacion_mensaje'], $_SESSION['reprogramacion_tipo']);

$mensajeCancelacion = $_SESSION['cancelacion_mensaje'] ?? null;
$tipoCancelacion = $_SESSION['cancelacion_tipo'] ?? 'success';
unset($_SESSION['cancelacion_mensaje'], $_SESSION['cancelacion_tipo']);

$tablaSolicitudesExiste = false;
if ($resultadoTabla = $conn->query("SHOW TABLES LIKE 'SolicitudReprogramacion'")) {
    $tablaSolicitudesExiste = $resultadoTabla->num_rows > 0;
    $resultadoTabla->free();
}

$pendientesReprogramacion = 0;
$pendientesCancelacion = 0;
if ($tablaSolicitudesExiste && in_array($rolUsuario, [3, 5])) {
    if ($stmtPendientes = $conn->prepare("SELECT tipo, COUNT(*) FROM SolicitudReprogramacion WHERE estatus = 'pendiente' GROUP BY tipo")) {
        $stmtPendientes->execute();
        $stmtPendientes->bind_result($tipoSolicitud, $totalPendiente);
        while ($stmtPendientes->fetch()) {
            if ($tipoSolicitud === 'cancelacion') {
                $pendientesCancelacion = (int) $totalPendiente;
            } elseif ($tipoSolicitud === 'reprogramacion') {
                $pendientesReprogramacion = (int) $totalPendiente;
            }
        }
        $stmtPendientes->close();
    }
}
?>

<div class="row">
  <div class="col-md-12">
    <div class="card">
      <div class="card-header">
        <h4 class="card-title">Citas</h4>
      </div>
      <div class="card-body">
        <?php if ($mensajeReprogramacion): ?>
          <div class="alert alert-<?php echo htmlspecialchars($tipoReprogramacion, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($mensajeReprogramacion, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if ($mensajeCancelacion): ?>
          <div class="alert alert-<?php echo htmlspecialchars($tipoCancelacion, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($mensajeCancelacion, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (in_array($rolUsuario, [3, 4]) && $pendientesReprogramacion > 0): ?>
          <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Hay <?php echo (int) $pendientesReprogramacion; ?> solicitud(es) de reprogramación pendientes.
            <a href="/Citas/solicitudes.php" class="alert-link">Revisar solicitudes</a>.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (in_array($rolUsuario, [3, 4]) && $pendientesCancelacion > 0): ?>
          <div class="alert alert-warning alert-dismissible fade show" role="alert">
            Hay <?php echo (int) $pendientesCancelacion; ?> solicitud(es) de cancelación pendientes.
            <a href="/Citas/solicitudes.php?tipo=cancelacion" class="alert-link">Revisar solicitudes</a>.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        <div class="table-responsive">

          <?php
          // Consulta SQL
          $selectSolicitudesReprogramacion = $tablaSolicitudesExiste ? ",\n       COALESCE(sr_reprogramacion.solicitudesPendientes, 0) as solicitudesReprogramacionPendientes" : ",\n       0 as solicitudesReprogramacionPendientes";
          $joinSolicitudesReprogramacion = $tablaSolicitudesExiste ? "LEFT JOIN (\n    SELECT cita_id, COUNT(*) AS solicitudesPendientes\n    FROM SolicitudReprogramacion\n    WHERE estatus = 'pendiente' AND tipo = 'reprogramacion'\n    GROUP BY cita_id\n) sr_reprogramacion ON sr_reprogramacion.cita_id = ci.id\n" : '';

$selectSolicitudesCancelacion = $tablaSolicitudesExiste ? ",\n       COALESCE(sr_cancelacion.solicitudesPendientesCancelacion, 0) as solicitudesCancelacionPendientes" : ",\n       0 as solicitudesCancelacionPendientes";
$joinSolicitudesCancelacion = $tablaSolicitudesExiste ? "LEFT JOIN (\n    SELECT cita_id, COUNT(*) AS solicitudesPendientesCancelacion\n    FROM SolicitudReprogramacion\n    WHERE estatus = 'pendiente' AND tipo = 'cancelacion'\n    GROUP BY cita_id\n) sr_cancelacion ON sr_cancelacion.cita_id = ci.id\n" : '';


$zonaHoraria = new DateTimeZone('America/Mexico_City');
$fechaActual = new DateTime('now', $zonaHoraria);
$fechaInicioSeleccionada = $_GET['fecha_inicio'] ?? $fechaActual->format('Y-m-d');
$fechaFinSeleccionada = $_GET['fecha_fin'] ?? $fechaActual->format('Y-m-d');

$fechaInicioValidada = DateTime::createFromFormat('Y-m-d', (string) $fechaInicioSeleccionada, $zonaHoraria);
if ($fechaInicioValidada === false || $fechaInicioValidada->format('Y-m-d') !== (string) $fechaInicioSeleccionada) {
  $fechaInicioSeleccionada = $fechaActual->format('Y-m-d');
} else {
  $fechaInicioSeleccionada = $fechaInicioValidada->format('Y-m-d');
}

$fechaFinValidada = DateTime::createFromFormat('Y-m-d', (string) $fechaFinSeleccionada, $zonaHoraria);
if ($fechaFinValidada === false || $fechaFinValidada->format('Y-m-d') !== (string) $fechaFinSeleccionada) {
  $fechaFinSeleccionada = $fechaActual->format('Y-m-d');
} else {
  $fechaFinSeleccionada = $fechaFinValidada->format('Y-m-d');
}

if ($fechaInicioSeleccionada > $fechaFinSeleccionada) {
  $fechaTemporal = $fechaInicioSeleccionada;
  $fechaInicioSeleccionada = $fechaFinSeleccionada;
  $fechaFinSeleccionada = $fechaTemporal;
}

$estatusSeleccionado = null;
if (isset($_GET['estatus'])) {
  $estatusParam = trim((string) $_GET['estatus']);
  if ($estatusParam !== '' && ctype_digit($estatusParam)) {
    $estatusSeleccionado = (int) $estatusParam;
  }
}

$estatusDisponibles = [];
if ($resultadoEstatus = $conn->query("SELECT id, name FROM Estatus ORDER BY name")) {
  while ($estatus = $resultadoEstatus->fetch_assoc()) {
    $estatusDisponibles[] = [
      'id' => (int) $estatus['id'],
      'name' => (string) $estatus['name']
    ];
  }
  $resultadoEstatus->free();
}

if ($estatusSeleccionado === null) {
  $fechaInicioSeleccionada = $fechaActual->format('Y-m-d');
  $fechaFinSeleccionada = $fechaActual->format('Y-m-d');
}


            $fecha = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');

          $whereConditions = ["DATE(ci.Programado) BETWEEN ? AND ?"];
          if ($estatusSeleccionado === null) {
            $whereConditions[] = "(ci.FormaPago IS NULL OR ci.FormaPago = '')";
          } else {
            $whereConditions[] = "ci.Estatus = ?";
          }

          $sql = "SELECT ci.id,
       ci.IdNino AS paciente_id,
       n.name,
       us.name as Psicologo,
       ci.IdUsuario AS PsicologoId,
       ci.costo,
       ci.Programado,
       DATE(ci.Programado) as Fecha,
       TIME(ci.Programado) as Hora,
       ci.Tipo,
       ci.FormaPago,
       es.name as Estatus,
       COALESCE(n.saldo_paquete, 0) AS saldo_paquete" . $selectSolicitudesReprogramacion . $selectSolicitudesCancelacion . "
FROM Cita ci
INNER JOIN nino n ON n.id = ci.IdNino
INNER JOIN Usuarios us ON us.id = ci.IdUsuario
INNER JOIN Estatus es ON es.id = ci.Estatus
" . $joinSolicitudesReprogramacion . $joinSolicitudesCancelacion . "
WHERE " . implode("\n  AND ", $whereConditions) . "
ORDER BY ci.Programado ASC;";


$stmt = $conn->prepare($sql);
if (!$stmt) {
  throw new RuntimeException('Error al preparar la consulta de citas: ' . $conn->error);
}
if ($estatusSeleccionado === null) {
  $stmt->bind_param('ss', $fechaInicioSeleccionada, $fechaFinSeleccionada);
} else {
  $stmt->bind_param('ssi', $fechaInicioSeleccionada, $fechaFinSeleccionada, $estatusSeleccionado);
}
$stmt->execute();
$result = $stmt->get_result();
          $hoy = $fechaActual->format('Y-m-d');
          // Verificar si hay resultados y generar la tabla HTML
          echo '<div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center mb-3">';
          if ($fechaInicioSeleccionada === $fechaFinSeleccionada) {
            $descripcionRango = 'Citas para ' . htmlspecialchars($fechaInicioSeleccionada, ENT_QUOTES, 'UTF-8');
          } else {
            $descripcionRango = 'Citas del ' . htmlspecialchars($fechaInicioSeleccionada, ENT_QUOTES, 'UTF-8') . ' al ' . htmlspecialchars($fechaFinSeleccionada, ENT_QUOTES, 'UTF-8');
          }
          echo '<h5 class="mb-2 mb-md-0">' . $descripcionRango . '</h5>';
          echo '<form method="get" class="row row-cols-1 row-cols-sm-auto g-2 align-items-end">';
          echo '<div class="col">';
          echo '<label class="form-label" for="fecha_inicio">Fecha inicio</label>';
          echo '<input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" value="' . htmlspecialchars($fechaInicioSeleccionada, ENT_QUOTES, 'UTF-8') . '">';
          echo '</div>';
          echo '<div class="col">';
          echo '<label class="form-label" for="fecha_fin">Fecha fin</label>';
          echo '<input type="date" id="fecha_fin" name="fecha_fin" class="form-control" value="' . htmlspecialchars($fechaFinSeleccionada, ENT_QUOTES, 'UTF-8') . '">';
          echo '</div>';
          echo '<div class="col">';
          echo '<label class="form-label" for="estatus">Estatus</label>';
          echo '<select id="estatus" name="estatus" class="form-select">';
          $selectedPendientes = $estatusSeleccionado === null ? ' selected' : '';
          echo '<option value=""' . $selectedPendientes . '>Pendientes de pago de hoy</option>';
          foreach ($estatusDisponibles as $estatus) {
            $selected = ($estatusSeleccionado !== null && $estatusSeleccionado === $estatus['id']) ? ' selected' : '';
            echo '<option value="' . (int) $estatus['id'] . '"' . $selected . '>' . htmlspecialchars($estatus['name'], ENT_QUOTES, 'UTF-8') . '</option>';
          }
          echo '</select>';
          echo '</div>';
          echo '<div class="col">';
          echo '<button type="submit" class="btn btn-primary">Filtrar</button>';
          echo '</div>';
          echo '</form>';
          echo '</div>';

          if ($result->num_rows > 0) {
            echo "<table border='1' id=\"myTable\" >
            <thead>
            <tr>
                <th>Fecha</th>
                <th>Id</th>
                <th>Paciente</th>
                <th>Psicólogo</th>
                <th>Costo</th>
                <th>Hora</th>
                <th>Tipo</th>
                <th>Forma de pago</th>
                <th>Estatus</th>
                <th>Solicitudes de reprogramación</th>
                <th>Solicitudes de cancelación</th>
                <th>Opciones</th>
            </tr>    </thead>
                        <tbody>";
            // Recorrer los resultados y mostrarlos en la tabla

            while ($row = $result->fetch_assoc()) {
              $pendientesReprogramacion = isset($row['solicitudesReprogramacionPendientes']) ? (int) $row['solicitudesReprogramacionPendientes'] : 0;
              $textoBadgeReprogramacion = $pendientesReprogramacion > 0 ? 'Pendiente (' . $pendientesReprogramacion . ')' : 'Sin solicitudes';
              $badgeClassReprogramacion = $pendientesReprogramacion > 0 ? 'badge bg-warning text-dark' : 'badge bg-secondary';

              $pendientesCancelacion = isset($row['solicitudesCancelacionPendientes']) ? (int) $row['solicitudesCancelacionPendientes'] : 0;
              $textoBadgeCancelacion = $pendientesCancelacion > 0 ? 'Pendiente (' . $pendientesCancelacion . ')' : 'Sin solicitudes';
              $badgeClassCancelacion = $pendientesCancelacion > 0 ? 'badge bg-warning text-dark' : 'badge bg-secondary';

              $reprogramarTexto = ($rolUsuario == 1) ? 'Solicitar reprogramación' : 'Reprogramar';
              $formaPagoRegistrada = isset($row['FormaPago']) ? trim((string) $row['FormaPago']) : '';
              $estatusActual = isset($row['Estatus']) ? strtolower((string) $row['Estatus']) : '';
              $botones = [];
              if ($rolUsuario == 1 && $pendientesReprogramacion > 0) {
                $botones[] = '<button class="btn btn-secondary btn-sm" disabled>Solicitud pendiente</button>';
              } else {
                $botones[] = '<button class="btn btn-primary btn-sm" onclick="Reprogramar(' . $row['id'] . ')">' . $reprogramarTexto . '</button>';
              }

              if ($rolUsuario == 1) {
                if ($pendientesCancelacion > 0) {
                  $botones[] = '<button class="btn btn-secondary btn-sm" disabled>Cancelación pendiente</button>';
                } else {
                  $botones[] = '<button class="btn btn-danger btn-sm" onclick="actualizarCita(' . $row['id'] . ',1)">Solicitar cancelación</button>';
                }
              } else {
                $botones[] = '<button class="btn btn-danger btn-sm" onclick="actualizarCita(' . $row['id'] . ',1)">Cancelar</button>';
              }

              if ($formaPagoRegistrada !== '') {
                $botones[] = '<span class="badge bg-success">Pago registrado</span>';
                if ($estatusActual !== 'finalizada' ) {
                  $botones[] = '<button class="btn btn-outline-success btn-sm" onclick="finalizarCita(' . $row['id'] . ')">Finalizar</button>';
                }
              } elseif ($row['Fecha'] === $hoy && ($row['Estatus'] == 'Creada' || $row['Estatus'] == 'Reprogramado')) {
                $pagoInfo = [
                  'idCita' => (int) $row['id'],
                  'estatus' => 4,
                  'costo' => (float) $row['costo'],
                  'pacienteId' => (int) $row['paciente_id'],
                  'saldo' => (float) $row['saldo_paquete'],
                  'programado' => $row['Programado'],
                  'psicologoId' => (int) $row['PsicologoId'],
                  'tipo' => $row['Tipo'],
                  'pacienteNombre' => $row['name'],
                  'psicologoNombre' => $row['Psicologo']
                ];
                $botones[] = '<button class="btn btn-success btn-sm pagar-cita-btn" data-pago="' . htmlspecialchars(json_encode($pagoInfo), ENT_QUOTES, 'UTF-8') . '">Pagar</button>';
              }

              echo '<tr>';
              echo '<td>' . $row['Fecha'] . '</td>';
              echo '<td>' . $row['id'] . '</td>';
              echo '<td>' . $row['name'] . '</td>';
              echo '<td>' . $row['Psicologo'] . '</td>';
              echo '<td>' . $row['costo'] . '</td>';
              echo '<td>' . $row['Hora'] . '</td>';
              echo '<td>' . $row['Tipo'] . '</td>';
              $formaPagoTexto = $formaPagoRegistrada !== '' ? $formaPagoRegistrada : 'Sin registrar';
              echo '<td>' . htmlspecialchars($formaPagoTexto, ENT_QUOTES, 'UTF-8') . '</td>';
              echo '<td>' . $row['Estatus'] . '</td>';
              echo '<td><span class="' . $badgeClassReprogramacion . '">' . $textoBadgeReprogramacion . '</span></td>';
              echo '<td><span class="' . $badgeClassCancelacion . '">' . $textoBadgeCancelacion . '</span></td>';
              echo '<td>' . implode(' ', $botones) . '</td>';
              echo '</tr>';
            }
            echo "</tbody></table>";
          } else {
            echo "<p class='mb-0'>No se encontraron citas para la fecha seleccionada.</p>";
          }

          // Cerrar conexión
          $stmt->close();
          $conn->close();
          ?>
        </div>
      </div>
    </div>
  </div>
</div>







<hr>
<div class="row ">
  <h2>Generar cita</h2>
  <div class="col-12 col-sm-6 col-md-6 col-xl-4 ">
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <h3>Cliente</h3>
        </div>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-12 col-lg-12">
            <form onsubmit="return searchNino();">
              <div class="form-group">
                <label for="nameSelect">Lista</label>
                <select class="form-select" name="nameSelect" id="nameSelect" onchange="updateResumen()">
                  <!-- Opciones se llenarán dinámicamente desde get_names.php -->
                </select>
                <hr>
                <label for="search">Buscar</label>
                <div class="input-group">
                  <!-- Campo de búsqueda -->
                  <input class="form-control" type="text" name="search" id="search" placeholder="Buscar por nombre">
                  <button class="btn btn-black btn-border" type="submit">Buscar</button>
                </div>
              </div>
            </form>
            <div id="results"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-6 col-xl-4">
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <h3>Fecha y tipo de cita</h3>
        </div>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-12 col-lg-12">
            <div class="form-group">
              <label for="idEmpleado">Psicólogos</label>
              <select name="empleado" id="idEmpleado" class="form-select" onchange="updateResumen()">
                <!-- Opciones se llenarán dinámicamente desde get_names.php -->
              </select>
            </div>
            <div class="form-group">
              <label for="costosSelect"> Tipo</label>
              <select name="costos" id="costosSelect" class="form-select" onchange="updateResumen()">
                <!-- Opciones se llenarán dinámicamente desde get_names.php -->
              </select>
            </div>
            <div class="form-group">
              <label for="paqueteSelect">Paquete</label>
              <select name="paquete" id="paqueteSelect" class="form-select" onchange="handlePaqueteChange()">
                <option value="">Sin paquete</option>
              </select>
            </div>
            <div class="form-group">
              <label for="citaDia"> Día de la cita</label>
              <input type="datetime-local" id="citaDia" name="citaDia" class="form-select" onchange="updateResumen()" step="3600">
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-6 col-xl-4">
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <h3>Resumen de Cita</h3>
        </div>
      </div>
      <div class="card-body">
        <form id="formCita" action="procesar_cita.php" method="POST" onsubmit="validarFormulario(event)">
          <div class="row">
            <div class="col-md-12 col-lg-12">
              <div class="form-group">
                <label for="resumenCliente">Cliente</label>
                <input type="text" name="resumenCliente" id="resumenCliente" class="form-control" readonly>
                <input type="text" name="sendIdCliente" id="sendIdCliente" class="form-control" hidden readonly>
              </div>
              <div class="form-group">
                <label for="resumenPsicologo">Psicólogo</label>
                <input type="text" name="resumenPsicologo" id="resumenPsicologo" class="form-control" readonly>
                <input type="text" name="sendIdPsicologo" id="sendIdPsicologo" class="form-control" hidden readonly>
              </div>
              <div class="form-group">
                <label for="resumenTipo">Tipo</label>
                <input type="text" name="resumenTipo" id="resumenTipo" class="form-control" readonly>
              </div>
              <div class="form-group">
                <label for="resumenCosto">Costo</label>
                <input type="number" name="resumenCosto" id="resumenCosto" class="form-control" min="0" step="0.01">
              </div>
              <div class="form-group">
                <label for="resumenPaquete">Paquete</label>
                <input type="text" name="resumenPaquete" id="resumenPaquete" class="form-control" value="Sin paquete" readonly>
                <input type="hidden" name="sendIdPaquete" id="sendIdPaquete">
              </div>
              <div class="form-group d-none" id="grupoMetodoPaquete">
                <label for="paqueteMetodo">Método del primer pago</label>
                <select name="paqueteMetodo" id="paqueteMetodo" class="form-select">
                  <option value="">Selecciona una opción</option>
                  <option value="Efectivo">Efectivo</option>
                  <option value="Transferencia">Transferencia</option>
                </select>
              </div>
              <div class="form-group d-none" id="grupoSaldoPaquete">
                <label for="resumenSaldoPaquete">Saldo adicional</label>
                <input type="text" id="resumenSaldoPaquete" class="form-control" readonly>
              </div>
              <div class="form-group">
                <label for="resumenFecha">Fecha de la Cita</label>
                <input type="datetime-local" name="resumenFecha" id="resumenFecha" class="form-control" readonly step="3600">
              </div>
              <button type="submit" class="btn btn-primary mt-3">Guardar Cita</button>
              <hr>
              <div class="card card-stats card-round" id="idResultado">
                <div class="card-body">
                  <div class="row">
                    <div class="col-2">
                      <div class="icon-big text-center">
                        <i class="icon-close text-danger"></i>
                      </div>
                    </div>
                    <div class="col-10 col-stats">
                      <div class="numbers">

                        <div id="resultado"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="ModalTipoPago" tabindex="-1" aria-labelledby="ModalTipoPagoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ModalTipoPagoLabel">Seleccione el tipo de pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Paciente</label>
                        <p id="modalPacienteNombre" class="form-control-plaintext mb-0"></p>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label fw-semibold">Costo de la cita</label>
                            <p id="modalCostoCita" class="form-control-plaintext mb-0"></p>
                        </div>
                        <div class="col">
                            <label class="form-label fw-semibold">Saldo disponible</label>
                            <p id="modalSaldoActual" class="form-control-plaintext mb-0"></p>
                        </div>
                    </div>
                    <div class="alert alert-warning d-none" id="alertaSaldoInsuficiente" role="alert">
                        El monto asignado al saldo excede el saldo disponible del paciente.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pagos registrados</label>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="tablaPagos">
                                <thead>
                                    <tr>
                                        <th scope="col">Forma de pago</th>
                                        <th scope="col" style="width: 160px;">Monto</th>
                                        <th scope="col" style="width: 60px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="form-text">Registra una o varias formas de pago hasta cubrir el costo de la cita.</div>
                    </div>
                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="agregarPago">
                            Agregar forma de pago
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="agregarPagoSaldo">
                            Usar saldo disponible
                        </button>
                    </div>
                    <p class="fw-semibold mb-0" id="resumenPagos"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmarPago">Confirmar</button>
                </div>
            </div>
        </div>
    </div>
<div class="modal fade" id="modalProximaCita" tabindex="-1" aria-labelledby="modalProximaCitaLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalProximaCitaLabel">Agendar próxima cita</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-success d-none" id="alertaPagoExitoso" role="alert"></div>
        <p class="mb-3">¿Deseas agendar una cita para la próxima semana en el mismo horario? Puedes ajustar los datos antes de confirmar.</p>
        <form id="formProximaCita">
          <input type="hidden" name="sendIdCliente" id="proximaSendIdCliente">
          <input type="hidden" name="sendIdPsicologo" id="proximaSendIdPsicologo">
          <input type="hidden" name="resumenTipo" id="proximaResumenTipo">
          <div class="mb-3">
            <label for="proximaCliente" class="form-label">Cliente</label>
            <input type="text" class="form-control" id="proximaCliente" readonly>
          </div>
          <div class="mb-3">
            <label for="proximaPsicologo" class="form-label">Psicólogo</label>
            <input type="text" class="form-control" id="proximaPsicologo" readonly>
          </div>
          <div class="mb-3">
            <label for="proximaFecha" class="form-label">Fecha sugerida</label>
            <input type="datetime-local" class="form-control" id="proximaFecha" name="resumenFecha" step="3600" required>
          </div>
          <div class="mb-3">
            <label for="proximaCosto" class="form-label">Costo</label>
            <input type="number" class="form-control" id="proximaCosto" name="resumenCosto" min="0" step="0.01" required>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="omitirProximaCita">Omitir</button>
        <button type="button" class="btn btn-primary" id="confirmarProximaCita">Agendar cita</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="updateModalLabel">Actualizar Fecha de Cita</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="updateForm" method="post" action="update.php">
          <div class="mb-3">
            <label for="citaId" class="form-label">ID de la Cita</label>
            <input type="text" class="form-control" id="citaId" name="citaId" readonly>
          </div>
          <div class="mb-3">
            <label for="fechaProgramada" class="form-label">Nueva Fecha Programada</label>
            <input type="datetime-local" class="form-control" id="fechaProgramada" name="fechaProgramada" required step="3600">
          </div>
          <div class="alert alert-info" id="solicitudAviso" style="display:none;">
            La solicitud será enviada al coordinador para su aprobación.
          </div>
          <button type="submit" class="btn btn-primary" id="updateSubmitButton">Actualizar</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php
include 'Modulos/footer.php';
?>

<script>window.ES_VENTAS = <?php echo ($rolUsuario == 1) ? 'true' : 'false'; ?>;</script>
<script src="assets/js/citas.js?v=<?php echo time(); ?>"></script>
