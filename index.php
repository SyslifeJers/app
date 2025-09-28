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
if ($tablaSolicitudesExiste && in_array($rolUsuario, [3, 4])) {
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

          $sql = "SELECT ci.id,
       ci.IdNino AS paciente_id,
       n.name,
       us.name as Psicologo,
       ci.costo,
       ci.Programado,
       DATE(ci.Programado) as Fecha,
       TIME(ci.Programado) as Hora,
       ci.Tipo,
       es.name as Estatus,
       COALESCE(n.saldo_paquete, 0) AS saldo_paquete" . $selectSolicitudesReprogramacion . $selectSolicitudesCancelacion . "
FROM Cita ci
INNER JOIN nino n ON n.id = ci.IdNino
INNER JOIN Usuarios us ON us.id = ci.IdUsuario
INNER JOIN Estatus es ON es.id = ci.Estatus
" . $joinSolicitudesReprogramacion . $joinSolicitudesCancelacion . "
WHERE ci.Estatus = 2 OR ci.Estatus = 3
ORDER BY ci.Programado ASC;";

          $result = $conn->query($sql);
          date_default_timezone_set('America/Mexico_City');
          $hoy = date('Y-m-d');
          echo $hoy;
          // Verificar si hay resultados y generar la tabla HTML
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

              if (date('Y-m-d', strtotime($row['Fecha'])) == $hoy && ($row['Estatus'] == 'Creada' || $row['Estatus'] == 'Reprogramado')) {
                $onclickPago = sprintf(
                  'actualizarCitaPago(%d, %d, %f, %d, %f)',
                  $row['id'],
                  4,
                  (float) $row['costo'],
                  (int) $row['paciente_id'],
                  (float) $row['saldo_paquete']
                );
                $botones[] = '<button class="btn btn-success btn-sm" onclick="' . $onclickPago . '">Pagar</button>';
              }

              echo '<tr>';
              echo '<td>' . $row['Fecha'] . '</td>';
              echo '<td>' . $row['id'] . '</td>';
              echo '<td>' . $row['name'] . '</td>';
              echo '<td>' . $row['Psicologo'] . '</td>';
              echo '<td>' . $row['costo'] . '</td>';
              echo '<td>' . $row['Hora'] . '</td>';
              echo '<td>' . $row['Tipo'] . '</td>';
              echo '<td>' . $row['Estatus'] . '</td>';
              echo '<td><span class="' . $badgeClassReprogramacion . '">' . $textoBadgeReprogramacion . '</span></td>';
              echo '<td><span class="' . $badgeClassCancelacion . '">' . $textoBadgeCancelacion . '</span></td>';
              echo '<td>' . implode(' ', $botones) . '</td>';
              echo '</tr>';
            }
            echo "</tbody></table>";
          } else {
            echo "0 resultados";
          }

          // Cerrar conexión
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
              <label for="citaDia"> Día de la cita</label>
              <input type="datetime-local" id="citaDia" name="citaDia" class="form-select" onchange="updateResumen()">
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
                <input type="number" name="resumenCosto" id="resumenCosto" class="form-control" >
              </div>
              <div class="form-group">
                <label for="resumenFecha">Fecha de la Cita</label>
                <input type="datetime-local" name="resumenFecha" id="resumenFecha" class="form-control" readonly>
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
            <input type="datetime-local" class="form-control" id="fechaProgramada" name="fechaProgramada" required>
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

