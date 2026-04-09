<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user'], $_SESSION['token'])) {
    header('Location: https://app.clinicacerene.com/login.php');
    exit;
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../Modulos/logger.php';

$connAuth = conectar();
if (!($connAuth instanceof mysqli)) {
    throw new RuntimeException('No se pudo conectar a la base de datos.');
}

$stmtToken = $connAuth->prepare('SELECT token FROM Usuarios WHERE user = ?');
if (!($stmtToken instanceof mysqli_stmt)) {
    throw new RuntimeException('No se pudo validar la sesion.');
}
$stmtToken->bind_param('s', $_SESSION['user']);
$stmtToken->execute();
$stmtToken->store_result();
$stmtToken->bind_result($dbToken);
$stmtToken->fetch();
$stmtToken->close();

if (!isset($dbToken) || $_SESSION['token'] !== $dbToken) {
    header('Location: https://app.clinicacerene.com/login.php');
    exit;
}

$rolUsuario = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;
$esAdminTickets = ($rolUsuario === 3);
$idUsuario = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;

$ticketId = isset($_GET['id']) ? (string) $_GET['id'] : '';
if ($ticketId === '' || !ctype_digit($ticketId)) {
    $_SESSION['tickets_flash'] = ['tipo' => 'danger', 'texto' => 'Ticket invalido.'];
    header('Location: /Tickets/index.php');
    exit;
}
$ticketIdInt = (int) $ticketId;

function validarEstadoTicket(?string $estado): ?string
{
    if ($estado === null) {
        return null;
    }
    $estado = strtolower(trim($estado));
    $permitidos = ['abierto', 'en_progreso', 'resuelto', 'cerrado'];
    return in_array($estado, $permitidos, true) ? $estado : null;
}

