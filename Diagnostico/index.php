<?php
include '../Modulos/head.php';

date_default_timezone_set('America/Mexico_City');

$serviciosActivos = [];
$pagosPorServicio = [];

$sqlServicios = "SELECT
        sp.id,
        sp.total,
        sp.saldo_restante,
        sp.sesiones_total,
        sp.sesiones_completadas,
        sp.estatus_id,
        sp.creado_en,
        pr.name AS servicio_nombre,
        n.id AS paciente_id,
        n.name AS paciente_nombre,
        u.id AS psicologo_id,
        u.name AS psicologo_nombre,
        es.name AS estatus_nombre,
        (SELECT COALESCE(SUM(pg.monto), 0) FROM ServicioPagos pg WHERE pg.servicio_paciente_id = sp.id) AS total_pagado,
        (SELECT MIN(ci.Programado) FROM Cita ci WHERE ci.servicio_paciente_id = sp.id AND ci.Estatus IN (2, 3) AND ci.Programado >= NOW()) AS proxima_cita
    FROM ServiciosPaciente sp
    INNER JOIN Precios pr ON pr.id = sp.precio_id
    INNER JOIN nino n ON n.id = sp.nino_id
    LEFT JOIN Usuarios u ON u.id = sp.psicologo_id
    LEFT JOIN Estatus es ON es.id = sp.estatus_id
    WHERE sp.sesiones_total > 1
      AND sp.sesiones_completadas < sp.sesiones_total
      AND sp.estatus_id IN (2, 5)
    ORDER BY sp.creado_en DESC, sp.id DESC
    LIMIT 500";

$result = $conn->query($sqlServicios);
if ($result instanceof mysqli_result) {
    $serviciosActivos = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}

$servicioIds = array_map(static function (array $servicio): int {
    return (int) $servicio['id'];
}, $serviciosActivos);

if ($servicioIds !== []) {
    $placeholders = implode(',', array_fill(0, count($servicioIds), '?'));
    $tipos = str_repeat('i', count($servicioIds));
    $sqlPagos = "SELECT
            pg.servicio_paciente_id,
            pg.id,
            pg.cita_id,
            pg.metodo,
            pg.monto,
            pg.creado_en,
            COALESCE(u.name, '') AS registrado_por_nombre
        FROM ServicioPagos pg
        LEFT JOIN Usuarios u ON u.id = pg.registrado_por
        WHERE pg.servicio_paciente_id IN ($placeholders)
        ORDER BY pg.creado_en DESC, pg.id DESC";

    if ($stmtPagos = $conn->prepare($sqlPagos)) {
        $stmtPagos->bind_param($tipos, ...$servicioIds);
        $stmtPagos->execute();
        $resultPagos = $stmtPagos->get_result();
        if ($resultPagos instanceof mysqli_result) {
            while ($pago = $resultPagos->fetch_assoc()) {
                $servicioPagoId = (int) $pago['servicio_paciente_id'];
                if (!isset($pagosPorServicio[$servicioPagoId])) {
                    $pagosPorServicio[$servicioPagoId] = [];
                }
                $pagosPorServicio[$servicioPagoId][] = [
                    'id' => (int) $pago['id'],
                    'cita_id' => $pago['cita_id'] !== null ? (int) $pago['cita_id'] : null,
                    'metodo' => (string) $pago['metodo'],
                    'monto' => (float) $pago['monto'],
                    'creado_en' => (string) $pago['creado_en'],
                    'registrado_por' => (string) $pago['registrado_por_nombre'],
                ];
            }
            $resultPagos->free();
        }
        $stmtPagos->close();
    }
}
?>

