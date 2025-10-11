<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once 'conexion.php';
require_once 'Modulos/logger.php';

$message = '';
$message_type = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$mostrarFormulario = false;

if ($token === '') {
    $message = 'El enlace de recuperación no es válido.';
    $message_type = 'danger';
} else {
    $conn = conectar();

    $stmt = $conn->prepare('SELECT id, reset_token_expiration FROM Usuarios WHERE reset_token = ? LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();

    if (!$usuario) {
        $message = 'El enlace de recuperación no es válido o ya fue utilizado.';
        $message_type = 'danger';
    } else {
        $expiracion = $usuario['reset_token_expiration'];
        $expirada = $expiracion && (new DateTime($expiracion)) < new DateTime();

        if ($expirada) {
            $message = 'El enlace de recuperación ha expirado. Solicita uno nuevo.';
            $message_type = 'danger';
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = trim($_POST['password'] ?? '');
                $confirmacion = trim($_POST['password_confirmation'] ?? '');

                if ($password === '' || $confirmacion === '') {
                    $message = 'Por favor, completa todos los campos.';
                    $message_type = 'danger';
                    $mostrarFormulario = true;
                } elseif ($password !== $confirmacion) {
                    $message = 'Las contraseñas no coinciden.';
                    $message_type = 'danger';
                    $mostrarFormulario = true;
                } elseif (mb_strlen($password) < 8) {
                    $message = 'La contraseña debe tener al menos 8 caracteres.';
                    $message_type = 'danger';
                    $mostrarFormulario = true;
                } else {
                    $update = $conn->prepare('UPDATE Usuarios SET pass = ?, reset_token = NULL, reset_token_expiration = NULL WHERE id = ?');
                    $update->bind_param('si', $password, $usuario['id']);
                    $update->execute();
                    $update->close();

                    registrarLog(
                        $conn,
                        (int) $usuario['id'],
                        'auth',
                        'password_reset_success',
                        'La contraseña fue restablecida correctamente mediante enlace de recuperación.',
                        'Usuario',
                        (string) $usuario['id']
                    );

                    $message = 'Tu contraseña ha sido restablecida correctamente. Ahora puedes iniciar sesión con tu nueva contraseña.';
                    $message_type = 'success';
                }
            } else {
                $mostrarFormulario = true;
            }
        }
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Cerene App - Restablecer contraseña</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="assets/img/kaiadmin/favicon.ico" type="image/x-icon" />

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
      <div class="main-panel">
        <div class="container">
          <div class="page-inner">
            <div class="container">
              <div class="row justify-content-center">
                <div class="col-md-6">
                  <div class="card mt-5">
                    <div class="card-header">
                      <h3 class="text-center">Restablecer contraseña</h3>
                    </div>
                    <div class="card-body">
                      <?php if (!empty($message)) : ?>
                        <div class="alert alert-<?php echo htmlspecialchars($message_type ?: 'info'); ?>" role="alert">
                          <?php echo htmlspecialchars($message); ?>
                        </div>
                      <?php endif; ?>
                      <?php if ($mostrarFormulario) : ?>
                        <form action="reset_password.php" method="post" novalidate>
                          <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                          <div class="mb-3">
                            <label for="password" class="form-label">Nueva contraseña</label>
                            <input type="password" id="password" name="password" class="form-control" required minlength="8">
                          </div>
                          <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required minlength="8">
                          </div>
                          <button type="submit" class="btn btn-primary w-100">Actualizar contraseña</button>
                        </form>
                      <?php else : ?>
                        <div class="text-center">
                          <a href="login.php" class="btn btn-secondary mt-3">Volver al inicio de sesión</a>
                        </div>
                      <?php endif; ?>
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
  </body>
</html>
