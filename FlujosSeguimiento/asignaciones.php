<?php
include __DIR__ . '/../Modulos/head.php';
require_once __DIR__ . '/flujos_lib.php';

$csrfToken = flujos_csrf_token();

$flujos = [];
$resFlujos = $conn->query("SELECT id_flujo, nombre FROM exp_flujos WHERE activo = 1 ORDER BY nombre ASC");
if ($resFlujos instanceof mysqli_result) {
    while ($row = $resFlujos->fetch_assoc()) $flujos[] = $row;
}

$pacientes = [];
$resPacientes = $conn->query("SELECT n.id, n.name AS paciente, c.name AS tutor, CONCAT('#', n.id, ' - ', n.name, ' - Tutor: ', COALESCE(c.name, 'Sin tutor')) AS nombre FROM nino n LEFT JOIN Clientes c ON c.id = n.idtutor WHERE n.activo = 1 ORDER BY n.name ASC");
if ($resPacientes instanceof mysqli_result) {
    while ($row = $resPacientes->fetch_assoc()) $pacientes[] = $row;
}

$psicologas = [];
$resPsicologas = $conn->query("SELECT u.id, u.name, r.name AS rol_nombre FROM Usuarios u INNER JOIN Rol r ON r.id = u.IdRol WHERE u.activo = 1 AND (LOWER(r.name) LIKE '%psicolog%' OR LOWER(r.name) LIKE '%admin%' OR r.id IN (1, 3, 5)) ORDER BY r.name ASC, u.name ASC");
if ($resPsicologas instanceof mysqli_result) {
    while ($row = $resPsicologas->fetch_assoc()) $psicologas[] = $row;
}
?>

<div class="page-inner">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h3 class="fw-bold mb-3">Asignaciones de Flujos</h3>
        <a class="btn btn-outline-secondary" href="index.php">Flujos</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>Asignar flujo a paciente</strong></div>
                <div class="card-body">
                    <form id="assignForm">
                        <div class="mb-3"><label class="form-label">Flujo</label><select class="form-select" name="id_flujo" required><?php foreach ($flujos as $f): ?><option value="<?php echo (int)$f['id_flujo']; ?>"><?php echo htmlspecialchars($f['nombre']); ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3">
                            <label class="form-label">Paciente</label>
                            <input class="form-control" id="patientSearch" type="search" list="patientsList" placeholder="Buscar por nombre, tutor o ID" autocomplete="off" required>
                            <input id="patientSelect" name="id_nino" type="hidden" value="">
                            <datalist id="patientsList">
                                <?php foreach ($pacientes as $p): ?>
                                    <?php $nombrePaciente = (string)($p['paciente'] ?? ''); $nombreTutor = (string)($p['tutor'] ?? 'Sin tutor'); ?>
                                    <option data-id="<?php echo (int)$p['id']; ?>" value="<?php echo htmlspecialchars('#' . (int)$p['id'] . ' - ' . $nombrePaciente . ' - Tutor: ' . $nombreTutor, ENT_QUOTES, 'UTF-8'); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">Selecciona una opción de la lista para asignar el paciente correcto.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Psicologa / administrador responsable</label>
                            <input class="form-control form-control-sm mb-2" id="responsibleSearch" type="search" placeholder="Buscar responsable">
                            <select class="form-select" id="responsibleSelect" name="psicologo_id" required><option value="" data-search="">Selecciona responsable</option><?php foreach ($psicologas as $p): ?><?php $responsableTexto = trim((string)$p['name'] . ' - ' . (string)($p['rol_nombre'] ?? '')); ?><option value="<?php echo (int)$p['id']; ?>" data-search="<?php echo htmlspecialchars(strtolower($responsableTexto), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($responsableTexto); ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="mb-3"><label class="form-label">Observaciones iniciales</label><textarea class="form-control" name="observaciones"></textarea></div>
                        <button class="btn btn-primary" type="submit">Asignar flujo</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"><strong>Flujos asignados</strong><div class="d-flex gap-2"><input class="form-control form-control-sm" id="assignmentSearch" type="search" placeholder="Buscar asignacion"><button class="btn btn-sm btn-outline-primary" id="reloadAssignments">Actualizar</button></div></div>
                <div class="card-body" id="assignmentsBox"><p class="text-muted mb-0">Cargando...</p></div>
            </div>
        </div>
    </div>
</div>

<script>
let assignments = [];