$stmtT = $connAuth->prepare("SELECT t.id,
                                 t.creado_por,
                                 t.problema_general,
                                 t.descripcion,
                                 t.area_problema,
                                 t.nino_id,
                                 t.estado,
                                 t.asignado_a,
                                 t.created_at,
                                 u.user AS creador_usuario,
                                 u.name AS creador_nombre,
                                 n.name AS nino_nombre,
                                 ua.user AS asignado_usuario,
                                 ua.name AS asignado_nombre
                          FROM soporte_tickets t
                          LEFT JOIN Usuarios u ON u.id = t.creado_por
                          LEFT JOIN nino n ON n.id = t.nino_id
                          LEFT JOIN Usuarios ua ON ua.id = t.asignado_a
                          WHERE t.id = ?");
if (!($stmtT instanceof mysqli_stmt)) {
    throw new RuntimeException('Error al preparar el ticket: ' . $connAuth->error);
}
$stmtT->bind_param('i', $ticketIdInt);
$stmtT->execute();
$ticket = $stmtT->get_result()->fetch_assoc();
$stmtT->close();

if (!$ticket) {
    $_SESSION['tickets_flash'] = ['tipo' => 'danger', 'texto' => 'Ticket no encontrado.'];
    header('Location: /Tickets/index.php');
    exit;
}

$creadoPor = (int) ($ticket['creado_por'] ?? 0);
if (!$esAdminTickets && $creadoPor !== $idUsuario) {
    $_SESSION['tickets_flash'] = ['tipo' => 'danger', 'texto' => 'No tienes acceso a este ticket.'];
    header('Location: /Tickets/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = trim((string) ($_POST['accion'] ?? ''));

    if ($accion === 'mensaje') {
        $mensaje = trim((string) ($_POST['mensaje'] ?? ''));
        $nuevoEstado = $esAdminTickets ? validarEstadoTicket((string) ($_POST['estado'] ?? '')) : null;
        $tomar = $esAdminTickets && isset($_POST['tomar_ticket']) && $_POST['tomar_ticket'] === '1';

        if ($mensaje === '' && $nuevoEstado === null && !$tomar) {
            $_SESSION['tickets_flash'] = ['tipo' => 'danger', 'texto' => 'No hay cambios para guardar.'];
            header('Location: /Tickets/ver.php?id=' . $ticketIdInt);
            exit;
        }

        $connAuth->begin_transaction();
        try {
            if ($mensaje !== '') {
                $stmtMsg = $connAuth->prepare('INSERT INTO soporte_ticket_mensajes (ticket_id, autor_id, mensaje) VALUES (?, ?, ?)');
                if (!($stmtMsg instanceof mysqli_stmt)) {
                    throw new RuntimeException('No se pudo preparar el mensaje.');
                }
                $stmtMsg->bind_param('iis', $ticketIdInt, $idUsuario, $mensaje);
                if (!$stmtMsg->execute()) {
                    throw new RuntimeException('No se pudo guardar el mensaje.');
                }
                $stmtMsg->close();
            }

            if ($tomar) {
                $stmtTake = $connAuth->prepare('UPDATE soporte_tickets SET asignado_a = ? WHERE id = ?');
                if ($stmtTake instanceof mysqli_stmt) {
                    $stmtTake->bind_param('ii', $idUsuario, $ticketIdInt);
                    $stmtTake->execute();
                    $stmtTake->close();
                }
            }

            if ($nuevoEstado !== null) {
                $stmtUp = $connAuth->prepare('UPDATE soporte_tickets SET estado = ? WHERE id = ?');
                if (!($stmtUp instanceof mysqli_stmt)) {
                    throw new RuntimeException('No se pudo preparar el cambio de estado.');
                }
                $stmtUp->bind_param('si', $nuevoEstado, $ticketIdInt);
                if (!$stmtUp->execute()) {
                    throw new RuntimeException('No se pudo actualizar el estado.');
                }
                $stmtUp->close();
            }

            registrarLog(
                $connAuth,
                $idUsuario,
                'tickets',
                'seguimiento',
                sprintf('Seguimiento en ticket #%d.', $ticketIdInt),
                'soporte_tickets',
                (string) $ticketIdInt
            );

            $connAuth->commit();
            $_SESSION['tickets_flash'] = ['tipo' => 'success', 'texto' => 'Cambios guardados.'];
        } catch (Throwable $e) {
            $connAuth->rollback();
            $_SESSION['tickets_flash'] = ['tipo' => 'danger', 'texto' => 'No se pudieron guardar los cambios.'];
        }

        header('Location: /Tickets/ver.php?id=' . $ticketIdInt);
        exit;
    }
}

$connAuth->close();

include '../Modulos/head.php';

$flash = $_SESSION['tickets_flash'] ?? null;
unset($_SESSION['tickets_flash']);

$adjuntos = [];
$stmtA = $conn->prepare('SELECT id, ruta, nombre_original, mime, tamano, created_at FROM soporte_ticket_adjuntos WHERE ticket_id = ? ORDER BY id ASC');
if ($stmtA instanceof mysqli_stmt) {
    $stmtA->bind_param('i', $ticketIdInt);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    while ($row = $resA->fetch_assoc()) {
        $adjuntos[] = $row;
    }
    $stmtA->close();
}

$mensajes = [];
$stmtM = $conn->prepare('SELECT m.id, m.mensaje, m.created_at, u.user, u.name FROM soporte_ticket_mensajes m LEFT JOIN Usuarios u ON u.id = m.autor_id WHERE m.ticket_id = ? ORDER BY m.id ASC');
if ($stmtM instanceof mysqli_stmt) {
    $stmtM->bind_param('i', $ticketIdInt);
    $stmtM->execute();
    $resM = $stmtM->get_result();
    while ($row = $resM->fetch_assoc()) {
        $mensajes[] = $row;
    }
    $stmtM->close();
}

$estadoActual = (string) ($ticket['estado'] ?? 'abierto');
$estadoActualNorm = validarEstadoTicket($estadoActual) ?? $estadoActual;

function estadoTexto(string $estado): string
{
    switch (strtolower(trim($estado))) {
        case 'abierto':
            return 'Abierto';
        case 'en_progreso':
            return 'En progreso';
        case 'resuelto':
            return 'Resuelto';
        case 'cerrado':
            return 'Cerrado';
        default:
            return $estado !== '' ? $estado : 'N/D';
    }
}

function estadoClase(string $estado): string
{
    switch (strtolower(trim($estado))) {
        case 'abierto':
            return 'bg-danger';
        case 'en_progreso':
            return 'bg-warning text-dark';
        case 'resuelto':
            return 'bg-success';
        case 'cerrado':
            return 'bg-secondary';
        default:
            return 'bg-secondary';
    }
}

$creador = trim((string) ($ticket['creador_nombre'] ?? ''));
if ($creador === '') {
    $creador = (string) ($ticket['creador_usuario'] ?? '');
}
$asignado = trim((string) ($ticket['asignado_nombre'] ?? ''));
if ($asignado === '') {
    $asignado = (string) ($ticket['asignado_usuario'] ?? '');
}
?>

<div class="container mt-4">
    <?php if (is_array($flash) && isset($flash['tipo'], $flash['texto'])): ?>
        <div class="alert alert-<?php echo htmlspecialchars((string) $flash['tipo'], ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars((string) $flash['texto'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-2 mb-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h2 class="h4 mb-0">Ticket #<?php echo (int) $ticket['id']; ?></h2>
            <span class="badge <?php echo htmlspecialchars(estadoClase($estadoActualNorm), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(estadoTexto($estadoActualNorm), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="d-flex gap-2">
            <a href="/Tickets/index.php" class="btn btn-outline-secondary">Volver</a>
            <?php if (!$esAdminTickets): ?>
                <a href="/Tickets/crear.php" class="btn btn-outline-primary">Nuevo ticket</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0">
                    <h3 class="h6 mb-0">Detalles</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Problema general</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars((string) ($ticket['problema_general'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt class="col-sm-5">Area</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars((string) ($ticket['area_problema'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt class="col-sm-5">Nino</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars((string) ($ticket['nino_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt class="col-sm-5">Creado por</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars($creador, ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt class="col-sm-5">Fecha</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars((string) ($ticket['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>

                        <?php if ($esAdminTickets): ?>
                            <dt class="col-sm-5">Asignado a</dt>
                            <dd class="col-sm-7"><?php echo htmlspecialchars($asignado, ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php endif; ?>
                    </dl>
                    <hr>
                    <h4 class="h6">Descripcion</h4>
                    <div class="border rounded p-3 bg-light" style="white-space: pre-wrap;">
                        <?php echo htmlspecialchars((string) ($ticket['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-white border-0">
                    <h3 class="h6 mb-0">Capturas</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($adjuntos)): ?>
                        <p class="text-muted mb-0">Sin capturas.</p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($adjuntos as $a): ?>
                                <?php $ruta = (string) ($a['ruta'] ?? ''); ?>
                                <div class="col-6 col-md-4">
                                    <a href="<?php echo htmlspecialchars($ruta, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="d-block">
                                        <img
                                            src="<?php echo htmlspecialchars($ruta, ENT_QUOTES, 'UTF-8'); ?>"
                                            class="img-fluid rounded border"
                                            alt="Captura"
                                            loading="lazy"
                                            style="aspect-ratio: 4 / 3; object-fit: cover;"
                                        >
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0">
                    <h3 class="h6 mb-0">Seguimiento</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($mensajes)): ?>
                        <p class="text-muted">Sin mensajes aun. El administrador dara seguimiento aqui.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($mensajes as $m): ?>
                                <?php
                                    $autor = trim((string) ($m['name'] ?? ''));
                                    if ($autor === '') {
                                        $autor = (string) ($m['user'] ?? '');
                                    }
                                ?>
                                <div class="border rounded p-3">
                                    <div class="d-flex justify-content-between gap-2 mb-2">
                                        <div class="fw-semibold"><?php echo htmlspecialchars($autor, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars((string) ($m['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div style="white-space: pre-wrap;">
                                        <?php echo htmlspecialchars((string) ($m['mensaje'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <hr>

                    <form method="post">
                        <input type="hidden" name="accion" value="mensaje">

                        <div class="mb-3">
                            <label for="mensaje" class="form-label">Agregar mensaje</label>
                            <textarea class="form-control" id="mensaje" name="mensaje" rows="3" placeholder="Escribe un comentario o actualizacion..."></textarea>
                        </div>

                        <?php if ($esAdminTickets): ?>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="estado" class="form-label">Cambiar estado</label>
                                    <select id="estado" name="estado" class="form-select">
                                        <option value="">Sin cambio</option>
                                        <option value="abierto" <?php echo ($estadoActualNorm === 'abierto') ? 'selected' : ''; ?>>Abierto</option>
                                        <option value="en_progreso" <?php echo ($estadoActualNorm === 'en_progreso') ? 'selected' : ''; ?>>En progreso</option>
                                        <option value="resuelto" <?php echo ($estadoActualNorm === 'resuelto') ? 'selected' : ''; ?>>Resuelto</option>
                                        <option value="cerrado" <?php echo ($estadoActualNorm === 'cerrado') ? 'selected' : ''; ?>>Cerrado</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" id="tomar_ticket" name="tomar_ticket">
                                        <label class="form-check-label" for="tomar_ticket">
                                            Tomar/asignarme el ticket
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">Guardar</button>
                            <a href="/Tickets/index.php" class="btn btn-outline-secondary">Volver</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../Modulos/footer.php'; ?>
