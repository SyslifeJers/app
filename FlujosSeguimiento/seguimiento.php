<?php
include __DIR__ . '/../Modulos/head.php';
require_once __DIR__ . '/flujos_lib.php';

$csrfToken = flujos_csrf_token();

$idAsignacion = isset($_GET['id_asignacion']) ? (int)$_GET['id_asignacion'] : 0;
$asignacion = null;
$tareas = [];
$historial = [];
$psicologas = [];
$documentosPorTarea = [];

if ($idAsignacion > 0) {
    $stmt = $conn->prepare('SELECT a.*, f.nombre AS flujo_nombre, n.name AS paciente FROM exp_flujo_asignaciones a INNER JOIN exp_flujos f ON f.id_flujo = a.id_flujo INNER JOIN nino n ON n.id = a.id_nino WHERE a.id_asignacion = ? LIMIT 1');
    $stmt->bind_param('i', $idAsignacion);
    $stmt->execute();
    $asignacion = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmtT = $conn->prepare('SELECT ta.*, u.name AS responsable_nombre FROM exp_flujo_tareas_asignadas ta LEFT JOIN Usuarios u ON u.id = ta.responsable_usuario_id WHERE ta.id_asignacion = ? ORDER BY ta.orden ASC, ta.fecha_limite ASC, ta.id_tarea_asignada ASC');
    $stmtT->bind_param('i', $idAsignacion);
    $stmtT->execute();
    $tareas = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtT->close();

    $tareaIds = array_map(static function (array $tarea): int {
        return (int)$tarea['id_tarea_asignada'];
    }, $tareas);
    if (!empty($tareaIds)) {
        $ids = implode(',', array_map('intval', $tareaIds));
        $resDocs = $conn->query("SELECT id_tarea_asignada, ruta, nombre_original, comentario, fecha_subida FROM exp_flujo_documentos WHERE activo = 1 AND id_tarea_asignada IN ($ids) ORDER BY fecha_subida DESC");
        if ($resDocs instanceof mysqli_result) {
            while ($doc = $resDocs->fetch_assoc()) {
                $documentosPorTarea[(int)$doc['id_tarea_asignada']][] = $doc;
            }
        }
    }

    $stmtH = $conn->prepare('SELECT h.*, u.name AS usuario FROM exp_flujo_historial h LEFT JOIN Usuarios u ON u.id = h.usuario_id WHERE h.id_asignacion = ? ORDER BY h.fecha DESC LIMIT 200');
    $stmtH->bind_param('i', $idAsignacion);
    $stmtH->execute();
    $historial = $stmtH->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtH->close();
}

$resPsicologas = $conn->query("SELECT u.id, u.name FROM Usuarios u INNER JOIN Rol r ON r.id = u.IdRol WHERE u.activo = 1 AND LOWER(r.name) LIKE '%psicolog%' ORDER BY u.name ASC");
if ($resPsicologas instanceof mysqli_result) {
    while ($row = $resPsicologas->fetch_assoc()) $psicologas[] = $row;
}
?>

