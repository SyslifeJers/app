<?php

declare(strict_types=1);

include '../Modulos/head.php';

$mensaje = $_SESSION['solicitud_saldo_mensaje'] ?? null;
$tipoMensaje = $_SESSION['solicitud_saldo_tipo'] ?? 'info';
unset($_SESSION['solicitud_saldo_mensaje'], $_SESSION['solicitud_saldo_tipo']);

$estado = isset($_GET['estado']) ? strtolower(trim((string) $_GET['estado'])) : 'pendiente';
$estadosPermitidos = ['pendiente', 'aprobada', 'rechazada', 'todas'];
if (!in_array($estado, $estadosPermitidos, true)) {
    $estado = 'pendiente';
}

require_once __DIR__ . '/../conexion.php';

$connSolicitudes = conectar();
$solicitudes = [];
$errorMensaje = null;

if ($connSolicitudes) {
    $sql = "SELECT s.id, s.monto, s.saldo_anterior, s.saldo_solicitado, s.comentario, s.estatus, s.fecha_solicitud, s.fecha_resolucion, s.respuesta,
                   n.name AS paciente_nombre, n.id AS paciente_id, n.saldo_paquete AS saldo_actual,
                   c.name AS tutor_nombre,
                   solicitante.user AS solicitante_usuario,
                   aprobador.user AS aprobador_usuario
            FROM SolicitudAjusteSaldo s
            INNER JOIN nino n ON n.id = s.nino_id
            LEFT JOIN Clientes c ON c.id = n.idtutor
            LEFT JOIN Usuarios solicitante ON solicitante.id = s.solicitado_por
            LEFT JOIN Usuarios aprobador ON aprobador.id = s.aprobado_por";

    $tipos = '';
    $parametros = [];

    if ($estado !== 'todas') {
        $sql .= ' WHERE s.estatus = ?';
        $tipos .= 's';
        $parametros[] = $estado;
    }

    $sql .= ' ORDER BY s.fecha_solicitud DESC';

    $stmt = $connSolicitudes->prepare($sql);

    if ($stmt) {
        if ($tipos !== '') {
            $stmt->bind_param($tipos, ...$parametros);
        }

        if ($stmt->execute()) {
            $resultado = $stmt->get_result();
            if ($resultado) {
                while ($fila = $resultado->fetch_assoc()) {
                    $solicitudes[] = $fila;
                }
            }
            $stmt->close();
        } else {
            $errorMensaje = 'No fue posible obtener las solicitudes registradas.';
        }
    } else {
        $errorMensaje = 'No fue posible preparar la consulta de solicitudes.';
    }

    $connSolicitudes->close();
} else {
    $errorMensaje = 'No se pudo conectar a la base de datos.';
}

function formatCurrency(float $value): string
{
    return '$' . number_format($value, 2);
}

function formatDate(?string $fecha): string
{
    if ($fecha === null) {
        return '-';
    }

    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return $fecha;
    }

    return date('d/m/Y H:i', $timestamp);
}

$estadosLabels = [
    'pendiente' => 'Pendientes',
    'aprobada' => 'Aprobadas',
    'rechazada' => 'Rechazadas',
    'todas' => 'Todas',
];

?>

