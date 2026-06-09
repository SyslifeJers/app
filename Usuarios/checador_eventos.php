<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
include '../Modulos/head.php';

if (!isset($_SESSION['id']) || !isset($_SESSION['token'])) {
    http_response_code(401);
    echo '<div class="container mt-5"><div class="alert alert-danger">No autenticado.</div></div>';
    include '../Modulos/footer.php';
    exit;
}

$usuarioId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($usuarioId <= 0) {
    http_response_code(400);
    echo '<div class="container mt-5"><div class="alert alert-warning">Usuario inválido.</div></div>';
    include '../Modulos/footer.php';
    exit;
}

$stmtUsuario = $conn->prepare('SELECT id, name, user, id_checador FROM Usuarios WHERE id = ? LIMIT 1');
$stmtUsuario->bind_param('i', $usuarioId);
$stmtUsuario->execute();
$usuario = $stmtUsuario->get_result()->fetch_assoc();
$stmtUsuario->close();

if (!$usuario) {
    http_response_code(404);
    echo '<div class="container mt-5"><div class="alert alert-warning">Usuario no encontrado.</div></div>';
    include '../Modulos/footer.php';
    exit;
}

$idChecador = trim((string) $usuario['id_checador']);
$eventos = array();
$resumenDias = array();
$hoy = date('Y-m-d');

if ($idChecador !== '') {
    $stmtEventos = $conn->prepare('SELECT serial_no, employee_no, nombre, fecha_hora, door_no, dispositivo, sucursal, fecha_sincronizacion, fecha_registro FROM checador_eventos WHERE employee_no = ? ORDER BY fecha_hora DESC, serial_no DESC LIMIT 1000');
    $stmtEventos->bind_param('s', $idChecador);
    $stmtEventos->execute();
    $resultadoEventos = $stmtEventos->get_result();
    while ($row = $resultadoEventos->fetch_assoc()) {
        $eventos[] = $row;
    }
    $stmtEventos->close();
}

foreach ($eventos as $evento) {
    $fechaHora = (string) $evento['fecha_hora'];
    $dia = substr($fechaHora, 0, 10);
    if ($dia === '') {
        continue;
    }

    if (!isset($resumenDias[$dia])) {
        $resumenDias[$dia] = array(
            'fecha' => $dia,
            'entrada' => $fechaHora,
            'salida' => $fechaHora,
            'eventos' => 0,
        );
    }

    if ($fechaHora < $resumenDias[$dia]['entrada']) {
        $resumenDias[$dia]['entrada'] = $fechaHora;
    }
    if ($fechaHora > $resumenDias[$dia]['salida']) {
        $resumenDias[$dia]['salida'] = $fechaHora;
    }

    $resumenDias[$dia]['eventos']++;
}

krsort($resumenDias);

$conn->close();

$nombreUsuario = htmlspecialchars((string) $usuario['name'], ENT_QUOTES, 'UTF-8');
$usuarioSistema = htmlspecialchars((string) $usuario['user'], ENT_QUOTES, 'UTF-8');
$idChecadorHtml = $idChecador !== '' ? htmlspecialchars($idChecador, ENT_QUOTES, 'UTF-8') : 'Sin relacionar';
?>