<script>
  const ES_VENTAS = <?php echo ($rolUsuario == 1) ? 'true' : 'false'; ?>;
  const pagoModalEstado = {
    modal: null,
    idCita: null,
    estatus: null,
    costo: 0,
    saldo: 0,
    pagos: []
  };
  const modalPagoElement = document.getElementById('ModalTipoPago');
  const tablaPagosBody = document.querySelector('#tablaPagos tbody');
  const agregarPagoBtn = document.getElementById('agregarPago');
  const agregarPagoSaldoBtn = document.getElementById('agregarPagoSaldo');
  const resumenPagos = document.getElementById('resumenPagos');
  const saldoActualLabel = document.getElementById('modalSaldoActual');
  const costoCitaLabel = document.getElementById('modalCostoCita');
  const pacienteLabel = document.getElementById('modalPacienteNombre');
  const alertaSaldoInsuficiente = document.getElementById('alertaSaldoInsuficiente');
  const formatoMoneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
  const METODOS_PAGO = ['Efectivo', 'Transferencia', 'Tarjeta'];
  const METODO_SALDO = 'Saldo';

  function obtenerTotalesPagos() {
    const totales = {
      total: 0,
      totalSaldo: 0,
      totalExternos: 0,
      metodos: new Set()
    };

    if (!Array.isArray(pagoModalEstado.pagos)) {
      pagoModalEstado.pagos = [];
    }

    pagoModalEstado.pagos.forEach((pago) => {
      const metodo = (pago.metodo || '').trim();
      const monto = parseFloat(pago.monto);
      if (!metodo || Number.isNaN(monto)) {
        return;
      }

      totales.total += monto;
      totales.metodos.add(metodo);
      if (metodo === METODO_SALDO) {
        totales.totalSaldo += monto;
      } else {
        totales.totalExternos += monto;
      }
    });

    return totales;
  }

  function actualizarResumenPagos() {
    const totales = obtenerTotalesPagos();
    if (resumenPagos) {
      let mensaje = 'Total registrado: ' + formatoMoneda.format(totales.total) + '. ';
      if (totales.total + 0.0001 < pagoModalEstado.costo) {
        const faltante = pagoModalEstado.costo - totales.total;
        mensaje += 'Faltan ' + formatoMoneda.format(faltante) + ' por cubrir.';
      } else {
        const excedente = totales.total - pagoModalEstado.costo;
        if (excedente > 0.009) {
          mensaje += 'Excedente de ' + formatoMoneda.format(excedente) + ' se agregará al saldo.';
        } else {
          mensaje += 'Monto exacto para cubrir la cita.';
        }
      }

      if (totales.totalSaldo > 0.009) {
        const saldoRestante = Math.max(0, pagoModalEstado.saldo - totales.totalSaldo);
        mensaje += ' Saldo utilizado: ' + formatoMoneda.format(totales.totalSaldo) + '. Saldo restante: ' + formatoMoneda.format(saldoRestante) + '.';
      }

      resumenPagos.textContent = mensaje;
    }

    if (alertaSaldoInsuficiente) {
      if (totales.totalSaldo > pagoModalEstado.saldo + 0.0001) {
        alertaSaldoInsuficiente.classList.remove('d-none');
      } else {
        alertaSaldoInsuficiente.classList.add('d-none');
      }
    }

    actualizarBotonesPagos();
  }

  function actualizarBotonesPagos() {
    if (!agregarPagoSaldoBtn) {
      return;
    }

    const saldoDisponible = pagoModalEstado.saldo || 0;
    const saldoEnUso = pagoModalEstado.pagos.some((pago) => (pago.metodo || '').trim() === METODO_SALDO);

    if (saldoDisponible <= 0 || saldoEnUso) {
      agregarPagoSaldoBtn.setAttribute('disabled', 'disabled');
    } else {
      agregarPagoSaldoBtn.removeAttribute('disabled');
    }
  }

  function handleMontoChange(index, valor) {
    if (!Array.isArray(pagoModalEstado.pagos) || !pagoModalEstado.pagos[index]) {
      return;
    }

    const numero = parseFloat(valor);
    pagoModalEstado.pagos[index].monto = Number.isNaN(numero) || numero < 0 ? 0 : numero;
    actualizarResumenPagos();
  }

  function handleMetodoChange(index, nuevoMetodo) {
    if (!Array.isArray(pagoModalEstado.pagos) || !pagoModalEstado.pagos[index]) {
      return;
    }

    pagoModalEstado.pagos[index].metodo = nuevoMetodo;

    if (nuevoMetodo === METODO_SALDO) {
      const montoActual = parseFloat(pagoModalEstado.pagos[index].monto);
      if (Number.isNaN(montoActual) || montoActual <= 0) {
        const totales = obtenerTotalesPagos();
        const totalSinActual = totales.total - (Number.isNaN(montoActual) ? 0 : montoActual);
        const faltante = Math.max(0, pagoModalEstado.costo - totalSinActual);
        const montoSugerido = Math.min(pagoModalEstado.saldo, faltante > 0 ? faltante : pagoModalEstado.saldo);
        pagoModalEstado.pagos[index].monto = parseFloat(montoSugerido.toFixed(2));
      }
    }

    renderPagos();
  }

  function eliminarPago(index) {
    if (!Array.isArray(pagoModalEstado.pagos) || !pagoModalEstado.pagos[index]) {
      return;
    }

    pagoModalEstado.pagos.splice(index, 1);

    if (pagoModalEstado.pagos.length === 0) {
      agregarPagoGenerico();
      return;
    }

    renderPagos();
  }

  function agregarPagoGenerico(metodo = METODOS_PAGO[0], monto = null) {
    if (!Array.isArray(pagoModalEstado.pagos)) {
      pagoModalEstado.pagos = [];
    }

    const totales = obtenerTotalesPagos();
    let montoInicial = 0;
    if (monto === null) {
      const faltante = Math.max(0, pagoModalEstado.costo - totales.total);
      montoInicial = faltante > 0 ? faltante : 0;
    } else {
      montoInicial = monto;
    }

    pagoModalEstado.pagos.push({
      metodo,
      monto: parseFloat((montoInicial || 0).toFixed(2))
    });

    renderPagos();
  }

  function inicializarPagos() {
    pagoModalEstado.pagos = [{
      metodo: METODOS_PAGO[0],
      monto: parseFloat(pagoModalEstado.costo.toFixed(2))
    }];
    renderPagos();
  }

  function renderPagos() {
    if (!tablaPagosBody) {
      return;
    }

    tablaPagosBody.innerHTML = '';

    pagoModalEstado.pagos.forEach((pago, index) => {
      const fila = document.createElement('tr');

      const celdaMetodo = document.createElement('td');
      const selectMetodo = document.createElement('select');
      selectMetodo.className = 'form-select form-select-sm';
      const metodoActual = (pago.metodo || '').trim() || METODOS_PAGO[0];
      const saldoYaAsignado = pagoModalEstado.pagos.some((p, i) => (p.metodo || '').trim() === METODO_SALDO && i !== index);
      [...METODOS_PAGO, METODO_SALDO].forEach((metodo) => {
        if (metodo === METODO_SALDO && saldoYaAsignado && metodoActual !== METODO_SALDO) {
          return;
        }
        const option = document.createElement('option');
        option.value = metodo;
        option.textContent = metodo === METODO_SALDO ? 'Saldo disponible' : metodo;
        if (metodo === metodoActual) {
          option.selected = true;
        }
        selectMetodo.appendChild(option);
      });
      selectMetodo.addEventListener('change', (event) => handleMetodoChange(index, event.target.value));
      celdaMetodo.appendChild(selectMetodo);
      fila.appendChild(celdaMetodo);

      const celdaMonto = document.createElement('td');
      const inputMonto = document.createElement('input');
      inputMonto.type = 'number';
      inputMonto.min = '0';
      inputMonto.step = '0.01';
      inputMonto.className = 'form-control form-control-sm';
      const montoValor = parseFloat(pago.monto);
      inputMonto.value = Number.isNaN(montoValor) ? '0.00' : montoValor.toFixed(2);
      inputMonto.addEventListener('input', (event) => handleMontoChange(index, event.target.value));
      inputMonto.addEventListener('blur', () => {
        const montoActualizado = parseFloat(pagoModalEstado.pagos[index].monto);
        inputMonto.value = Number.isNaN(montoActualizado) ? '0.00' : montoActualizado.toFixed(2);
      });
      celdaMonto.appendChild(inputMonto);
      fila.appendChild(celdaMonto);

      const celdaAcciones = document.createElement('td');
      const btnEliminar = document.createElement('button');
      btnEliminar.type = 'button';
      btnEliminar.className = 'btn btn-link text-danger p-0';
      btnEliminar.textContent = 'Quitar';
      btnEliminar.addEventListener('click', () => eliminarPago(index));
      celdaAcciones.appendChild(btnEliminar);
      fila.appendChild(celdaAcciones);

      tablaPagosBody.appendChild(fila);
    });

    actualizarResumenPagos();
  }

  if (modalPagoElement) {
    modalPagoElement.addEventListener('hidden.bs.modal', function () {
      pagoModalEstado.pagos = [];
      if (tablaPagosBody) {
        tablaPagosBody.innerHTML = '';
      }
      if (resumenPagos) {
        resumenPagos.textContent = '';
      }
      if (alertaSaldoInsuficiente) {
        alertaSaldoInsuficiente.classList.add('d-none');
      }
      if (agregarPagoSaldoBtn) {
        agregarPagoSaldoBtn.removeAttribute('disabled');
      }
    });
  }

  if (agregarPagoBtn) {
    agregarPagoBtn.addEventListener('click', () => agregarPagoGenerico());
  }

  if (agregarPagoSaldoBtn) {
    agregarPagoSaldoBtn.addEventListener('click', () => {
      const totales = obtenerTotalesPagos();
      const faltante = Math.max(0, pagoModalEstado.costo - totales.total);
      let montoSugerido = faltante > 0 ? faltante : pagoModalEstado.saldo;
      montoSugerido = Math.min(pagoModalEstado.saldo, montoSugerido);
      if (montoSugerido <= 0) {
        montoSugerido = Math.min(pagoModalEstado.saldo, pagoModalEstado.costo);
      }
      agregarPagoGenerico(METODO_SALDO, montoSugerido);
    });
  }

  function actualizarCitaPago(idCita, estatus, costo, pacienteId, saldo) {
    if (!modalPagoElement) {
      return;
    }

    pagoModalEstado.idCita = idCita;
    pagoModalEstado.estatus = estatus;
    pagoModalEstado.costo = parseFloat(costo) || 0;
    pagoModalEstado.saldo = parseFloat(saldo) || 0;
    pagoModalEstado.pacienteId = pacienteId;

    if (!pagoModalEstado.modal) {
      pagoModalEstado.modal = new bootstrap.Modal(modalPagoElement, {
        keyboard: false
      });
    }

    if (costoCitaLabel) {
      costoCitaLabel.textContent = formatoMoneda.format(pagoModalEstado.costo);
    }
    if (saldoActualLabel) {
      saldoActualLabel.textContent = formatoMoneda.format(pagoModalEstado.saldo);
    }
    if (alertaSaldoInsuficiente) {
      alertaSaldoInsuficiente.classList.add('d-none');
    }

    inicializarPagos();

    pagoModalEstado.modal.show();

    const confirmarPago = document.getElementById('confirmarPago');
    if (!confirmarPago) {
      return;
    }

    confirmarPago.onclick = function () {
      if (!Array.isArray(pagoModalEstado.pagos) || pagoModalEstado.pagos.length === 0) {
        alert('Agrega al menos una forma de pago.');
        return;
      }

      const totales = obtenerTotalesPagos();
      const pagosPayload = [];

      for (const pago of pagoModalEstado.pagos) {
        const metodo = (pago.metodo || '').trim();
        const monto = parseFloat(pago.monto);
        if (!metodo) {
          alert('Selecciona una forma de pago válida.');
          return;
        }
        if (Number.isNaN(monto) || monto <= 0) {
          alert('Ingresa montos válidos para cada forma de pago.');
          return;
        }
        pagosPayload.push({
          metodo,
          monto: monto.toFixed(2)
        });
      }

      if (totales.total + 0.0001 < pagoModalEstado.costo) {
        alert('El monto total registrado es menor al costo de la cita.');
        return;
      }

      if (totales.totalSaldo > pagoModalEstado.saldo + 0.0001) {
        alert('El saldo disponible no es suficiente para cubrir el monto asignado.');
        return;
      }

      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'cancelar.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
          pagoModalEstado.modal.hide();
          location.reload();
        }
      };

      const params = new URLSearchParams();
      params.append('citaId', pagoModalEstado.idCita);
      params.append('estatus', pagoModalEstado.estatus);
      const metodosResumen = Array.from(totales.metodos);
      if (metodosResumen.length === 1) {
        params.append('formaPago', metodosResumen[0]);
      } else if (metodosResumen.length > 1) {
        params.append('formaPago', 'Mixto (' + metodosResumen.join(', ') + ')');
      }
      params.append('montoPago', totales.total.toFixed(2));
      params.append('pagos', JSON.stringify(pagosPayload));

      xhr.send(params.toString());
    };
  }