<div class="page-inner">
    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-0">Servicios activos</h3>
            <p class="text-muted mb-0">Servicios por sesiones con citas pendientes por completar.</p>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <div class="card-title mb-0">Listado</div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="tablaServiciosActivos">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Servicio</th>
                            <th>Paciente</th>
                            <th>Psicologo</th>
                            <th>Sesiones</th>
                            <th>Total</th>
                            <th>Pagado</th>
                            <th>Restante</th>
                            <th>Proxima cita</th>
                            <th>Estatus</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($serviciosActivos) === 0): ?>
                            <tr><td colspan="11" class="text-center text-muted">No hay servicios activos pendientes por completar.</td></tr>
                        <?php else: ?>
                            <?php foreach ($serviciosActivos as $servicio): ?>
                                <?php
                                    $servicioId = (int) $servicio['id'];
                                    $pacienteId = (int) $servicio['paciente_id'];
                                    $psicologoId = isset($servicio['psicologo_id']) ? (int) $servicio['psicologo_id'] : 0;
                                    $servicioNombre = (string) ($servicio['servicio_nombre'] ?? 'Servicio');
                                    $agendarUrl = '../index.php?prefill_cita=1'
                                        . '&paciente_id=' . rawurlencode((string) $pacienteId)
                                        . ($psicologoId > 0 ? '&psicologo_id=' . rawurlencode((string) $psicologoId) : '')
                                        . '&tipo=' . rawurlencode($servicioNombre);
                                    $pagosServicio = $pagosPorServicio[$servicioId] ?? [];
                                    $payloadDetalle = [
                                        'id' => $servicioId,
                                        'servicio' => $servicioNombre,
                                        'paciente' => (string) $servicio['paciente_nombre'],
                                        'total' => (float) $servicio['total'],
                                        'pagado' => (float) $servicio['total_pagado'],
                                        'restante' => (float) $servicio['saldo_restante'],
                                        'pagos' => $pagosServicio,
                                    ];
                                ?>
                                <tr>
                                    <td>#<?php echo $servicioId; ?></td>
                                    <td><?php echo htmlspecialchars($servicioNombre, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $servicio['paciente_nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($servicio['psicologo_nombre'] ?? 'Sin asignar'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int) $servicio['sesiones_completadas']; ?> / <?php echo (int) $servicio['sesiones_total']; ?></td>
                                    <td>$<?php echo number_format((float) $servicio['total'], 2); ?></td>
                                    <td>$<?php echo number_format((float) $servicio['total_pagado'], 2); ?></td>
                                    <td class="fw-semibold <?php echo (float) $servicio['saldo_restante'] > 0.009 ? 'text-danger' : 'text-success'; ?>">$<?php echo number_format((float) $servicio['saldo_restante'], 2); ?></td>
                                    <td><?php echo $servicio['proxima_cita'] ? htmlspecialchars((string) $servicio['proxima_cita'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">Sin agendar</span>'; ?></td>
                                    <td><?php echo htmlspecialchars((string) ($servicio['estatus_nombre'] ?? $servicio['estatus_id']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($agendarUrl, ENT_QUOTES, 'UTF-8'); ?>">Agendar cita</a>
                                            <button type="button" class="btn btn-sm btn-outline-secondary servicio-ver-mas" data-servicio="<?php echo htmlspecialchars(json_encode($payloadDetalle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">Ver más</button>
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

<div class="modal fade" id="modalDetalleServicio" tabindex="-1" aria-labelledby="modalDetalleServicioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalleServicioLabel">Detalle del servicio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="fw-semibold" id="detalleServicioTitulo"></div>
                    <div class="text-muted small" id="detalleServicioResumen"></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Pago</th>
                                <th>Método</th>
                                <th>Cita</th>
                                <th>Registró</th>
                            </tr>
                        </thead>
                        <tbody id="detalleServicioPagosBody">
                            <tr><td colspan="5" class="text-center text-muted">Sin pagos registrados.</td></tr>
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

<?php include '../Modulos/footer.php'; ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var moneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
        var modalDetalleEl = document.getElementById('modalDetalleServicio');
        var modalDetalle = modalDetalleEl && window.bootstrap ? new bootstrap.Modal(modalDetalleEl) : null;
        var detalleTitulo = document.getElementById('detalleServicioTitulo');
        var detalleResumen = document.getElementById('detalleServicioResumen');
        var pagosBody = document.getElementById('detalleServicioPagosBody');

        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.DataTable === 'function') {
            window.jQuery('#tablaServiciosActivos').DataTable({
                language: {
                    lengthMenu: 'Mostrar _MENU_ servicios',
                    zeroRecords: 'No se encontraron servicios',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ servicios',
                    infoEmpty: 'Sin servicios activos',
                    infoFiltered: '(filtrado de _MAX_ servicios)',
                    search: 'Buscar:',
                    paginate: {
                        first: 'Primero',
                        last: 'Ultimo',
                        next: 'Siguiente',
                        previous: 'Anterior'
                    }
                },
                order: [[0, 'desc']],
                pageLength: 25
            });
        }

        document.addEventListener('click', function (event) {
            var button = event.target && event.target.closest ? event.target.closest('.servicio-ver-mas') : null;
            if (!button || !modalDetalle) {
                return;
            }

            var payload = null;
            try {
                payload = JSON.parse(button.getAttribute('data-servicio') || '{}');
            } catch (e) {
                payload = null;
            }
            if (!payload) {
                return;
            }

            if (detalleTitulo) {
                detalleTitulo.textContent = '#' + payload.id + ' - ' + (payload.servicio || 'Servicio') + ' / ' + (payload.paciente || '');
            }
            if (detalleResumen) {
                detalleResumen.textContent = 'Total ' + moneda.format(Number(payload.total || 0)) + ' | Pagado ' + moneda.format(Number(payload.pagado || 0)) + ' | Restante ' + moneda.format(Number(payload.restante || 0));
            }
            if (pagosBody) {
                pagosBody.innerHTML = '';
                var pagos = Array.isArray(payload.pagos) ? payload.pagos : [];
                if (pagos.length === 0) {
                    pagosBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Sin pagos registrados.</td></tr>';
                } else {
                    pagos.forEach(function (pago) {
                        var tr = document.createElement('tr');
                        var cita = pago.cita_id ? ('#' + pago.cita_id) : 'Sin cita';
                        tr.innerHTML = '<td>' + escapeHtml(pago.creado_en || '') + '</td>'
                            + '<td>' + moneda.format(Number(pago.monto || 0)) + '</td>'
                            + '<td>' + escapeHtml(pago.metodo || '') + '</td>'
                            + '<td>' + escapeHtml(cita) + '</td>'
                            + '<td>' + escapeHtml(pago.registrado_por || '-') + '</td>';
                        pagosBody.appendChild(tr);
                    });
                }
            }

            modalDetalle.show();
        });

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    });
</script>