<div class="container mt-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1">Eventos del checador</h1>
            <div class="text-muted">
                <?php echo $nombreUsuario; ?>
                <span class="mx-1">|</span>
                Usuario: <?php echo $usuarioSistema; ?>
                <span class="mx-1">|</span>
                ID checador: <span class="fw-semibold"><?php echo $idChecadorHtml; ?></span>
            </div>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Volver a usuarios
        </a>
    </div>

    <?php if ($idChecador === '') { ?>
        <div class="alert alert-warning">Este usuario no tiene ID de checador relacionado.</div>
    <?php } else { ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
                <div>
                    <span class="fw-semibold">Resumen diario de asistencia</span>
                    <div class="text-muted small">Entrada = primera checada del día. Salida = última checada del día.</div>
                </div>
                <span class="badge bg-primary-subtle text-primary-emphasis"><?php echo count($resumenDias); ?> días</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaResumenChecador">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Checadas</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($resumenDias) === 0) { ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay eventos para resumir.</td>
                            </tr>
                        <?php } ?>

                        <?php foreach ($resumenDias as $diaResumen) { ?>
                            <?php
                            $fecha = (string) $diaResumen['fecha'];
                            $entrada = (string) $diaResumen['entrada'];
                            $salida = (string) $diaResumen['salida'];
                            $totalEventos = (int) $diaResumen['eventos'];
                            $salidaMostrar = $totalEventos > 1 ? substr($salida, 11, 8) : 'Sin salida';
                            $estadoClase = 'bg-success-subtle text-success-emphasis';
                            $estadoTexto = 'Completo';

                            if ($fecha === $hoy) {
                                $estadoClase = 'bg-info-subtle text-info-emphasis';
                                $estadoTexto = $totalEventos > 1 ? 'Día en curso' : 'Día en curso, salida pendiente';
                            } elseif ($totalEventos <= 1) {
                                $estadoClase = 'bg-warning-subtle text-warning-emphasis';
                                $estadoTexto = 'Faltó checar salida';
                            }
                            ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(substr($entrada, 11, 8), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($salidaMostrar, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $totalEventos, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="badge <?php echo $estadoClase; ?>"><?php echo htmlspecialchars($estadoTexto, ENT_QUOTES, 'UTF-8'); ?></span></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Últimos eventos registrados</span>
                <span class="badge bg-primary-subtle text-primary-emphasis"><?php echo count($eventos); ?> eventos</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaEventosChecador">
                    <thead class="table-light">
                        <tr>
                            <th>Serial</th>
                            <th>Empleado</th>
                            <th>Nombre checador</th>
                            <th>Fecha/hora</th>
                            <th>Puerta</th>
                            <th>Dispositivo</th>
                            <th>Sucursal</th>
                            <th>Sincronización</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($eventos) === 0) { ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No hay eventos para este ID de checador.</td>
                            </tr>
                        <?php } ?>

                        <?php foreach ($eventos as $evento) { ?>
                            <tr>
                                <td class="fw-semibold">#<?php echo htmlspecialchars((string) $evento['serial_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $evento['employee_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $evento['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $evento['fecha_hora'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $evento['door_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $evento['dispositivo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $evento['sucursal'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $evento['fecha_sincronizacion'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php } ?>
</div>

<?php include '../Modulos/footer.php'; ?>

<script>
    $(document).ready(function () {
        var $tablaResumen = $('#tablaResumenChecador');
        if ($tablaResumen.length && $tablaResumen.find('tbody tr').length > 1) {
            $tablaResumen.DataTable({
                language: {
                    lengthMenu: 'Número de filas _MENU_',
                    zeroRecords: 'No se encontraron días',
                    info: 'Página _PAGE_ de _PAGES_',
                    search: 'Buscar:',
                    paginate: {
                        first: 'Primero',
                        last: 'Último',
                        next: 'Siguiente',
                        previous: 'Previo'
                    },
                    infoEmpty: 'No hay registros disponibles',
                    infoFiltered: '(filtrado de _MAX_ días)'
                },
                order: [[0, 'desc']]
            });
        }

        var $tabla = $('#tablaEventosChecador');
        if ($tabla.length && $tabla.find('tbody tr').length > 1) {
            $tabla.DataTable({
                language: {
                    lengthMenu: 'Número de filas _MENU_',
                    zeroRecords: 'No se encontraron eventos',
                    info: 'Página _PAGE_ de _PAGES_',
                    search: 'Buscar:',
                    paginate: {
                        first: 'Primero',
                        last: 'Último',
                        next: 'Siguiente',
                        previous: 'Previo'
                    },
                    infoEmpty: 'No hay registros disponibles',
                    infoFiltered: '(filtrado de _MAX_ eventos)'
                },
                order: [[3, 'desc']]
            });
        }
    });
</script>
