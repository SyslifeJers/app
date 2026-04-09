
<?php
			ini_set('error_reporting', E_ALL);
			ini_set('display_errors', 1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user']) || !isset($_SESSION['token'])) {
    // El usuario no está logueado, redirigir al formulario de login
    header("Location: https://app.clinicacerene.com/login.php");
    exit();
}

require_once __DIR__ . '/../conexion.php';
$conn = conectar();

// Verificar que el token en la sesión coincida con el token en la base de datos
$stmt = $conn->prepare("SELECT token FROM Usuarios WHERE user = ?");
$stmt->bind_param("s", $_SESSION['user']);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($db_token);
$stmt->fetch();

if ($_SESSION['token'] !== $db_token) {
    // El token no coincide, redirigir al formulario de login
    header("Location: https://app.clinicacerene.com/login.php");
    exit();
}
 $rol = isset($_SESSION['rol']) ? (int) $_SESSION['rol'] : 0;

 $ROL_PRACTICANTE = 6;
 $esPracticante = ($rol === $ROL_PRACTICANTE);

 if ($esPracticante) {
     $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
     $path = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '');
     $permiteCalendario = $path !== '' && substr($path, -strlen('/Citas/calendario.php')) === '/Citas/calendario.php';
     $permiteSalir = $path !== '' && substr($path, -strlen('/salir.php')) === '/salir.php';

     if (!$permiteCalendario && !$permiteSalir) {
         header('Location: /Citas/calendario.php');
         exit();
     }
 }

// Badge: citas de diagnostico para hoy.
$diagnosticoCitasHoy = 0;
try {
    $fechaHoy = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');
        if ($stmtDiag = $conn->prepare('SELECT COUNT(*) FROM Cita WHERE diagnostico_id IS NOT NULL AND DATE(Programado) = ? AND Estatus IN (2, 3)')) {
            $stmtDiag->bind_param('s', $fechaHoy);
            $stmtDiag->execute();
            $stmtDiag->bind_result($totalHoy);
            if ($stmtDiag->fetch()) {
                $diagnosticoCitasHoy = (int) $totalHoy;
            }
            $stmtDiag->close();
        }
    
} catch (Throwable $e) {
    $diagnosticoCitasHoy = 0;
}

// Badge: citas por reasignar (usuario Nueva entrevista).
$nuevaEntrevistaCitasPendientes = 0;
if ($rol == 3 || $rol == 5) {
    try {
        $usuarioNuevaEntrevistaId = 11;
        if ($stmtPend = $conn->prepare('SELECT COUNT(*) FROM Cita WHERE IdUsuario = ? AND DATE(Programado) >= CURDATE() AND Estatus IN (2, 3)')) {
            $stmtPend->bind_param('i', $usuarioNuevaEntrevistaId);
            $stmtPend->execute();
            $stmtPend->bind_result($totalPend);
            if ($stmtPend->fetch()) {
                $nuevaEntrevistaCitasPendientes = (int) $totalPend;
            }
            $stmtPend->close();
        }
    } catch (Throwable $e) {
        $nuevaEntrevistaCitasPendientes = 0;
    }
}
  ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Cerene App</title>
    <meta
      content="width=device-width, initial-scale=1.0, shrink-to-fit=no"
      name="viewport"
    />
    <link
      rel="icon"
      href="assets/img/kaiadmin/favicon.ico"
      type="image/x-icon"
    />

    <!-- Fonts and icons -->
    <script src="../assets/js/plugin/webfont/webfont.min.js"></script>
    <script>
      WebFont.load({
        google: { families: ["Public Sans:300,400,500,600,700"] },
        custom: {
          families: [
            "Font Awesome 5 Solid",
            "Font Awesome 5 Regular",
            "Font Awesome 5 Brands",
            "simple-line-icons",
          ],
          urls: ["../assets/css/fonts.min.css"],
        },
        active: function () {
          sessionStorage.fonts = true;
        },
      });
    </script>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../assets/css/plugins.min.css" />
    <link rel="stylesheet" href="../assets/css/kaiadmin.min.css" />

    <!-- CSS Just for demo purpose, don't include it in your project -->
     <link rel="stylesheet" href="../assets/css/demo.css" />
