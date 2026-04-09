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
$idUsuario = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;

if ($rolUsuario === 3) {
    $_SESSION['tickets_flash'] = ['tipo' => 'warning', 'texto' => 'Los administradores no dan de alta tickets.'];
    header('Location: /Tickets/index.php');
    exit;
}

$errores = [];

$problemaGeneral = '';
$descripcion = '';
$areaProblema = '';
$ninoId = '';

$opcionesProblema = [
    'Citas',
    'Clientes',
    'Cobranza',
    'Reportes',
    'Sistema',
    'Otro',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $problemaGeneral = trim((string) ($_POST['problema_general'] ?? ''));
    $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
    $areaProblema = trim((string) ($_POST['area_problema'] ?? ''));
    $ninoId = trim((string) ($_POST['nino_id'] ?? ''));

    $ninoIdInt = null;
    if ($ninoId !== '') {
        if (ctype_digit($ninoId)) {
            $ninoIdInt = (int) $ninoId;
        } else {
            $errores[] = 'Selecciona un nino valido.';
        }
    }

    if ($problemaGeneral === '') {
        $errores[] = 'Selecciona el problema general.';
    }
    if ($descripcion === '') {
        $errores[] = 'Ingresa una descripcion.';
    }
    if ($areaProblema === '' && $ninoIdInt === null) {
        $errores[] = 'Indica el area del problema o selecciona un nino.';
    }

    if (empty($errores)) {
        $stmt = $connAuth->prepare("INSERT INTO soporte_tickets (creado_por, problema_general, descripcion, area_problema, nino_id, estado) VALUES (?, ?, ?, ?, ?, 'abierto')");
        if (!($stmt instanceof mysqli_stmt)) {
            $errores[] = 'No se pudo preparar el alta del ticket.';
        } else {
            $stmt->bind_param('isssi', $idUsuario, $problemaGeneral, $descripcion, $areaProblema, $ninoIdInt);
            if (!$stmt->execute()) {
                $errores[] = 'No se pudo guardar el ticket. Intentalo de nuevo.';
            } else {
                $ticketId = (int) $connAuth->insert_id;
                $stmt->close();

                $erroresAdjuntos = [];
                $totalAdjuntos = 0;

                if (isset($_FILES['capturas']) && is_array($_FILES['capturas']['name'] ?? null)) {
                    $names = $_FILES['capturas']['name'];
                    $tmpNames = $_FILES['capturas']['tmp_name'];
                    $errorsFiles = $_FILES['capturas']['error'];
                    $sizes = $_FILES['capturas']['size'];

                    $maxBytes = 5 * 1024 * 1024;
                    $permitidos = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                    ];

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $baseDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'soporte_tickets' . DIRECTORY_SEPARATOR . $ticketId;
                    $baseUrl = '/uploads/soporte_tickets/' . $ticketId;

                    for ($i = 0; $i < count($names); $i++) {
                        if (($errorsFiles[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }
                        $totalAdjuntos++;
                        if ($totalAdjuntos > 6) {
                            $erroresAdjuntos[] = 'Solo se permiten hasta 6 capturas.';
                            break;
                        }
                        if (($errorsFiles[$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                            $erroresAdjuntos[] = 'Una de las capturas no se pudo subir.';
                            continue;
                        }
                        $tmp = (string) ($tmpNames[$i] ?? '');
                        $size = (int) ($sizes[$i] ?? 0);
                        if ($tmp === '' || !is_uploaded_file($tmp)) {
                            $erroresAdjuntos[] = 'Archivo temporal invalido.';
                            continue;
                        }
                        if ($size <= 0 || $size > $maxBytes) {
                            $erroresAdjuntos[] = 'Una captura excede el tamano permitido (5MB).';
                            continue;
                        }

                        $mime = (string) $finfo->file($tmp);
                        if (!isset($permitidos[$mime])) {
                            $erroresAdjuntos[] = 'Formato no permitido. Usa JPG, PNG o WEBP.';
                            continue;
                        }
                        $ext = $permitidos[$mime];

                        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
                            $erroresAdjuntos[] = 'No se pudo crear la carpeta de adjuntos.';
                            break;
                        }

                        $nombreSeguro = bin2hex(random_bytes(16)) . '.' . $ext;
                        $destino = $baseDir . DIRECTORY_SEPARATOR . $nombreSeguro;
                        if (!move_uploaded_file($tmp, $destino)) {
                            $erroresAdjuntos[] = 'No se pudo guardar una captura.';
                            continue;
                        }

                        $rutaPublica = $baseUrl . '/' . $nombreSeguro;
                        $original = (string) ($names[$i] ?? '');

                        $stmtAdj = $connAuth->prepare('INSERT INTO soporte_ticket_adjuntos (ticket_id, uploader_id, ruta, nombre_original, mime, tamano) VALUES (?, ?, ?, ?, ?, ?)');
                        if ($stmtAdj instanceof mysqli_stmt) {
                            $stmtAdj->bind_param('iisssi', $ticketId, $idUsuario, $rutaPublica, $original, $mime, $size);
                            $stmtAdj->execute();
                            $stmtAdj->close();
                        }
                    }
                }

                registrarLog(
                    $connAuth,
                    $idUsuario,
                    'tickets',
                    'crear',
                    sprintf('Se creo el ticket de soporte #%d (%s).', $ticketId, $problemaGeneral),
                    'soporte_tickets',
                    (string) $ticketId
                );

                $textoFlash = 'Ticket creado correctamente.';
                if (!empty($erroresAdjuntos)) {
                    $textoFlash .= ' (Algunas capturas no se pudieron guardar)';
                }
                $_SESSION['tickets_flash'] = ['tipo' => 'success', 'texto' => $textoFlash];

                header('Location: /Tickets/ver.php?id=' . $ticketId);
                exit;
            }
            $stmt->close();
        }
    }
}

$connAuth->close();

include '../Modulos/head.php';

$ninos = [];
if ($stmtN = $conn->prepare('SELECT id, name FROM nino WHERE activo = 1 ORDER BY name ASC')) {
    $stmtN->execute();
    $resN = $stmtN->get_result();
    while ($row = $resN->fetch_assoc()) {
        $ninos[] = $row;
    }
    $stmtN->close();
}
?>

<div class="container mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 py-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                <div>
                    <h2 class="h4 mb-1 d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-circle p-2">
                            <i class="fas fa-plus"></i>
                        </span>
                        Nuevo ticket
                    </h2>
                    <p class="text-muted mb-0 small">Describe el problema para que el administrador pueda dar seguimiento.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="/Tickets/index.php" class="btn btn-outline-secondary">Volver</a>
                </div>
            </div>
        </div>

        <div class="card-body">
            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars(implode(' ', $errores), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label for="problema_general" class="form-label">Problema general</label>
                        <select id="problema_general" name="problema_general" class="form-select" required>
                            <option value="">Selecciona...</option>
                            <?php foreach ($opcionesProblema as $op): ?>
                                <option value="<?php echo htmlspecialchars($op, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($problemaGeneral === $op) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($op, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label for="area_problema" class="form-label">Area del problema (opcional si eliges nino)</label>
                        <input type="text" class="form-control" id="area_problema" name="area_problema" value="<?php echo htmlspecialchars($areaProblema, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. Recepcion, Cobranza, Calendario">
                    </div>

                    <div class="col-12">
                        <label for="nino_id" class="form-label">Nino con el problema (opcional si indicas area)</label>
                        <select id="nino_id" name="nino_id" class="form-select">
                            <option value="">Sin nino</option>
                            <?php foreach ($ninos as $n): ?>
                                <?php $id = (string) ($n['id'] ?? ''); ?>
                                <option value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($ninoId !== '' && $ninoId === $id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) ($n['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="descripcion" class="form-label">Descripcion</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="5" required placeholder="Que paso, en que pantalla, y como reproducirlo."><?php echo htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="capturas" class="form-label">Capturas de imagen (hasta 6, JPG/PNG/WEBP, 5MB c/u)</label>
                        <input class="form-control" type="file" id="capturas" name="capturas[]" accept="image/jpeg,image/png,image/webp" multiple>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Crear ticket</button>
                        <a href="/Tickets/index.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../Modulos/footer.php'; ?>
