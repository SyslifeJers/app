<?php
//ver errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once  'Modulos/logger.php';

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['user'];
    $pass = $_POST['pass'];

    require_once 'conexion.php';
    $conn = conectar();

    // Verificar las credenciales del usuario
    $stmt = $conn->prepare("SELECT id, pass, IdRol FROM Usuarios WHERE user = ? and activo = 1");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hash, $idRol);
        $stmt->fetch();

        if (strcmp($pass, $hash) == 0) {
            // Generar un token
            $token = bin2hex(random_bytes(16));

            // Guardar el token en la base de datos
            $updateStmt = $conn->prepare("UPDATE Usuarios SET token = ? WHERE id = ?");
            $updateStmt->bind_param("si", $token, $id);
            $updateStmt->execute();

            // Configurar la duración de la sesión a 2 horas
            ini_set('session.gc_maxlifetime', 7200);
            session_set_cookie_params([
              'lifetime' => 7200,
              'path' => '/',
              'domain' => '', // Cambia esto si usas un dominio específico
              'secure' => isset($_SERVER['HTTPS']),
              'httponly' => true,
              'samesite' => 'Lax'
            ]);
            // Guardar el token en la sesión
            $_SESSION['id'] = $id;
            $_SESSION['user'] = $user;
            $_SESSION['token'] = $token;
            $_SESSION['rol'] = $idRol;

            registrarLog(
                $conn,
                $id,
                'auth',
                'login',
                sprintf('El usuario %s inició sesión correctamente.', $user),
                'Usuario',
                (string) $id
            );

            $message = "Login exitoso. Token generado: " . $token;
            header("Location: index.php");
            $message_type = "success";
        } else {
            $message = "Contraseña incorrecta.";
            $message_type = "danger";

            registrarLog(
                $conn,
                $id,
                'auth',
                'login_fallido',
                sprintf('Intento fallido de inicio de sesión: contraseña incorrecta para el usuario %s.', $user),
                'Usuario',
                (string) $id
            );
        }
    } else {
        $message = "Usuario no encontrado.";
        $message_type = "danger";

        registrarLog(
            $conn,
            null,
            'auth',
            'login_fallido',
            sprintf('Intento fallido de inicio de sesión: usuario %s no encontrado.', $user),
            'Usuario',
            $user
        );
    }

    $stmt->close();
    $conn->close();
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
  </head>
  <body>
    <div class="wrapper">
      <!-- Sidebar -->

      <div class="main-panel">

        <div class="container">
          <div class="page-inner">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card mt-5">
                <div class="card-header">
                    <h3 class="text-center">Login</h3>
                </div>
                <div class="card-body">
                    <form action="login.php" method="post" novalidate>
                        <div class="mb-3">
                            <label for="user" class="form-label">Username</label>
                            <input type="text" id="user" name="user" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="pass" class="form-label">Password</label>
                            <input type="password" id="pass" name="pass" class="form-control" required>
                        </div>
                        <?php if (!empty($message)) : ?>
                            <div class="alert alert-<?php echo htmlspecialchars($message_type ?: 'info'); ?>" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="forgot_password.php">¿Olvidaste tu contraseña?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
          </div>
        </div>


      </div>

    </div>
    <!--   Core JS Files   -->
    <script src="../assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>

    <!-- jQuery Scrollbar -->
    <script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>

    <!-- Chart JS -->
    <script src="../assets/js/plugin/chart.js/chart.min.js"></script>

    <!-- jQuery Sparkline -->
    <script src="../assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js"></script>

    <!-- Chart Circle -->
    <script src="../assets/js/plugin/chart-circle/circles.min.js"></script>

    <!-- Datatables -->
    <script src="../assets/js/plugin/datatables/datatables.min.js"></script>

    <!-- Bootstrap Notify -->
    <script src="../assets/js/plugin/bootstrap-notify/bootstrap-notify.min.js"></script>

    <!-- jQuery Vector Maps -->
    <script src="../assets/js/plugin/jsvectormap/jsvectormap.min.js"></script>
    <script src="../assets/js/plugin/jsvectormap/world.js"></script>

    <!-- Sweet Alert -->
    <script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>

    <!-- Kaiadmin JS -->
    <script src="../assets/js/kaiadmin.min.js"></script>

    <!-- Kaiadmin DEMO methods, don't include it in your project! -->
    <script>

      $("#lineChart").sparkline([102, 109, 120, 99, 110, 105, 115], {
        type: "line",
        height: "70",
        width: "100%",
        lineWidth: "2",
        lineColor: "#177dff",
        fillColor: "rgba(23, 125, 255, 0.14)",
      });

      $("#lineChart2").sparkline([99, 125, 122, 105, 110, 124, 115], {
        type: "line",
        height: "70",
        width: "100%",
        lineWidth: "2",
        lineColor: "#f3545d",
        fillColor: "rgba(243, 84, 93, .14)",
      });

      $("#lineChart3").sparkline([105, 103, 123, 100, 95, 105, 115], {
        type: "line",
        height: "70",
        width: "100%",
        lineWidth: "2",
        lineColor: "#ffa534",
        fillColor: "rgba(255, 165, 52, .14)",
      });
    </script>
  </body>
</html>
