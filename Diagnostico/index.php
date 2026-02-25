<?php
include '../Modulos/head.php';

date_default_timezone_set('America/Mexico_City');

$hoy = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');

$citasHoy = [];
$procesos = [];


    $sqlHoy = "SELECT
                ci.id AS cita_id,
                ci.Programado,
                ci.Estatus AS cita_estatus_id,
                es.name AS cita_estatus_nombre,
                ci.IdNino AS paciente_id,
                n.name AS paciente_nombre,
                ci.IdUsuario AS psicologo_id,
                u.name AS psicologo_nombre,
                ci.diagnostico_id,
                ci.diagnostico_sesion,
                d.sesiones_total,
                d.sesiones_completadas,
                d.total,
                d.saldo_restante,
                d.estatus_id AS diagnostico_estatus_id,
                esd.name AS diagnostico_estatus_nombre
            FROM Cita ci
            INNER JOIN nino n ON n.id = ci.IdNino
            LEFT JOIN Usuarios u ON u.id = ci.IdUsuario
            INNER JOIN Estatus es ON es.id = ci.Estatus
            INNER JOIN Diagnosticos d ON d.id = ci.diagnostico_id
            LEFT JOIN Estatus esd ON esd.id = d.estatus_id
            WHERE ci.diagnostico_id IS NOT NULL
              AND DATE(ci.Programado) = ?
              AND ci.Estatus IN (2, 3)
            ORDER BY ci.Programado ASC";

    if ($stmt = $conn->prepare($sqlHoy)) {
        $stmt->bind_param('s', $hoy);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            $citasHoy = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }

    $sqlProcesos = "SELECT
                d.id,
                d.total,
                d.pago_inicial,
                d.saldo_restante,
                d.sesiones_total,
                d.sesiones_completadas,
                d.estatus_id,
                es.name AS estatus_nombre,
                d.creado_en,
                n.id AS paciente_id,
                n.name AS paciente_nombre,
                u.id AS psicologo_id,
                u.name AS psicologo_nombre,
                (SELECT COALESCE(SUM(p.monto), 0) FROM DiagnosticoPagos p WHERE p.diagnostico_id = d.id) AS total_pagado
            FROM Diagnosticos d
            INNER JOIN nino n ON n.id = d.nino_id
            LEFT JOIN Usuarios u ON u.id = d.psicologo_id
            LEFT JOIN Estatus es ON es.id = d.estatus_id
            WHERE d.estatus_id IN (2, 5)
            ORDER BY d.creado_en DESC
            LIMIT 500";

    $result = $conn->query($sqlProcesos);
    if ($result instanceof mysqli_result) {
        $procesos = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }
?>