function normalizeText(value) {
    return String(value == null ? '' : value).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function filterSelectOptions(inputId, selectId) {
    const input = document.getElementById(inputId);
    const select = document.getElementById(selectId);
    const term = normalizeText(input.value);
    let firstVisible = null;

    Array.from(select.options).forEach(option => {
        const match = normalizeText(option.dataset.search || option.textContent).includes(term);
        option.hidden = !match;
        if (match && firstVisible === null) firstVisible = option;
    });

    if (select.selectedOptions.length > 0 && select.selectedOptions[0].hidden && firstVisible) {
        select.value = firstVisible.value;
    }
}

function postJson(url, data) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>' }, credentials: 'same-origin', body: JSON.stringify(data) }).then(r => r.json().then(j => ({ ok: r.ok, data: j })));
}

function syncPatientId() {
    const value = document.getElementById('patientSearch').value;
    const hidden = document.getElementById('patientSelect');
    const option = Array.from(document.getElementById('patientsList').options).find(item => item.value === value);
    hidden.value = option ? option.dataset.id : '';
}

function renderAssignments() {
    const term = normalizeText(document.getElementById('assignmentSearch').value);
    const filtered = assignments.filter(a => normalizeText([a.id_asignacion, a.paciente, a.flujo_nombre, a.estatus].join(' ')).includes(term));
    const rows = filtered.map(a => {
        const deleteButton = Number(a.puede_eliminar || 0) === 1 ? '<button class="btn btn-sm btn-outline-danger ms-1" type="button" onclick="deleteAssignment(' + Number(a.id_asignacion) + ')">Eliminar</button>' : '';
        return '<tr><td>' + a.id_asignacion + '</td><td>' + a.paciente + '</td><td>' + a.flujo_nombre + '</td><td>' + a.estatus + '</td><td>' + Number(a.avance).toFixed(0) + '%</td><td>' + (a.tareas_vencidas || 0) + '</td><td><a class="btn btn-sm btn-outline-primary" href="seguimiento.php?id_asignacion=' + a.id_asignacion + '">Ver</a>' + deleteButton + '</td></tr>';
    }).join('');
    document.getElementById('assignmentsBox').innerHTML = '<div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>ID</th><th>Paciente</th><th>Flujo</th><th>Estatus</th><th>Avance</th><th>Vencidas</th><th></th></tr></thead><tbody>' + (rows || '<tr><td colspan="7" class="text-center text-muted">Sin asignaciones.</td></tr>') + '</tbody></table></div>';
}

function deleteAssignment(id) {
    if (!confirm('Esta asignacion se ocultara solo si no tiene cambios ni datos en tareas. ¿Continuar?')) return;
    postJson('eliminar_asignacion.php', { id_asignacion: id }).then(result => {
        if (!result.ok || !result.data.success) throw new Error(result.data.message || 'No se pudo eliminar');
        loadAssignments();
    }).catch(e => alert(e.message));
}

function loadAssignments() {
    fetch('listar_asignaciones.php', { credentials: 'same-origin' }).then(r => r.json()).then(payload => {
        assignments = payload.data || [];
        renderAssignments();
    }).catch(e => { document.getElementById('assignmentsBox').innerHTML = '<div class="alert alert-danger">No fue posible cargar asignaciones.</div>'; });
}
document.getElementById('patientSearch').addEventListener('input', syncPatientId);
document.getElementById('responsibleSearch').addEventListener('input', function () { filterSelectOptions('responsibleSearch', 'responsibleSelect'); });
document.getElementById('assignmentSearch').addEventListener('input', renderAssignments);
document.getElementById('assignForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const form = event.currentTarget;
    syncPatientId();
    if (!document.getElementById('patientSelect').value) {
        alert('Selecciona un paciente valido de la lista.');
        return;
    }
    if (!document.getElementById('responsibleSelect').value) {
        alert('Selecciona un responsable.');
        return;
    }
    const data = Object.fromEntries(new FormData(form).entries());
    postJson('asignar_flujo.php', data).then(result => { if (!result.ok || !result.data.success) throw new Error(result.data.message || 'No se pudo asignar'); form.reset(); document.getElementById('patientSelect').value = ''; loadAssignments(); }).catch(e => alert(e.message));
});
document.getElementById('reloadAssignments').addEventListener('click', loadAssignments);
loadAssignments();
</script>

<?php include __DIR__ . '/../Modulos/footer.php'; ?>
