<?php
include '../Modulos/head.php';

$mensajeAdeudos = $_SESSION['adeudos_mensaje'] ?? null;
$tipoMensajeAdeudos = $_SESSION['adeudos_tipo'] ?? 'info';
unset($_SESSION['adeudos_mensaje'], $_SESSION['adeudos_tipo']);

$adeudos = [];
$sql = "SELECT
            ad.id,
            ad.total,
            ad.saldo_restante,
            ad.estatus_id,
            ad.creado_en,
            n.id AS paciente_id,
            n.name AS paciente_nombre,
            u.id AS psicologo_id,
            u.name AS psicologo_nombre,
            es.name AS estatus_nombre,
            (SELECT COALESCE(SUM(p.monto), 0) FROM AdeudosDiagnosticoPagos p WHERE p.adeudo_id = ad.id) AS total_pagado
        FROM AdeudosDiagnostico ad
        INNER JOIN nino n ON n.id = ad.nino_id
        LEFT JOIN Usuarios u ON u.id = ad.psicologo_id
        LEFT JOIN Estatus es ON es.id = ad.estatus_id
        ORDER BY ad.creado_en DESC
        LIMIT 500";

$result = $conn->query($sql);
if ($result instanceof mysqli_result) {
    while ($fila = $result->fetch_assoc()) {
        $adeudos[] = $fila;
    }
    $result->free();
}
?>