<div class="page-inner">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h3 class="fw-bold mb-3">Seguimiento de flujo</h3>
        <a class="btn btn-outline-secondary" href="asignaciones.php">Asignaciones</a>
    </div>

    <?php if (!$asignacion): ?>
        <div class="alert alert-warning">Asignacion no encontrada.</div>
    <?php else: ?>
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap justify-content-between gap-3">
                <div><strong><?php echo htmlspecialchars($asignacion['flujo_nombre']); ?></strong><div class="text-muted"><?php echo htmlspecialchars($asignacion['paciente']); ?></div></div>
                <div><span class="badge bg-primary"><?php echo htmlspecialchars($asignacion['estatus']); ?></span></div>
                <div class="fw-bold">Avance: <?php echo number_format((float)$asignacion['avance'], 0); ?>%</div>
                <button class="btn btn-outline-danger btn-sm" id="cancelFlow">Cancelar flujo</button>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>Tareas</strong></div>
            <div class="card-body table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th>Tarea</th><th>Responsable</th><th>Fecha limite</th><th>Estatus</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($tareas as $t): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($t['nombre']); ?></strong><div class="small text-muted"><?php echo htmlspecialchars((string)$t['descripcion']); ?></div><?php if (!empty($t['plantilla_ruta'])): ?><div class="small"><a target="_blank" href="<?php echo htmlspecialchars((string)$t['plantilla_ruta']); ?>">Plantilla: <?php echo htmlspecialchars((string)$t['plantilla_nombre_original']); ?></a></div><?php endif; ?><?php if (!empty($t['id_examen'])): ?><div class="small text-muted">Evaluacion ID: <?php echo (int)$t['id_examen']; ?></div><?php endif; ?><?php foreach (($documentosPorTarea[(int)$t['id_tarea_asignada']] ?? []) as $doc): ?><div class="small"><a target="_blank" href="<?php echo htmlspecialchars((string)$doc['ruta']); ?>"><?php echo htmlspecialchars((string)$doc['nombre_original']); ?></a><?php if (!empty($doc['comentario'])): ?> <span class="text-muted">- <?php echo htmlspecialchars((string)$doc['comentario']); ?></span><?php endif; ?></div><?php endforeach; ?></td>
                                <td><?php echo htmlspecialchars($t['responsable']); ?><?php if ($t['responsable_nombre']): ?><div class="small text-muted"><?php echo htmlspecialchars($t['responsable_nombre']); ?></div><?php endif; ?></td>
                                <td><input class="form-control form-control-sm fecha-limite" data-id="<?php echo (int)$t['id_tarea_asignada']; ?>" type="date" value="<?php echo htmlspecialchars((string)$t['fecha_limite']); ?>"></td>
                                <td><?php echo htmlspecialchars($t['estatus']); ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <select class="form-select form-select-sm responsable-user" data-id="<?php echo (int)$t['id_tarea_asignada']; ?>" style="width:160px"><option value="">Responsable</option><?php foreach ($psicologas as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?></select>
                                        <form class="admin-doc-form d-flex gap-1" enctype="multipart/form-data" style="max-width:260px">
                                            <input type="hidden" name="id_tarea_asignada" value="<?php echo (int)$t['id_tarea_asignada']; ?>">
                                            <input class="form-control form-control-sm" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Subir</button>
                                        </form>
                                        <button class="btn btn-sm btn-outline-danger cancel-task" data-id="<?php echo (int)$t['id_tarea_asignada']; ?>">Cancelar</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>Agregar tarea extraordinaria</strong></div>
            <div class="card-body">
                <form id="extraTaskForm" class="row g-2">
                    <input type="hidden" name="id_asignacion" value="<?php echo (int)$idAsignacion; ?>">
                    <input type="hidden" name="id_nino" value="<?php echo (int)$asignacion['id_nino']; ?>">
                    <div class="col-md-4"><input class="form-control" name="nombre" placeholder="Nombre" required></div>
                    <div class="col-md-4"><input class="form-control" name="descripcion" placeholder="Descripcion"></div>
                    <div class="col-md-2"><input class="form-control" name="fecha_limite" type="date"></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Agregar</button></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Historial</strong></div>
            <div class="card-body table-responsive">
                <table class="table table-sm"><thead><tr><th>Fecha</th><th>Accion</th><th>Descripcion</th><th>Usuario</th></tr></thead><tbody><?php foreach ($historial as $h): ?><tr><td><?php echo htmlspecialchars($h['fecha']); ?></td><td><?php echo htmlspecialchars($h['accion']); ?></td><td><?php echo htmlspecialchars((string)$h['descripcion']); ?></td><td><?php echo htmlspecialchars((string)$h['usuario']); ?></td></tr><?php endforeach; ?></tbody></table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function postJson(url, data) { return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>' }, credentials: 'same-origin', body: JSON.stringify(data) }).then(r => r.json().then(j => ({ ok: r.ok, data: j }))); }
function postForm(url, form) { return fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>' }, credentials: 'same-origin', body: new FormData(form) }).then(r => r.json().then(j => ({ ok: r.ok, data: j }))); }
document.querySelectorAll('.fecha-limite').forEach(input => input.addEventListener('change', function () { postJson('editar_fecha_limite.php', { id_tarea_asignada: this.dataset.id, fecha_limite: this.value }).then(r => { if (!r.ok || !r.data.success) throw new Error(r.data.message || 'Error'); location.reload(); }).catch(e => alert(e.message)); }));
document.querySelectorAll('.responsable-user').forEach(sel => sel.addEventListener('change', function () { if (!this.value) return; postJson('reasignar_responsable.php', { id_tarea_asignada: this.dataset.id, responsable: 'psicologa', responsable_usuario_id: this.value }).then(r => { if (!r.ok || !r.data.success) throw new Error(r.data.message || 'Error'); location.reload(); }).catch(e => alert(e.message)); }));
document.querySelectorAll('.cancel-task').forEach(btn => btn.addEventListener('click', function () { if (!confirm('Cancelar tarea?')) return; postJson('cancelar_tarea.php', { id_tarea_asignada: this.dataset.id, motivo: 'Cancelada desde seguimiento administrativo' }).then(r => { if (!r.ok || !r.data.success) throw new Error(r.data.message || 'Error'); location.reload(); }).catch(e => alert(e.message)); }));
document.querySelectorAll('.admin-doc-form').forEach(form => form.addEventListener('submit', function (event) { event.preventDefault(); postForm('subir_documento_tarea.php', event.currentTarget).then(r => { if (!r.ok || !r.data.success) throw new Error(r.data.message || 'Error'); location.reload(); }).catch(e => alert(e.message)); }));
var cancelFlow = document.getElementById('cancelFlow');
if (cancelFlow) cancelFlow.addEventListener('click', function () { if (!confirm('Cancelar flujo completo?')) return; postJson('cancelar_flujo.php', { id_asignacion: <?php echo (int)$idAsignacion; ?>, motivo: 'Cancelado desde seguimiento administrativo' }).then(r => { if (!r.ok || !r.data.success) throw new Error(r.data.message || 'Error'); location.href = 'asignaciones.php'; }).catch(e => alert(e.message)); });
var extraForm = document.getElementById('extraTaskForm');
if (extraForm) extraForm.addEventListener('submit', function (event) { event.preventDefault(); postJson('agregar_tarea_extra.php', Object.fromEntries(new FormData(event.currentTarget).entries())).then(r => { if (!r.ok || !r.data.success) throw new Error(r.data.message || 'Error'); location.reload(); }).catch(e => alert(e.message)); });
</script>

<?php include __DIR__ . '/../Modulos/footer.php'; ?>