<div class="page-inner">
    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
        <h3 class="fw-bold mb-0">Diagnostico</h3>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" id="btnNuevoDiagnostico">Nuevo diagnostico</button>
        </div>
    </div>



    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-title mb-0">Diagnosticos del dia (<?php echo htmlspecialchars($hoy, ENT_QUOTES, 'UTF-8'); ?>)</div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle" id="tablaDiagnosticosHoy">
                            <thead class="table-light">
                                <tr>
                                    <th>Hora</th>
                                    <th>Cita</th>
                                    <th>Paciente</th>
                                    <th>Psicologo</th>
                                    <th>Sesion</th>
                                    <th>Estatus cita</th>
                                    <th>Restante</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php if (count($citasHoy) === 0): ?>
                                    <tr><td colspan="8" class="text-muted text-center">No hay citas de diagnostico para hoy.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($citasHoy as $row): ?>
                                        <?php
                                            $programado = (string) $row['Programado'];
                                            $hora = '';
                                            try {
                                                $dt = new DateTime($programado);
                                                $hora = $dt->format('H:i');
                                            } catch (Throwable $e) {
                                                $hora = $programado;
                                            }
                                            $restante = (float) $row['saldo_restante'];
                                            $payload = [
                                                'citaId' => (int) $row['cita_id'],
                                                'diagnosticoId' => (int) $row['diagnostico_id'],
                                                'pacienteId' => (int) $row['paciente_id'],
                                                'pacienteNombre' => (string) $row['paciente_nombre'],
                                                'psicologoId' => isset($row['psicologo_id']) ? (int) $row['psicologo_id'] : null,
                                                'psicologoNombre' => (string) ($row['psicologo_nombre'] ?? ''),
                                                'programado' => $programado,
                                                'sesion' => (int) $row['diagnostico_sesion'],
                                                'sesionesTotal' => (int) $row['sesiones_total'],
                                                'sesionesCompletadas' => (int) $row['sesiones_completadas'],
                                                'saldoRestante' => $restante,
                                            ];
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($hora, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>#<?php echo (int) $row['cita_id']; ?></td>
                                            <td><?php echo htmlspecialchars((string) $row['paciente_nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['psicologo_nombre'] ?? 'Sin asignar'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo (int) $row['diagnostico_sesion']; ?> / <?php echo (int) $row['sesiones_total']; ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['cita_estatus_nombre'] ?? $row['cita_estatus_id']), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="fw-semibold <?php echo $restante > 0.009 ? 'text-danger' : 'text-success'; ?>">$<?php echo number_format($restante, 2); ?></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-action="reprogramar" data-payload="<?php echo htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8'); ?>">Reprogramar</button>
                                                    <button type="button" class="btn btn-sm btn-primary" data-action="finalizar" data-payload="<?php echo htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8'); ?>">Finalizar</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-title mb-0">Procesos activos</div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle" id="tablaProcesos">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Paciente</th>
                                    <th>Psicologo</th>
                                    <th>Sesiones</th>
                                    <th>Total</th>
                                    <th>Pagado</th>
                                    <th>Restante</th>
                                    <th>Estatus</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php if (count($procesos) === 0): ?>
                                    <tr><td colspan="9" class="text-muted text-center">No hay procesos activos.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($procesos as $row): ?>
                                        <?php
                                            $restante = (float) $row['saldo_restante'];
                                            $payload = [
                                                'diagnosticoId' => (int) $row['id'],
                                                'pacienteId' => (int) $row['paciente_id'],
                                                'pacienteNombre' => (string) $row['paciente_nombre'],
                                                'psicologoId' => isset($row['psicologo_id']) ? (int) $row['psicologo_id'] : null,
                                                'psicologoNombre' => (string) ($row['psicologo_nombre'] ?? ''),
                                                'total' => (float) $row['total'],
                                                'pagado' => (float) $row['total_pagado'],
                                                'restante' => $restante,
                                                'sesionesTotal' => (int) $row['sesiones_total'],
                                                'sesionesCompletadas' => (int) $row['sesiones_completadas'],
                                                'estatusId' => (int) $row['estatus_id'],
                                            ];
                                        ?>
                                        <tr>
                                            <td>#<?php echo (int) $row['id']; ?></td>
                                            <td><?php echo htmlspecialchars((string) $row['paciente_nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['psicologo_nombre'] ?? 'Sin asignar'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo (int) $row['sesiones_completadas']; ?> / <?php echo (int) $row['sesiones_total']; ?></td>
                                            <td>$<?php echo number_format((float) $row['total'], 2); ?></td>
                                            <td>$<?php echo number_format((float) $row['total_pagado'], 2); ?></td>
                                            <td class="fw-semibold <?php echo $restante > 0.009 ? 'text-danger' : 'text-success'; ?>">$<?php echo number_format($restante, 2); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['estatus_nombre'] ?? $row['estatus_id']), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-action="pagar" data-payload="<?php echo htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8'); ?>">Registrar pago</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-action="agendar" data-payload="<?php echo htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8'); ?>">Agendar cita</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevoDiagnostico" tabindex="-1" aria-labelledby="modalNuevoDiagnosticoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formNuevoDiagnostico">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNuevoDiagnosticoLabel">Nuevo diagnostico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear">

                    <div class="mb-3">
                        <label class="form-label" for="diagPaciente">Paciente</label>
                        <select class="form-select" id="diagPaciente" name="nino_id" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="diagPsicologo">Psicologo</label>
                        <select class="form-select" id="diagPsicologo" name="psicologo_id" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="diagFecha">Fecha primer sesion</label>
                        <input type="datetime-local" class="form-control" id="diagFecha" name="fecha" step="3600" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="diagSesiones">Sesiones incluidas</label>
                            <input type="number" class="form-control" id="diagSesiones" name="sesiones_total" min="1" step="1" value="4" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="diagTotal">Total</label>
                            <input type="number" class="form-control" id="diagTotal" name="total" min="0" step="0.01" value="4500.00" required>
                        </div>
                    </div>
                    <hr>
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="diagPagoInicial">Pago inicial</label>
                            <input type="number" class="form-control" id="diagPagoInicial" name="pago_inicial" min="0" step="0.01" value="0.00">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="diagMetodo">Metodo</label>
                            <select class="form-select" id="diagMetodo" name="metodo">
                                <option value="">Sin pago</option>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Tarjeta">Tarjeta</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-text">Si registras pago inicial, se descuenta del total y el resto queda como adeudo del diagnostico.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalReprogramar" tabindex="-1" aria-labelledby="modalReprogramarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formReprogramar">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalReprogramarLabel">Reprogramar cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="accion" value="reprogramar">
                    <input type="hidden" name="cita_id" id="reprogCitaId">
                    <div class="mb-2">
                        <div class="fw-semibold" id="reprogTitulo"></div>
                        <div class="text-muted small" id="reprogMeta"></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="reprogFecha">Nueva fecha</label>
                        <input type="datetime-local" class="form-control" id="reprogFecha" name="fecha" step="3600" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFinalizar" tabindex="-1" aria-labelledby="modalFinalizarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formFinalizar">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFinalizarLabel">Finalizar sesion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="accion" value="finalizar">
                    <input type="hidden" name="cita_id" id="finCitaId">
                    <div class="mb-2">
                        <div class="fw-semibold" id="finTitulo"></div>
                        <div class="text-muted small" id="finMeta"></div>
                    </div>
                    <div class="alert alert-danger py-2 d-none" id="finAdeudoBox">
                        Adeudo restante: <span class="fw-semibold" id="finAdeudoRestante"></span>
                    </div>
                    <div class="row g-2 mb-3 d-none" id="finPagoRow">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="finPagoMonto">Pago</label>
                            <input type="number" class="form-control" id="finPagoMonto" name="pago_monto" min="0" step="0.01" value="0.00">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="finPagoMetodo">Metodo</label>
                            <select class="form-select" id="finPagoMetodo" name="pago_metodo">
                                <option value="">Selecciona una opcion</option>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Tarjeta">Tarjeta</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0 d-none" id="finProximaRow">
                        <label class="form-label" for="finProximaFecha">Proxima cita</label>
                        <input type="datetime-local" class="form-control" id="finProximaFecha" name="proxima_fecha" step="3600">
                        <div class="form-text">Solo se pide si faltan sesiones del diagnostico.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Finalizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPago" tabindex="-1" aria-labelledby="modalPagoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formPago">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPagoLabel">Registrar pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="accion" value="pagar">
                    <input type="hidden" name="diagnostico_id" id="pagoDiagnosticoId">
                    <div class="mb-2">
                        <div class="fw-semibold" id="pagoTitulo"></div>
                        <div class="text-muted small" id="pagoMeta"></div>
                    </div>
                    <div class="alert alert-danger py-2">
                        Restante: <span class="fw-semibold" id="pagoRestante"></span>
                    </div>
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="pagoMonto">Cantidad</label>
                            <input type="number" class="form-control" id="pagoMonto" name="monto" min="0" step="0.01" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="pagoMetodo">Metodo</label>
                            <select class="form-select" id="pagoMetodo" name="metodo" required>
                                <option value="">Selecciona una opcion</option>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Tarjeta">Tarjeta</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAgendar" tabindex="-1" aria-labelledby="modalAgendarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formAgendar">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAgendarLabel">Agendar cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="accion" value="agendar">
                    <input type="hidden" name="diagnostico_id" id="agendarDiagnosticoId">
                    <div class="mb-2">
                        <div class="fw-semibold" id="agendarTitulo"></div>
                        <div class="text-muted small" id="agendarMeta"></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="agendarFecha">Fecha y hora</label>
                        <input type="datetime-local" class="form-control" id="agendarFecha" name="fecha" step="3600" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agendar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../Modulos/footer.php'; ?>

<script>
    (function () {
        const API_URL = '../api/diagnostico.php';
        const formatoMoneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

        function initDataTable(id, order) {
            const el = document.getElementById(id);
            if (!el || !window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.DataTable !== 'function') {
                return;
            }
            window.jQuery(el).DataTable({
                language: {
                    lengthMenu: 'Mostrar _MENU_',
                    zeroRecords: 'Sin resultados',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_',
                    infoEmpty: 'Sin registros',
                    infoFiltered: '(filtrado de _MAX_)',
                    search: 'Buscar:',
                    paginate: {
                        first: 'Primero',
                        last: 'Ultimo',
                        next: 'Siguiente',
                        previous: 'Anterior'
                    }
                },
                order: order || [],
                pageLength: 25
            });
        }

        initDataTable('tablaDiagnosticosHoy', [[0, 'asc']]);
        initDataTable('tablaProcesos', [[0, 'desc']]);

        const modalNuevo = document.getElementById('modalNuevoDiagnostico');
        const modalReprog = document.getElementById('modalReprogramar');
        const modalFin = document.getElementById('modalFinalizar');
        const modalPago = document.getElementById('modalPago');
        const modalAgendar = document.getElementById('modalAgendar');

        const instNuevo = modalNuevo && window.bootstrap ? new bootstrap.Modal(modalNuevo) : null;
        const instReprog = modalReprog && window.bootstrap ? new bootstrap.Modal(modalReprog) : null;
        const instFin = modalFin && window.bootstrap ? new bootstrap.Modal(modalFin) : null;
        const instPago = modalPago && window.bootstrap ? new bootstrap.Modal(modalPago) : null;
        const instAgendar = modalAgendar && window.bootstrap ? new bootstrap.Modal(modalAgendar) : null;

        const btnNuevo = document.getElementById('btnNuevoDiagnostico');
        const diagPaciente = document.getElementById('diagPaciente');
        const diagPsicologo = document.getElementById('diagPsicologo');
        const formNuevo = document.getElementById('formNuevoDiagnostico');

        function cargarPacientes() {
            if (!diagPaciente) return;
            diagPaciente.innerHTML = '<option value="">Cargando...</option>';
            fetch('../get_names.php', { credentials: 'same-origin' })
                .then((r) => r.json())
                .then((data) => {
                    diagPaciente.innerHTML = '<option value="">Selecciona una opcion</option>';
                    if (!Array.isArray(data)) return;
                    data.forEach((item) => {
                        if (!item || !item.id) return;
                        const opt = document.createElement('option');
                        opt.value = String(item.id);
                        opt.textContent = item.name || ('Paciente #' + item.id);
                        diagPaciente.appendChild(opt);
                    });
                })
                .catch(() => {
                    diagPaciente.innerHTML = '<option value="">No fue posible cargar pacientes</option>';
                });
        }

        function cargarPsicologos() {
            if (!diagPsicologo) return;
            diagPsicologo.innerHTML = '<option value="">Cargando...</option>';
            fetch('../Modulos/getPsicologos.php', { credentials: 'same-origin' })
                .then((r) => r.json())
                .then((data) => {
                    diagPsicologo.innerHTML = '<option value="">Selecciona una opcion</option>';
                    if (!Array.isArray(data)) return;
                    data.forEach((item) => {
                        if (!item || !item.id) return;
                        const opt = document.createElement('option');
                        opt.value = String(item.id);
                        opt.textContent = item.name || ('Psicologo #' + item.id);
                        diagPsicologo.appendChild(opt);
                    });
                })
                .catch(() => {
                    diagPsicologo.innerHTML = '<option value="">No fue posible cargar psicologos</option>';
                });
        }

        cargarPacientes();
        cargarPsicologos();

        if (btnNuevo && instNuevo) {
            btnNuevo.addEventListener('click', () => instNuevo.show());
        }

        function postForm(form) {
            const fd = new FormData(form);
            return fetch(API_URL, {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
                .then((r) => r.json().catch(() => null))
                .then((data) => {
                    if (!data || data.success !== true) {
                        throw new Error(data && data.message ? data.message : 'No fue posible procesar la solicitud.');
                    }
                    window.location.reload();
                });
        }

        if (formNuevo) {
            formNuevo.addEventListener('submit', (e) => {
                e.preventDefault();
                postForm(formNuevo).catch((err) => alert(err.message || 'Error al crear el diagnostico.'));
            });
        }

        const formReprog = document.getElementById('formReprogramar');
        const reprogCitaId = document.getElementById('reprogCitaId');
        const reprogTitulo = document.getElementById('reprogTitulo');
        const reprogMeta = document.getElementById('reprogMeta');
        const reprogFecha = document.getElementById('reprogFecha');
        if (formReprog) {
            formReprog.addEventListener('submit', (e) => {
                e.preventDefault();
                postForm(formReprog).catch((err) => alert(err.message || 'Error al reprogramar.'));
            });
        }

        const formFin = document.getElementById('formFinalizar');
        const finCitaId = document.getElementById('finCitaId');
        const finTitulo = document.getElementById('finTitulo');
        const finMeta = document.getElementById('finMeta');
        const finAdeudoBox = document.getElementById('finAdeudoBox');
        const finAdeudoRestante = document.getElementById('finAdeudoRestante');
        const finPagoRow = document.getElementById('finPagoRow');
        const finPagoMonto = document.getElementById('finPagoMonto');
        const finPagoMetodo = document.getElementById('finPagoMetodo');
        const finProximaRow = document.getElementById('finProximaRow');
        const finProximaFecha = document.getElementById('finProximaFecha');
        if (formFin) {
            formFin.addEventListener('submit', (e) => {
                e.preventDefault();
                postForm(formFin).catch((err) => alert(err.message || 'Error al finalizar.'));
            });
        }

        const formPago = document.getElementById('formPago');
        const pagoDiagnosticoId = document.getElementById('pagoDiagnosticoId');
        const pagoTitulo = document.getElementById('pagoTitulo');
        const pagoMeta = document.getElementById('pagoMeta');
        const pagoRestante = document.getElementById('pagoRestante');
        const pagoMonto = document.getElementById('pagoMonto');
        const pagoMetodo = document.getElementById('pagoMetodo');
        if (formPago) {
            formPago.addEventListener('submit', (e) => {
                e.preventDefault();
                postForm(formPago).catch((err) => alert(err.message || 'Error al guardar el pago.'));
            });
        }

        const formAgendar = document.getElementById('formAgendar');
        const agendarDiagnosticoId = document.getElementById('agendarDiagnosticoId');
        const agendarTitulo = document.getElementById('agendarTitulo');
        const agendarMeta = document.getElementById('agendarMeta');
        const agendarFecha = document.getElementById('agendarFecha');
        if (formAgendar) {
            formAgendar.addEventListener('submit', (e) => {
                e.preventDefault();
                postForm(formAgendar).catch((err) => alert(err.message || 'Error al agendar.'));
            });
        }

        document.addEventListener('click', function (event) {
            const btn = event.target && event.target.closest ? event.target.closest('button[data-action]') : null;
            if (!btn) return;
            const action = btn.getAttribute('data-action');
            const payloadText = btn.getAttribute('data-payload');
            if (!action || !payloadText) return;
            let payload = null;
            try { payload = JSON.parse(payloadText); } catch (e) { payload = null; }
            if (!payload) return;

            if (action === 'reprogramar' && instReprog) {
                reprogCitaId.value = String(payload.citaId || '');
                reprogTitulo.textContent = payload.pacienteNombre ? payload.pacienteNombre : '';
                reprogMeta.textContent = 'Cita #' + payload.citaId + ' - Sesion ' + payload.sesion + ' de ' + payload.sesionesTotal;
                reprogFecha.value = '';
                instReprog.show();
            }

            if (action === 'finalizar' && instFin) {
                finCitaId.value = String(payload.citaId || '');
                finTitulo.textContent = payload.pacienteNombre ? payload.pacienteNombre : '';
                finMeta.textContent = 'Sesion ' + payload.sesion + ' de ' + payload.sesionesTotal + ' (Cita #' + payload.citaId + ')';

                const restante = Number(payload.saldoRestante || 0);
                if (finAdeudoBox && finAdeudoRestante && finPagoRow) {
                    if (restante > 0.009) {
                        finAdeudoBox.classList.remove('d-none');
                        finPagoRow.classList.remove('d-none');
                        finAdeudoRestante.textContent = formatoMoneda.format(restante);
                    } else {
                        finAdeudoBox.classList.add('d-none');
                        finPagoRow.classList.add('d-none');
                        finAdeudoRestante.textContent = '';
                    }
                }
                if (finPagoMonto) finPagoMonto.value = '0.00';
                if (finPagoMetodo) finPagoMetodo.value = '';

                if (finProximaRow && finProximaFecha) {
                    const faltan = Number(payload.sesion || 0) < Number(payload.sesionesTotal || 0);
                    finProximaRow.classList.toggle('d-none', !faltan);
                    finProximaFecha.required = faltan;
                    finProximaFecha.value = '';
                }

                instFin.show();
            }

            if (action === 'pagar' && instPago) {
                pagoDiagnosticoId.value = String(payload.diagnosticoId || '');
                pagoTitulo.textContent = payload.pacienteNombre ? payload.pacienteNombre : '';
                pagoMeta.textContent = 'Diagnostico #' + payload.diagnosticoId;
                pagoRestante.textContent = formatoMoneda.format(Number(payload.restante || payload.saldoRestante || 0));
                pagoMonto.value = '';
                pagoMetodo.value = '';
                instPago.show();
            }

            if (action === 'agendar' && instAgendar) {
                agendarDiagnosticoId.value = String(payload.diagnosticoId || payload.diagnosticoId || '');
                agendarTitulo.textContent = payload.pacienteNombre ? payload.pacienteNombre : '';
                agendarMeta.textContent = 'Diagnostico #' + payload.diagnosticoId;
                agendarFecha.value = '';
                instAgendar.show();
            }
        });
    }());
</script>
