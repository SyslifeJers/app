<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once 'conexion.php';
require_once 'Modulos/logger.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');

    if ($correo === '') {
        $message = 'Por favor, ingresa el correo electrónico asociado a tu cuenta.';
        $message_type = 'danger';
    } else {
        $conn = conectar();

        $stmt = $conn->prepare('SELECT id, user, correo FROM Usuarios WHERE correo = ? LIMIT 1');
        $stmt->bind_param('s', $correo);
        $stmt->execute();
        $result = $stmt->get_result();

        $usuario = $result->fetch_assoc();
        $stmt->close();

        $token = bin2hex(random_bytes(32));
        $expiracion = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

        if ($usuario) {
            $update = $conn->prepare('UPDATE Usuarios SET reset_token = ?, reset_token_expiration = ? WHERE id = ?');
            $update->bind_param('ssi', $token, $expiracion, $usuario['id']);
            $update->execute();
            $update->close();

            $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
            $rutaBase = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            if ($rutaBase === '' || $rutaBase === '.') {
                $rutaBase = '';
            }
            $enlace = sprintf(
                '%s://%s%s/reset_password.php?token=%s',
                $protocolo,
                $host,
                $rutaBase ? '/' . ltrim($rutaBase, '/') : '',
                urlencode($token)
            );

            $asunto = 'Recuperación de contraseña - Cerene App';
            $cuerpo = "Hola {$usuario['user']},\n\n" .
                "Recibimos una solicitud para restablecer la contraseña de tu cuenta. " .
                "Puedes crear una nueva contraseña haciendo clic en el siguiente enlace:\n\n" .
                $enlace . "\n\n" .
                "Si no solicitaste este cambio, ignora este correo. Tu contraseña no se modificará hasta que completes el proceso.\n\n" .
                "Este enlace expirará en 1 hora.\n\n" .
                "Saludos,\nEquipo Cerene";

            $fromDomain = $host;
            $headers = 'From: no-reply@' . $fromDomain . "\r\n" .
                "Reply-To: no-reply@{$fromDomain}\r\n" .
                "Content-Type: text/plain; charset=UTF-8\r\n";

            $envio = @mail($usuario['correo'], $asunto, $cuerpo, $headers);

            $message = 'Si el correo proporcionado está registrado, recibirás instrucciones para restablecer tu contraseña.';
            $message_type = $envio ? 'success' : 'warning';

            registrarLog(
                $conn,
                (int) $usuario['id'],
                'auth',
                'password_reset_request',
                sprintf('Solicitud de recuperación de contraseña enviada al correo %s.', $usuario['correo']),
                'Usuario',
                (string) $usuario['id']
            );
        } else {
            $message = 'Si el correo proporcionado está registrado, recibirás instrucciones para restablecer tu contraseña.';
            $message_type = 'info';

            registrarLog(
                $conn,
                null,
                'auth',
                'password_reset_request',
                sprintf('Solicitud de recuperación de contraseña para el correo %s no encontrado.', $correo),
                'Usuario',
                $correo
            );
        }

        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Cerene App - Recuperar contraseña</title>
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
                      <h3 class="text-center">Recuperar contraseña</h3>
                    </div>
                    <div class="card-body">
                      <p class="mb-4">
                        Ingresa el correo electrónico asociado a tu cuenta y te enviaremos un enlace para restablecer tu contraseña.
                      </p>
                      <?php if (!empty($message)) : ?>
                        <div class="alert alert-<?php echo htmlspecialchars($message_type ?: 'info'); ?>" role="alert">
                          <?php echo htmlspecialchars($message); ?>
                        </div>
                      <?php endif; ?>
                      <form action="forgot_password.php" method="post" novalidate>
                        <div class="mb-3">
                          <label for="correo" class="form-label">Correo electrónico</label>
                          <input type="email" id="correo" name="correo" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Enviar instrucciones</button>
                      </form>
                      <div class="mt-3 text-center">
                        <a href="login.php">Volver al inicio de sesión</a>
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
  </body>
</html>
