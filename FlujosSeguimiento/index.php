<?php
include __DIR__ . '/../Modulos/head.php';
require_once __DIR__ . '/flujos_lib.php';

$csrfToken = flujos_csrf_token();

$flujos = [];
$result = $conn->query("SELECT f.id_flujo, f.nombre, f.descripcion, f.activo, f.fecha_creacion, COUNT(t.id_tarea) AS total_tareas
    FROM exp_flujos f
    LEFT JOIN exp_flujo_tareas t ON t.id_flujo = f.id_flujo AND t.activo = 1
    GROUP BY f.id_flujo, f.nombre, f.descripcion, f.activo, f.fecha_creacion
    ORDER BY f.fecha_creacion DESC");
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $flujos[] = $row;
    }
}
?>

<div class="page-inner">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h3 class="fw-bold mb-3">Flujos de Seguimiento</h3>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-info" href="verificar_instalacion.php">Verificar instalacion</a>
            <a class="btn btn-outline-primary" href="asignaciones.php">Asignaciones</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>Crear / editar flujo</strong></div>
                <div class="card-body">
                    <form id="flowForm">
                        <input type="hidden" name="id_flujo" id="id_flujo" value="0">
                        <div class="mb-3"><label class="form-label">Nombre</label><input class="form-control" name="nombre" id="flujo_nombre" required></div>
                        <div class="mb-3"><label class="form-label">Descripcion</label><textarea class="form-control" name="descripcion" id="flujo_descripcion"></textarea></div>
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="activo" id="flujo_activo" checked><label class="form-check-label" for="flujo_activo">Activo</label></div>
                        <button class="btn btn-primary" type="submit">Guardar flujo</button>
                        <button class="btn btn-outline-secondary" type="button" id="resetFlow">Limpiar</button>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><strong>Agregar tarea plantilla</strong></div>
                <div class="card-body">
                    <form id="taskForm">
                        <input type="hidden" name="id_tarea" value="0">
                        <div class="mb-2"><label class="form-label">Flujo</label><select class="form-select" name="id_flujo" required><?php foreach ($flujos as $f): ?><option value="<?php echo (int)$f['id_flujo']; ?>"><?php echo htmlspecialchars($f['nombre']); ?></option><?php endforeach; ?></select></div>
                        <div class="mb-2"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
                        <div class="mb-2"><label class="form-label">Descripcion</label><textarea class="form-control" name="descripcion"></textarea></div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label">Orden</label><input type="number" class="form-control" name="orden" value="0"></div>
                            <div class="col-6"><label class="form-label">Dias venc.</label><input type="number" class="form-control" name="dias_vencimiento" min="0"></div>
                            <div class="col-6"><label class="form-label">Tipo</label><select class="form-select" name="tipo_tarea"><option value="recordatorio">Recordatorio</option><option value="subir_documento">Subir documento</option><option value="llamada">Llamada</option><option value="seguimiento" selected>Seguimiento</option><option value="otro">Otro</option></select></div>
                            <div class="col-6"><label class="form-label">Responsable</label><select class="form-select" name="responsable"><option value="psicologa">Psicologa</option><option value="administracion">Administracion</option><option value="paciente">Paciente</option><option value="escuela">Escuela</option><option value="otro">Otro</option></select></div>
                        </div>
                        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="obligatoria" checked id="task_obligatoria"><label class="form-check-label" for="task_obligatoria">Obligatoria</label></div>
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="requiere_documento" id="task_doc"><label class="form-check-label" for="task_doc">Requiere documento</label></div>
                        <button class="btn btn-primary" type="submit">Guardar tarea</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><strong>Flujos registrados</strong></div>
                <div class="card-body table-responsive">
                    <table class="table table-striped align-middle">
                        <thead><tr><th>ID</th><th>Flujo</th><th>Tareas</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach ($flujos as $f): ?>
                                <tr>
                                    <td><?php echo (int)$f['id_flujo']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($f['nombre']); ?></strong><div class="small text-muted"><?php echo htmlspecialchars((string)$f['descripcion']); ?></div></td>
                                    <td><?php echo (int)$f['total_tareas']; ?></td>
                                    <td><?php echo (int)$f['activo'] === 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary edit-flow" data-id="<?php echo (int)$f['id_flujo']; ?>" data-nombre="<?php echo htmlspecialchars($f['nombre'], ENT_QUOTES); ?>" data-descripcion="<?php echo htmlspecialchars((string)$f['descripcion'], ENT_QUOTES); ?>" data-activo="<?php echo (int)$f['activo']; ?>">Editar</button>
                                        <button class="btn btn-sm btn-outline-info show-tasks" data-id="<?php echo (int)$f['id_flujo']; ?>">Tareas</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header"><strong>Tareas del flujo</strong></div>
                <div class="card-body" id="tasksBox"><p class="text-muted mb-0">Selecciona un flujo para ver sus tareas.</p></div>
            </div>
        </div>
    </div>
