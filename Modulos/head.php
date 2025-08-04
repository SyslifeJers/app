
<?php
			ini_set('error_reporting', E_ALL);
			ini_set('display_errors', 1);
session_start();
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
                 <li class="nav-item ">
        <a class="nav-link" href="/Clientes/index.php"><i class="fas fa-users"></i>Clientes <span class="sr-only"></span></a>
      </li>
	   <li class="nav-item ">
        <a class="nav-link" href="/Usuarios/index.php"><i class="fas fa-user"></i>Psicologos <span class="sr-only"></span></a>
      </li>

	   <li class="nav-item ">
        <a class="nav-link" href="/Citas/index.php"><i class="fas fa-clipboard"></i>Citas <span class="sr-only"></span></a>
      </li>
      <?php $rol = isset($_SESSION['rol']) ? $_SESSION['rol'] : 0;
      
      if ($rol == 3) {?>
	  	   <li class="nav-item ">
        <a class="nav-link" href="/Reportes/index.php"><i class="fas fa-chart-pie"></i>Reportes <span class="sr-only"></span></a>
      </li>
      <li class="nav-item ">
        <a class="nav-link" href="/Configuracion/index.php"><i class="fas fa-hammer"></i>Configuración <span class="sr-only"></span></a>
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