<div class="page-inner">
    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
        <h3 class="fw-bold mb-0">Adeudos</h3>
        <div class="text-muted">Adeudos de diagnostico con pagos parciales.</div>
    </div>

    <?php if ($mensajeAdeudos): ?>
        <div class="alert alert-<?php echo htmlspecialchars($tipoMensajeAdeudos, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show mt-3" role="alert">
            <?php echo htmlspecialchars($mensajeAdeudos, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="card mt-3">
        <div class="card-header">
            <div class="card-title mb-0">Listado</div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="tablaAdeudos">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Paciente</th>
                            <th>Psicologo</th>
                            <th>Total</th>
                            <th>Pagado</th>
                            <th>Restante</th>
                            <th>Estatus</th>
                            <th>Creado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($adeudos) === 0): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Sin adeudos registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($adeudos as $row): ?>
                                <?php
                                    $adeudoId = (int) $row['id'];
                                    $total = (float) $row['total'];
                                    $pagado = (float) $row['total_pagado'];
                                    $restante = (float) $row['saldo_restante'];
                                    $estatusNombre = (string) ($row['estatus_nombre'] ?? '');
                                    $psicologoNombre = (string) ($row['psicologo_nombre'] ?? '');
                                    $pacienteNombre = (string) ($row['paciente_nombre'] ?? '');
                                    $payload = [
                                        'adeudoId' => $adeudoId,
                                        'pacienteId' => (int) $row['paciente_id'],
                                        'pacienteNombre' => $pacienteNombre,
                                        'psicologoId' => isset($row['psicologo_id']) ? (int) $row['psicologo_id'] : null,
                                        'psicologoNombre' => $psicologoNombre,
                                        'total' => $total,
                                        'pagado' => $pagado,
                                        'restante' => $restante,
                                        'estatusId' => (int) $row['estatus_id'],
                                        'estatusNombre' => $estatusNombre,
                                    ];
                                ?>
                                <tr>
                                    <td>#<?php echo $adeudoId; ?></td>
                                    <td><?php echo htmlspecialchars($pacienteNombre, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($psicologoNombre !== '' ? $psicologoNombre : 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>$<?php echo number_format($total, 2); ?></td>
                                    <td>$<?php echo number_format($pagado, 2); ?></td>
                                    <td class="fw-semibold <?php echo $restante > 0.009 ? 'text-danger' : 'text-success'; ?>">$<?php echo number_format($restante, 2); ?></td>
                                    <td><?php echo htmlspecialchars($estatusNombre !== '' ? $estatusNombre : (string) $row['estatus_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $row['creado_en'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-action="pagar" data-adeudo="<?php echo htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8'); ?>">Registrar pago</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="agendar" data-adeudo="<?php echo htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8'); ?>">Nueva cita</button>
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

<div class="modal fade" id="modalPagoAdeudo" tabindex="-1" aria-labelledby="modalPagoAdeudoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formPagoAdeudo">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPagoAdeudoLabel">Registrar pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="accion" value="registrar_pago">
                    <input type="hidden" name="adeudo_id" id="pagoAdeudoId">

                    <div class="mb-2">
                        <div class="fw-semibold" id="pagoAdeudoPaciente"></div>
                        <div class="text-muted small" id="pagoAdeudoMeta"></div>
                    </div>

                    <div class="alert alert-danger py-2">
                        Restante: <span class="fw-semibold" id="pagoAdeudoRestante"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="pagoAdeudoMonto">Cantidad a abonar</label>
                        <input type="number" class="form-control" id="pagoAdeudoMonto" name="monto" min="0" step="0.01" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="pagoAdeudoMetodo">Metodo</label>
                        <select class="form-select" id="pagoAdeudoMetodo" name="metodo" required>
                            <option value="">Selecciona una opcion</option>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Tarjeta">Tarjeta</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAgendarAdeudo" tabindex="-1" aria-labelledby="modalAgendarAdeudoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formAgendarAdeudo">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAgendarAdeudoLabel">Agendar nueva cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="accion" value="agendar_cita">
                    <input type="hidden" name="adeudo_id" id="agendarAdeudoId">

                    <div class="mb-2">
                        <div class="fw-semibold" id="agendarAdeudoPaciente"></div>
                        <div class="text-muted small" id="agendarAdeudoMeta"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="agendarAdeudoFecha">Fecha y hora</label>
                        <input type="datetime-local" class="form-control" id="agendarAdeudoFecha" name="fecha" step="3600" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="agendarAdeudoPsicologo">Psicologo</label>
                        <select class="form-select" id="agendarAdeudoPsicologo" name="psicologo_id" required>
                            <option value="">Cargando...</option>
                        </select>
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
        const tabla = document.getElementById('tablaAdeudos');
        if (tabla && window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.DataTable === 'function') {
            window.jQuery(tabla).DataTable({
                language: {
                    lengthMenu: 'Mostrar _MENU_ adeudos',
                    zeroRecords: 'No se encontraron adeudos',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ adeudos',
                    infoEmpty: 'Sin adeudos registrados',
                    infoFiltered: '(filtrado de _MAX_ adeudos)',
                    search: 'Buscar:',
                    paginate: {
                        first: 'Primero',
                        last: 'Ultimo',
                        next: 'Siguiente',
                        previous: 'Anterior'
                    }
                },
                order: [[0, 'desc']],
                pageLength: 25,
            });
        }

        const modalPagoEl = document.getElementById('modalPagoAdeudo');
        const modalAgendarEl = document.getElementById('modalAgendarAdeudo');
        const modalPago = modalPagoEl && window.bootstrap ? new bootstrap.Modal(modalPagoEl) : null;
        const modalAgendar = modalAgendarEl && window.bootstrap ? new bootstrap.Modal(modalAgendarEl) : null;

        const pagoAdeudoId = document.getElementById('pagoAdeudoId');
        const pagoAdeudoPaciente = document.getElementById('pagoAdeudoPaciente');
        const pagoAdeudoMeta = document.getElementById('pagoAdeudoMeta');
        const pagoAdeudoRestante = document.getElementById('pagoAdeudoRestante');
        const pagoAdeudoMonto = document.getElementById('pagoAdeudoMonto');
        const pagoAdeudoMetodo = document.getElementById('pagoAdeudoMetodo');

        const agendarAdeudoId = document.getElementById('agendarAdeudoId');
        const agendarAdeudoPaciente = document.getElementById('agendarAdeudoPaciente');
        const agendarAdeudoMeta = document.getElementById('agendarAdeudoMeta');
        const agendarAdeudoFecha = document.getElementById('agendarAdeudoFecha');
        const agendarAdeudoPsicologo = document.getElementById('agendarAdeudoPsicologo');

        const formatoMoneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

        function cargarPsicologos() {
            if (!agendarAdeudoPsicologo) {
                return;
            }
            fetch('../Modulos/getPsicologos.php', { credentials: 'same-origin' })
                .then((r) => r.json())
                .then((data) => {
                    agendarAdeudoPsicologo.innerHTML = '<option value="">Selecciona una opcion</option>';
                    if (!Array.isArray(data)) {
                        return;
                    }
                    data.forEach((item) => {
                        if (!item || !item.id) {
                            return;
                        }
                        const opt = document.createElement('option');
                        opt.value = String(item.id);
                        opt.textContent = item.name || ('Psicologo #' + item.id);
                        agendarAdeudoPsicologo.appendChild(opt);
                    });
                })
                .catch(() => {
                    agendarAdeudoPsicologo.innerHTML = '<option value="">No fue posible cargar psicologos</option>';
                });
        }

        cargarPsicologos();

        document.addEventListener('click', function (event) {
            const button = event.target && event.target.closest ? event.target.closest('button[data-action]') : null;
            if (!button) {
                return;
            }
            const action = button.getAttribute('data-action');
            const payloadText = button.getAttribute('data-adeudo');
            if (!action || !payloadText) {
                return;
            }
            let payload = null;
            try {
                payload = JSON.parse(payloadText);
            } catch (e) {
                payload = null;
            }
            if (!payload || !payload.adeudoId) {
                return;
            }

            if (action === 'pagar' && modalPago) {
                pagoAdeudoId.value = String(payload.adeudoId);
                pagoAdeudoPaciente.textContent = payload.pacienteNombre || '';
                pagoAdeudoMeta.textContent = 'Adeudo #' + payload.adeudoId + ' - Total ' + formatoMoneda.format(payload.total || 0);
                pagoAdeudoRestante.textContent = formatoMoneda.format(payload.restante || 0);
                pagoAdeudoMonto.value = '';
                pagoAdeudoMetodo.value = '';
                modalPago.show();
            }

            if (action === 'agendar' && modalAgendar) {
                agendarAdeudoId.value = String(payload.adeudoId);
                agendarAdeudoPaciente.textContent = payload.pacienteNombre || '';
                agendarAdeudoMeta.textContent = 'Adeudo #' + payload.adeudoId + ' - Restante ' + formatoMoneda.format(payload.restante || 0);
                agendarAdeudoFecha.value = '';
                if (payload.psicologoId && agendarAdeudoPsicologo) {
                    agendarAdeudoPsicologo.value = String(payload.psicologoId);
                }
                modalAgendar.show();
            }
        });

        function postAdeudos(form) {
            const formData = new FormData(form);
            return fetch('../api/adeudos_diagnostico.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
                .then((r) => r.json().catch(() => null))
                .then((data) => {
                    if (!data || data.success !== true) {
                        throw new Error(data && data.message ? data.message : 'No fue posible procesar la solicitud.');
                    }
                    window.location.reload();
                });
        }

        const formPago = document.getElementById('formPagoAdeudo');
        if (formPago) {
            formPago.addEventListener('submit', function (e) {
                e.preventDefault();
                postAdeudos(formPago).catch((err) => alert(err.message || 'Error al guardar el pago.'));
            });
        }

        const formAgendar = document.getElementById('formAgendarAdeudo');
        if (formAgendar) {
            formAgendar.addEventListener('submit', function (e) {
                e.preventDefault();
                postAdeudos(formAgendar).catch((err) => alert(err.message || 'Error al agendar la cita.'));
            });
        }
    }());
</script>