</div>

<script>
function formToJson(form) {
    const data = Object.fromEntries(new FormData(form).entries());
    data.activo = form.querySelector('[name="activo"]') ? form.querySelector('[name="activo"]').checked ? 1 : 0 : data.activo;
    data.obligatoria = form.querySelector('[name="obligatoria"]') ? form.querySelector('[name="obligatoria"]').checked ? 1 : 0 : data.obligatoria;
    data.requiere_documento = form.querySelector('[name="requiere_documento"]') ? form.querySelector('[name="requiere_documento"]').checked ? 1 : 0 : data.requiere_documento;
    return data;
}
function postJson(url, data) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>' }, credentials: 'same-origin', body: JSON.stringify(data) }).then(r => r.json().then(j => ({ ok: r.ok, data: j })));
}
document.getElementById('flowForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const data = formToJson(event.currentTarget);
    const url = Number(data.id_flujo || 0) > 0 ? 'editar_flujo.php' : 'crear_flujo.php';
    postJson(url, data).then(result => { if (!result.ok || !result.data.success) throw new Error(result.data.message || 'No se pudo guardar'); location.reload(); }).catch(e => alert(e.message));
});
document.getElementById('taskForm').addEventListener('submit', function (event) {
    event.preventDefault();
    postJson('guardar_tarea_flujo.php', formToJson(event.currentTarget)).then(result => { if (!result.ok || !result.data.success) throw new Error(result.data.message || 'No se pudo guardar'); location.reload(); }).catch(e => alert(e.message));
});
document.querySelectorAll('.edit-flow').forEach(btn => btn.addEventListener('click', function () {
    document.getElementById('id_flujo').value = this.dataset.id;
    document.getElementById('flujo_nombre').value = this.dataset.nombre || '';
    document.getElementById('flujo_descripcion').value = this.dataset.descripcion || '';
    document.getElementById('flujo_activo').checked = this.dataset.activo === '1';
}));
document.getElementById('resetFlow').addEventListener('click', function () { document.getElementById('flowForm').reset(); document.getElementById('id_flujo').value = '0'; });
document.querySelectorAll('.show-tasks').forEach(btn => btn.addEventListener('click', function () {
    fetch('listar_tareas_flujo.php?id_flujo=' + encodeURIComponent(this.dataset.id), { credentials: 'same-origin' }).then(r => r.json()).then(payload => {
        const rows = (payload.data || []).map(t => '<tr><td>' + t.orden + '</td><td>' + t.nombre + '</td><td>' + t.tipo_tarea + '</td><td>' + t.responsable + '</td><td>' + (Number(t.activo) === 1 ? 'Activa' : 'Inactiva') + '</td></tr>').join('');
        document.getElementById('tasksBox').innerHTML = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Orden</th><th>Tarea</th><th>Tipo</th><th>Responsable</th><th>Estado</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
    });
}));
</script>

<?php include __DIR__ . '/../Modulos/footer.php'; ?>
