<?php
include '../Modulos/head.php';

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
$esAdminTickets = ($rolUsuario === 3);
$idUsuario = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;

$flash = $_SESSION['tickets_flash'] ?? null;
unset($_SESSION['tickets_flash']);

$tickets = [];
$sql = "SELECT t.id,
               t.estado,
               t.problema_general,
               t.area_problema,
               t.created_at,
               u.user AS creador_usuario,
               u.name AS creador_nombre,
               n.name AS nino_nombre
        FROM soporte_tickets t
        LEFT JOIN Usuarios u ON u.id = t.creado_por
        LEFT JOIN nino n ON n.id = t.nino_id";

if (!$esAdminTickets) {
    $sql .= "\n        WHERE t.creado_por = ?";
}

$sql .= "\n        ORDER BY t.id DESC";

$stmt = $conn->prepare($sql);
if (!($stmt instanceof mysqli_stmt)) {
    throw new RuntimeException('Error al preparar la consulta de tickets: ' . $conn->error);
}
if (!$esAdminTickets) {
    $stmt->bind_param('i', $idUsuario);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
}
$stmt->close();

function badgeEstadoTicket(string $estado): array
{
    $estadoNorm = strtolower(trim($estado));
    switch ($estadoNorm) {
        case 'abierto':
            return ['Abierto', 'bg-danger'];
        case 'en_progreso':
            return ['En progreso', 'bg-warning text-dark'];
        case 'resuelto':
            return ['Resuelto', 'bg-success'];
        case 'cerrado':
            return ['Cerrado', 'bg-secondary'];
        default:
            return [($estado !== '' ? $estado : 'N/D'), 'bg-secondary'];
    }
}
?>

<div class="container mt-4">
    <?php if (is_array($flash) && isset($flash['tipo'], $flash['texto'])): ?>
        <div class="alert alert-<?php echo htmlspecialchars((string) $flash['tipo'], ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars((string) $flash['texto'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 py-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                <div>
                    <h2 class="h4 mb-1 d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-circle p-2">
                            <i class="fas fa-ticket-alt"></i>
                        </span>
                        Tickets de soporte
                    </h2>
                    <p class="text-muted mb-0 small">
                        <?php echo $esAdminTickets ? 'Seguimiento y control de tickets.' : 'Consulta y seguimiento de tus tickets.'; ?>
                    </p>
                </div>
                <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2">
                    <?php if (!$esAdminTickets): ?>
                        <a href="/Tickets/crear.php" class="btn btn-primary d-flex align-items-center justify-content-center gap-2">
                            <i class="fas fa-plus"></i>
                            <span>Nuevo ticket</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="ticketsTable">
                    <thead>
                        <tr>
                            <th style="width: 90px;">ID</th>
                            <th style="width: 140px;">Estado</th>
                            <th>Problema</th>
                            <th>Area</th>
                            <th>Nino</th>
                            <?php if ($esAdminTickets): ?>
                                <th>Creado por</th>
                            <?php endif; ?>
                            <th style="width: 170px;">Fecha</th>
                            <th style="width: 120px;">Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): ?>
                            <?php
                                [$textoEstado, $claseEstado] = badgeEstadoTicket((string) ($t['estado'] ?? ''));
                                $creador = trim((string) ($t['creador_nombre'] ?? ''));
                                if ($creador === '') {
                                    $creador = (string) ($t['creador_usuario'] ?? '');
                                }
                            ?>
                            <tr>
                                <td><?php echo (int) ($t['id'] ?? 0); ?></td>
                                <td><span class="badge <?php echo htmlspecialchars($claseEstado, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($textoEstado, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?php echo htmlspecialchars((string) ($t['problema_general'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($t['area_problema'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($t['nino_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php if ($esAdminTickets): ?>
                                    <td><?php echo htmlspecialchars($creador, ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars((string) ($t['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <a class="btn btn-outline-primary btn-sm" href="/Tickets/ver.php?id=<?php echo (int) ($t['id'] ?? 0); ?>">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../Modulos/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && jQuery.fn && typeof jQuery.fn.DataTable === 'function') {
            jQuery('#ticketsTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                }
            });
        }
    });
</script>