<div class="container mt-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Solicitudes de ajuste de saldo</h1>
            <p class="text-muted mb-0">Revisa y procesa los ajustes solicitados por el equipo.</p>
        </div>
        <div class="btn-group" role="group" aria-label="Filtro por estado">
            <?php foreach ($estadosLabels as $valor => $label): ?>
                <?php $url = $valor === 'todas' ? 'solicitudes_saldo.php' : ('solicitudes_saldo.php?estado=' . $valor); ?>
                <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                   class="btn btn-outline-primary <?php echo $estado === $valor ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo htmlspecialchars($tipoMensaje, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <?php if ($errorMensaje): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($errorMensaje, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle" id="tablaSolicitudesSaldo">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Paciente</th>
                                <th>Solicitado por</th>
                                <th>Saldo actual</th>
                                <th>Ajuste</th>
                                <th>Saldo solicitado</th>
                                <th>Fecha solicitud</th>
                                <th>Estatus</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($solicitudes) === 0): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No hay solicitudes para el filtro seleccionado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($solicitudes as $fila): ?>
                                <?php
                                    $ajuste = (float) $fila['saldo_solicitado'] - (float) $fila['saldo_anterior'];
                                    $saldoActual = (float) $fila['saldo_actual'];
                                ?>
                                <tr>
                                    <td>#<?php echo (int) $fila['id']; ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($fila['paciente_nombre'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="text-muted small">Tutor: <?php echo htmlspecialchars($fila['tutor_nombre'] ?? 'Sin tutor', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($fila['solicitante_usuario'] ?? 'Desconocido', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if (!empty($fila['comentario'])): ?>
                                            <div class="text-muted small">“<?php echo htmlspecialchars($fila['comentario'], ENT_QUOTES, 'UTF-8'); ?>”</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(formatCurrency($saldoActual), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="fw-semibold <?php echo $ajuste >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo htmlspecialchars(formatCurrency($ajuste), ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(formatCurrency((float) $fila['saldo_solicitado']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars(formatDate($fila['fecha_solicitud']), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if (!empty($fila['fecha_resolucion'])): ?>
                                            <div class="text-muted small">Resuelto: <?php echo htmlspecialchars(formatDate($fila['fecha_resolucion']), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $fila['estatus'] === 'pendiente' ? 'warning text-dark' : ($fila['estatus'] === 'aprobada' ? 'success' : 'secondary'); ?>">
                                            <?php echo ucfirst($fila['estatus']); ?>
                                        </span>
                                        <?php if (!empty($fila['aprobador_usuario'])): ?>
                                            <div class="text-muted small">Por: <?php echo htmlspecialchars($fila['aprobador_usuario'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($fila['respuesta'])): ?>
                                            <div class="text-muted small">“<?php echo htmlspecialchars($fila['respuesta'], ENT_QUOTES, 'UTF-8'); ?>”</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($fila['estatus'] === 'pendiente' && in_array($rol, [3, 5], true)): ?>
                                            <div class="d-flex flex-column gap-2">
                                                <form method="post" action="procesar_solicitud_saldo.php" onsubmit="return confirm('¿Deseas aprobar esta solicitud y ajustar el saldo?');">
                                                    <input type="hidden" name="solicitud_id" value="<?php echo (int) $fila['id']; ?>">
                                                    <input type="hidden" name="accion" value="aprobar">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" name="comentario" class="form-control" placeholder="Comentario (opcional)">
                                                        <button type="submit" class="btn btn-success">Aprobar</button>
                                                    </div>
                                                </form>
                                                <form method="post" action="procesar_solicitud_saldo.php" onsubmit="return confirm('¿Deseas rechazar esta solicitud?');">
                                                    <input type="hidden" name="solicitud_id" value="<?php echo (int) $fila['id']; ?>">
                                                    <input type="hidden" name="accion" value="rechazar">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" name="comentario" class="form-control" placeholder="Comentario (opcional)">
                                                        <button type="submit" class="btn btn-outline-danger">Rechazar</button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin acciones</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../Modulos/footer.php'; ?>

<script>
    $(document).ready(function () {
        $('#tablaSolicitudesSaldo').DataTable({
            language: {
                lengthMenu: 'Mostrar _MENU_ solicitudes',
                zeroRecords: 'No se encontraron solicitudes',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ solicitudes',
                infoEmpty: 'Sin solicitudes registradas',
                infoFiltered: '(filtrado de _MAX_ registros totales)',
                search: 'Buscar:',
                paginate: {
                    first: 'Primero',
                    last: 'Último',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            },
            order: [[0, 'desc']]
        });
    });
</script>