<link href="https://cdn.datatables.net/v/dt/dt-2.0.8/datatables.min.css" rel="stylesheet">

    <style>
      .nav-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        padding: 0 6px;
        border-radius: 999px;
        font-size: 11px;
        line-height: 1;
        font-weight: 700;
      }
    </style>

  </head>
  <body>
    <div class="wrapper">
      <!-- Sidebar -->
      <div class="sidebar" data-background-color="dark">
        <div class="sidebar-logo">
          <!-- Logo Header -->
          <div class="logo-header" data-background-color="dark">
            <a href="/index.php" class="logo">
              <img
                src="../logo.png"
                alt="navbar brand"
                class="navbar-brand"
                height="20"
              />
			  			
            </a>
            <div class="nav-toggle">
              <button class="btn btn-toggle toggle-sidebar">
                <i class="gg-menu-right"></i>
              </button>
              <button class="btn btn-toggle sidenav-toggler">
                <i class="gg-menu-left"></i>
              </button>
            </div>
            <button class="topbar-toggler more">
              <i class="gg-more-vertical-alt"></i>
            </button>
          </div>
          <!-- End Logo Header -->
        </div>
        <div class="sidebar-wrapper scrollbar scrollbar-inner">
          <div class="sidebar-content">
            <ul class="nav nav-secondary">
             <li class="nav-item active">			  
        <a class="nav-link" href="/index.php"> <i class="fas fa-home"></i>Inicio <span class="sr-only"></span></a>
      </li>
      <?php if (!$esPracticante) { ?>
                  <li class="nav-item ">
        <a class="nav-link" href="/Clientes/index.php"><i class="fas fa-users"></i>Clientes <span class="sr-only"></span></a>
      </li>
      <?php } ?>
       <?php if ($rol == 3 || $rol == 5) { ?>
            <li class="nav-item ">
         <a class="nav-link" href="/Usuarios/index.php"><i class="fas fa-user"></i>Usuarios <span class="sr-only"></span></a>
       </li>
       <?php } elseif ($rol == 1) { ?>
            <li class="nav-item ">
         <a class="nav-link" href="/Usuarios/index.php"><i class="fas fa-user"></i>Practicantes <span class="sr-only"></span></a>
       </li>
       <?php } ?>

      <?php if (!$esPracticante) { ?>
           <li class="nav-item ">
        <a class="nav-link" href="/Citas/index.php"><i class="fas fa-clipboard"></i>Corte de caja <span class="sr-only"></span></a>
      </li>
      <?php } ?>
      <li class="nav-item ">
        <a class="nav-link" href="/Citas/calendario.php"><i class="far fa-calendar-alt"></i>Calendario <span class="sr-only"></span></a>
      </li>

      <?php if (!$esPracticante) { ?>
        <li class="nav-item ">
          <a class="nav-link" href="/Tickets/index.php"><i class="fas fa-ticket-alt"></i><?php echo ($rol === 3) ? 'Tickets' : 'Soporte'; ?> <span class="sr-only"></span></a>
        </li>
      <?php } ?>

      <?php if (!$esPracticante) { ?>
             <li class="nav-item ">
        <a class="nav-link" href="/Diagnostico/index.php"><i class="fas fa-stethoscope"></i>Diagnostico<?php if ($diagnosticoCitasHoy > 0) { echo ' <span class="badge bg-danger nav-badge ms-2">' . (int) $diagnosticoCitasHoy . '</span>'; } ?> <span class="sr-only"></span></a>
      </li>
      <?php } ?>
      <?php if ($rol == 3 || $rol == 5) {?>
                    <li class="nav-item ">
         <a class="nav-link" href="/Citas/solicitudes.php"><i class="fas fa-history"></i>Solicitudes de reprogramación <span class="sr-only"></span></a>
       </li>
       <li class="nav-item ">
         <a class="nav-link" href="/Citas/solicitudes.php?tipo=cancelacion"><i class="fas fa-ban"></i>Solicitudes de cancelación <span class="sr-only"></span></a>
       </li>
       <li class="nav-item ">
         <a class="nav-link" href="/Citas/asignacion_nueva_entrevista.php"><i class="fas fa-user-check"></i>Asignación nueva entrevista<?php if ($nuevaEntrevistaCitasPendientes > 0) { echo ' <span class="badge bg-danger nav-badge ms-2">' . (int) $nuevaEntrevistaCitasPendientes . '</span>'; } ?> <span class="sr-only"></span></a>
       </li>
       <?php }

      if ($rol == 3 || $rol == 5) {?>
                   <li class="nav-item ">
        <a class="nav-link" href="/Clientes/solicitudes_saldo.php"><i class="fas fa-wallet"></i>Solicitudes de ajuste de saldo <span class="sr-only"></span></a>
      </li>
      <?php }

      if ($rol == 3 || $rol == 5) {?>
                   <li class="nav-item ">
        <a class="nav-link" href="/Reportes/index.php"><i class="fas fa-chart-pie"></i>Reportes <span class="sr-only"></span></a>
      </li>
      <li class="nav-item ">
        <a class="nav-link" href="/Configuracion/index.php"><i class="fas fa-hammer"></i>Configuración <span class="sr-only"></span></a>
      </li>
      <li class="nav-item ">
        <a class="nav-link" href="/Configuracion/paquetes.php"><i class="fas fa-box"></i>Paquetes <span class="sr-only"></span></a>
      </li>
      <li class="nav-item ">
        <a class="nav-link" href="/Logs/index.php"><i class="fas fa-clipboard-list"></i>Logs del sistema <span class="sr-only"></span></a>
      </li>
      <?php } ?>

	 
            </ul>
          </div>
        </div>
      </div>
      <!-- End Sidebar -->

      <div class="main-panel">
        <div class="main-header">
          <div class="main-header-logo">
            <!-- Logo Header -->
            <div class="logo-header" data-background-color="dark">
              <a href="index.html" class="logo">
                <img
                  src="../assets/img/kaiadmin/logo_light.svg"
                  alt="navbar brand"
                  class="navbar-brand"
                  height="20"
                />
              </a>
              <div class="nav-toggle">
                <button class="btn btn-toggle toggle-sidebar">
                  <i class="gg-menu-right"></i>
                </button>
                <button class="btn btn-toggle sidenav-toggler">
                  <i class="gg-menu-left"></i>
                </button>
              </div>
              <button class="topbar-toggler more">
                <i class="gg-more-vertical-alt"></i>
              </button>
            </div>
            <!-- End Logo Header -->
          </div>
          <!-- Navbar Header -->
          <nav
            class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom"
          >
            <div class="container-fluid">


              <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">

              
                <li class="nav-item topbar-user dropdown hidden-caret">
                  <a
                    class="dropdown-toggle profile-pic"
                    data-bs-toggle="dropdown"
                    href="#"
                    aria-expanded="false"
                  >

					<input hidden value="<?php echo $_SESSION['id']; ?>" id="idUsuario" name="idUsuario">
                    <span class="profile-username">
                      <span class="op-7">Hi,</span>
                      <span class="fw-bold"><?php echo $_SESSION['user']; ?></span>
                    </span>
                  </a>
                  <ul class="dropdown-menu dropdown-user animated fadeIn">
                    <div class="dropdown-user-scroll scrollbar-outer">
                      <li>
                        <div class="user-box">
                          <div class="avatar-lg">

                          </div>
                          <div class="u-text">
                            <h4><?php echo $_SESSION['user']; ?></h4>
                            
                          </div>
                        </div>
                      </li>
                      <li>
                        <a class="dropdown-item" href="/salir.php">Salir</a>
                      </li>
                    </div>
                  </ul>
                </li>
              </ul>
            </div>
          </nav>
          <!-- End Navbar -->
        </div>

        <div class="container">
          <div class="page-inner">