function enviarFormularioJSON() {
  const form = document.getElementById('formCita');
  const formData = new FormData(form);

  fetch('procesar_cita.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Cita guardada con éxito');
      window.location.href = 'index.php'; // o mostrar modal de éxito
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    alert('Error en el servidor: ' + error.message);
  });
}

  function actualizarCita(idCita, estatus) {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "cancelar.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
      if (xhr.readyState === 4 && xhr.status === 200) {
        location.reload();
        //alert(xhr.responseText);
        // Puedes redirigir o realizar otras acciones aquí
      }
    };

      const params = `citaId=${idCita}&estatus=${estatus}`;
      xhr.send(params);

  }
  function Reprogramar(citaId) {
    // Asigna el ID de la cita al campo del formulario
    document.getElementById('citaId').value = citaId;
    const fechaProgramadaInput = document.getElementById('fechaProgramada');
    if (fechaProgramadaInput) {
      fechaProgramadaInput.value = '';
    }

    const modalTitle = document.getElementById('updateModalLabel');
    const submitButton = document.getElementById('updateSubmitButton');
    const aviso = document.getElementById('solicitudAviso');

    if (ES_VENTAS) {
      if (modalTitle) modalTitle.textContent = 'Solicitar reprogramación';
      if (submitButton) submitButton.textContent = 'Enviar solicitud';
      if (aviso) aviso.style.display = 'block';
    } else {
      if (modalTitle) modalTitle.textContent = 'Actualizar Fecha de Cita';
      if (submitButton) submitButton.textContent = 'Actualizar';
      if (aviso) aviso.style.display = 'none';
    }

    // Abre el modal
    var updateModal = new bootstrap.Modal(document.getElementById('updateModal'));
    updateModal.show();
  }
  function validarFormulario(event) {
    event.preventDefault(); // Evita el envío del formulario

    // Obtener valores de los campos
    const cliente = document.getElementById('resumenCliente').value;
    const psicologo = document.getElementById('resumenPsicologo').value;
    const tipo = document.getElementById('resumenTipo').value;
    const costo = document.getElementById('resumenCosto').value;
    const fecha = document.getElementById('resumenFecha').value;

    // Validar que los campos no estén vacíos
    if (!cliente || !psicologo || !tipo || !costo || !fecha) {
      alert('Todos los campos son obligatorios.');
      return false;
    }

    // Validar que la fecha de la cita sea igual o mayor a la fecha actual
    const fechaCita = new Date(fecha);
    const fechaActual = new Date();

    if (fechaCita < fechaActual) {
      alert('La fecha de la cita debe ser igual o mayor a la fecha actual.');
      return false;
    }

    // Si todo es válido, enviar el formulario
enviarFormularioJSON();

  }


  function updateResumen() {
    const nameSelect = document.getElementById('nameSelect');
    const idEmpleado = document.getElementById('idEmpleado');
    const costosSelect = document.getElementById('costosSelect');
    const citaDia = document.getElementById('citaDia');

    document.getElementById('resumenCliente').value = nameSelect.options[nameSelect.selectedIndex].text;
    document.getElementById('sendIdCliente').value = nameSelect.options[nameSelect.selectedIndex].value;
    document.getElementById('resumenPsicologo').value = idEmpleado.options[idEmpleado.selectedIndex].text;
    document.getElementById('sendIdPsicologo').value = idEmpleado.options[idEmpleado.selectedIndex].value;
    var texts = costosSelect.options[costosSelect.selectedIndex].text;
    var textoLimpio = texts.replace(/[0-9:$.]/g, '');
    document.getElementById('resumenTipo').value = textoLimpio;
    document.getElementById('resumenCosto').value = costosSelect.options[costosSelect.selectedIndex].value;
    document.getElementById('resumenFecha').value = citaDia.value;

    revisarCita();
  }
  function loadAll() {
    loadCostos();
    loadNames();
    loadEmpleados();

  }
  function loadCostos() {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'Modulos/getPrecios.php', true);
    xhr.onload = function () {
      if (this.status === 200) {
        var jsonString = this.responseText;
        var jsonItems = jsonString.match(/({.*?})/g);

        // Convertir cada cadena JSON en un objeto JavaScript
        var items = jsonItems.map(function (item) {
          return JSON.parse(item);
        });
        items.forEach(function (item) {
          $('#costosSelect').append($('<option>', {
            value: item.costo,
            text: item.name
          }));
        });
        /*                     let names = JSON.parse(this.responseText);
                  let options = '<option value="">Seleccione un nombre</option>';
                  names.forEach(function (id,name) {
                      options += '<option value="${name}">${name}</option>';
                  });
                  document.getElementById('nameSelect').innerHTML = options; */
      }
    };
    xhr.send();
  }
  function loadEmpleados() {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'Modulos/getPsicologos.php', true);
    xhr.onload = function () {
      if (this.status === 200) {
        var jsonString = this.responseText;
        var jsonItems = jsonString.match(/({.*?})/g);

        // Convertir cada cadena JSON en un objeto JavaScript
        var items = jsonItems.map(function (item) {
          return JSON.parse(item);
        });
        items.forEach(function (item) {
          $('#idEmpleado').append($('<option>', {
            value: item.id,
            text: item.name
          }));
        });

      }
    };
    xhr.send();

  }

  function loadNames() {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'get_names.php', true);
    xhr.onload = function () {
      if (this.status === 200) {
        var jsonString = this.responseText;
        var jsonItems = jsonString.match(/({.*?})/g);

        // Convertir cada cadena JSON en un objeto JavaScript
        var items = jsonItems.map(function (item) {
          return JSON.parse(item);
        });
        items.forEach(function (item) {
          $('#nameSelect').append($('<option>', {
            value: item.id,
            text: item.name
          }));
        });
        /*                     let names = JSON.parse(this.responseText);
                  let options = '<option value="">Seleccione un nombre</option>';
                  names.forEach(function (id,name) {
                      options += '<option value="${name}">${name}</option>';
                  });
                  document.getElementById('nameSelect').innerHTML = options; */
      }
    };
    xhr.send();

  }

  function nini(id) {
    $("#nameSelect").val(id);
    updateResumen();
  }
  function searchNino() {

    const search = document.getElementById('search').value;

    const xhr = new XMLHttpRequest();
    xhr.open('GET', `search.php?search=${search}`, true);
    xhr.onload = function () {
      if (this.status === 200) {
        document.getElementById('results').innerHTML = this.responseText;
      }
    };
    xhr.send();

    return false; // Evitar el envío del formulario
  }

  window.onload = loadAll;

  const citaDiaInput = document.getElementById('citaDia');

  // Crear una nueva fecha que sea mañana
  const today = new Date();
  const tomorrow = new Date(today);
  tomorrow.setDate(today.getDate());

  // Formatear la fecha en el formato adecuado para datetime-local
  const year = tomorrow.getFullYear();
  const month = String(tomorrow.getMonth() + 1).padStart(2, '0');
  const day = String(tomorrow.getDate()).padStart(2, '0');
  const hours = String(tomorrow.getHours()).padStart(2, '0');
  const minutes = String(tomorrow.getMinutes()).padStart(2, '0');

  // Asignar la fecha mínima al input
  const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
  citaDiaInput.min = minDateTime;
  $(document).ready(function () {
    document.getElementById('idResultado').style.display = "none";
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
  function revisarCita() {
    var idUsuario = document.getElementById('sendIdPsicologo').value;
    var resumenFecha = document.getElementById('resumenFecha').value;
    console.log(idUsuario, resumenFecha)
    var formData = new FormData();
    formData.append('IdUsuario', idUsuario);
    formData.append('resumenFecha', resumenFecha);

    fetch('Modulos/validarCita.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        var resultadoDiv = document.getElementById('resultado');
        var resultadoDivGeneral = document.getElementById('idResultado');
        resultadoDiv.innerHTML = '';

        if (data.success) {
          resultadoDivGeneral.style.display = "none";
          resultadoDiv.innerHTML = 'La cita es válida.';
        } else {
          resultadoDivGeneral.style.display = "block";
          resultadoDiv.innerHTML = ' <p class="card-category">' + data.message + '</p>';
          if (data.citas && data.citas.length > 0) {
            data.citas.forEach(function (cita) {
              resultadoDiv.innerHTML += 'Fecha: ' + cita.fecha + ' - Niño: ' + cita.name + '<br>';
            });
          }
        }
      })
      .catch(error => console.error('Error:', error));
  };
</script>