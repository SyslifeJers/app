<?php
include '../Modulos/head.php';

$rolUsuario = $_SESSION['rol'] ?? 0;
if ($rolUsuario !== 3) {
    header('Location: /index.php');
    exit;
}

$moduloFiltro = isset($_GET['modulo']) ? trim($_GET['modulo']) : '';
if ($moduloFiltro !== '') {
    $moduloFiltro = mb_substr($moduloFiltro, 0, 100, 'UTF-8');
}

$tablaLogsExiste = false;
if ($resultado = $conn->query("SHOW TABLES LIKE 'LogSistema'")) {
    $tablaLogsExiste = $resultado->num_rows > 0;
    $resultado->free();
}

$registros = [];
$modulosDisponibles = [];
if ($tablaLogsExiste) {
    if ($resultadoModulos = $conn->query('SELECT DISTINCT modulo FROM LogSistema ORDER BY modulo ASC')) {
        while ($filaModulo = $resultadoModulos->fetch_assoc()) {
            if (!empty($filaModulo['modulo'])) {
                $modulosDisponibles[] = $filaModulo['modulo'];
            }
        }
        $resultadoModulos->free();
    }

    $limite = 500;
    $consultaBase = 'SELECT ls.id, ls.fecha, ls.modulo, ls.accion, ls.descripcion, ls.entidad, ls.referencia, ls.ip, us.name AS usuario_nombre FROM LogSistema ls LEFT JOIN Usuarios us ON us.id = ls.usuario_id';

    if ($moduloFiltro !== '' && ($stmtLogs = $conn->prepare($consultaBase . ' WHERE ls.modulo = ? ORDER BY ls.fecha DESC LIMIT ?'))) {
        $stmtLogs->bind_param('si', $moduloFiltro, $limite);
    } elseif ($moduloFiltro === '' && ($stmtLogs = $conn->prepare($consultaBase . ' ORDER BY ls.fecha DESC LIMIT ?'))) {
        $stmtLogs->bind_param('i', $limite);
    }

    if (isset($stmtLogs) && $stmtLogs !== false) {
        $stmtLogs->execute();
        $resultadoLogs = $stmtLogs->get_result();
        while ($fila = $resultadoLogs->fetch_assoc()) {
            $registros[] = $fila;
        }
        $stmtLogs->close();
    }
}
?>

<div class="row">
  <div class="col-md-12">
    <div class="card">
      <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <div>
          <h4 class="card-title mb-0">Logs del sistema</h4>
          <span class="text-muted small">Mostrando hasta 500 eventos recientes</span>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <?php if ($tablaLogsExiste): ?>
            <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
              <label for="filtroModulo" class="form-label mb-0 me-1">Módulo:</label>
              <select id="filtroModulo" name="modulo" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($modulosDisponibles as $modulo): ?>
                  <option value="<?php echo htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $modulo === $moduloFiltro ? 'selected' : ''; ?>><?php echo htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
              <?php if ($moduloFiltro !== ''): ?>
                <a class="btn btn-link btn-sm" href="index.php">Limpiar</a>
              <?php endif; ?>
            </form>
          <?php endif; ?>
          <?php if ($tablaLogsExiste && !empty($registros)): ?>
            <?php $parametrosExportacion = $moduloFiltro !== '' ? '?modulo=' . urlencode($moduloFiltro) : ''; ?>
            <a class="btn btn-success btn-sm" href="exportar_excel.php<?php echo $parametrosExportacion; ?>">
              <i class="fas fa-file-excel me-1"></i>
              Exportar a Excel
            </a>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body">
        <?php if (!$tablaLogsExiste): ?>
          <div class="alert alert-warning mb-0">
            La tabla de logs aún no se ha creado. Ejecuta las migraciones para habilitar esta sección.
          </div>
        <?php elseif (empty($registros)): ?>
          <div class="alert alert-info mb-0">
            No se encontraron eventos registrados.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table id="tablaLogs" class="table table-striped">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Usuario</th>
                  <th>Módulo</th>
                  <th>Acción</th>
                  <th>Descripción</th>
                  <th>Entidad</th>
                  <th>Referencia</th>
                  <th>IP</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($registros as $registro): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($registro['fecha'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($registro['usuario_nombre'] ?? 'Sin registro', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($registro['modulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($registro['accion'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($registro['descripcion'], ENT_QUOTES, 'UTF-8')); ?></td>
                    <td><?php echo htmlspecialchars($registro['entidad'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($registro['referencia'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($registro['ip'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include '../Modulos/footer.php'; ?>

<script>
  $(document).ready(function () {
    const tabla = $('#tablaLogs');
    if (tabla.length) {
      tabla.DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
          url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        }
      });
    }
  });
</script>
