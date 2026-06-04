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
<html lang="es">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Cerene App | Acceso</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="assets/img/kaiadmin/favicon.ico" type="image/x-icon" />

    <script src="assets/js/plugin/webfont/webfont.min.js"></script>
    <script>
      WebFont.load({
        google: { families: ["Public Sans:300,400,500,600,700,800"] },
        custom: {
          families: ["Font Awesome 5 Solid", "Font Awesome 5 Regular", "Font Awesome 5 Brands", "simple-line-icons"],
          urls: ["assets/css/fonts.min.css"],
        },
        active: function () { sessionStorage.fonts = true; },
      });
    </script>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/plugins.min.css" />
    <link rel="stylesheet" href="assets/css/kaiadmin.min.css" />
    <style>
      :root {
        --cerene-primary: #2456d6;
        --cerene-ink: #172033;
        --cerene-soft: #f4f7fb;
        --cerene-mint: #35c6a7;
      }

      body {
        min-height: 100vh;
        color: var(--cerene-ink);
        background:
          radial-gradient(circle at 12% 18%, rgba(53, 198, 167, 0.24), transparent 28%),
          radial-gradient(circle at 86% 12%, rgba(36, 86, 214, 0.18), transparent 30%),
          linear-gradient(135deg, #eef6ff 0%, #f8fbff 48%, #eefaf6 100%);
      }

      .login-shell {
        min-height: 100vh;
        display: flex;
        align-items: center;
        padding: 32px 16px;
        position: relative;
        overflow: hidden;
      }

      .login-shell::before,
      .login-shell::after {
        content: "";
        position: absolute;
        border-radius: 999px;
        filter: blur(2px);
        opacity: 0.55;
        pointer-events: none;
      }

      .login-shell::before {
        width: 240px;
        height: 240px;
        left: -80px;
        bottom: -90px;
        background: rgba(36, 86, 214, 0.14);
      }

      .login-shell::after {
        width: 180px;
        height: 180px;
        right: -50px;
        top: 70px;
        background: rgba(53, 198, 167, 0.18);
      }

      .login-card {
        width: 100%;
        max-width: 1080px;
        margin: 0 auto;
        border: 1px solid rgba(255, 255, 255, 0.75);
        border-radius: 34px;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.86);
        box-shadow: 0 28px 90px rgba(23, 32, 51, 0.16);
        backdrop-filter: blur(18px);
        position: relative;
        z-index: 1;
      }

      .brand-panel {
        min-height: 620px;
        padding: 48px;
        color: #fff;
        background:
          linear-gradient(145deg, rgba(20, 59, 156, 0.94), rgba(31, 128, 181, 0.86)),
          url("assets/img/undraw/undraw_sign_in_e6hj.svg") center bottom 34px / 74% auto no-repeat;
        position: relative;
      }

      .brand-panel::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(10, 31, 84, 0) 42%, rgba(10, 31, 84, 0.52) 100%);
      }

      .brand-content {
        position: relative;
        z-index: 1;
      }

      .brand-logo {
        width: 160px;
        max-width: 100%;
        padding: 10px 14px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.94);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
      }

      .brand-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 34px;
        padding: 8px 13px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.16);
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.02em;
      }

      .brand-title {
        max-width: 460px;
        margin-top: 22px;
        font-size: clamp(2.2rem, 4vw, 4rem);
        line-height: 0.96;
        font-weight: 800;
        letter-spacing: -0.05em;
      }

      .brand-copy {
        max-width: 430px;
        margin-top: 22px;
        color: rgba(255, 255, 255, 0.82);
        font-size: 1rem;
        line-height: 1.7;
      }

      .trust-row {
        position: absolute;
        left: 48px;
        right: 48px;
        bottom: 42px;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
      }

      .trust-item {
        padding: 14px;
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(10px);
      }

      .trust-item strong {
        display: block;
        font-size: 18px;
      }

      .trust-item span {
        display: block;
        color: rgba(255, 255, 255, 0.74);
        font-size: 12px;
        margin-top: 2px;
      }

      .form-panel {
        min-height: 620px;
        display: flex;
        align-items: center;
        padding: 52px;
        background: rgba(255, 255, 255, 0.72);
      }

      .form-box {
        width: 100%;
        max-width: 420px;
        margin: 0 auto;
      }

      .form-icon {
        width: 56px;
        height: 56px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 18px;
        color: var(--cerene-primary);
        background: rgba(36, 86, 214, 0.1);
        font-size: 22px;
        margin-bottom: 22px;
      }

      .form-title {
        font-size: 2rem;
        font-weight: 800;
        letter-spacing: -0.04em;
        margin-bottom: 8px;
      }

      .form-subtitle {
        color: #697386;
        margin-bottom: 30px;
      }

      .field-label {
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 8px;
      }

      .input-wrap {
        position: relative;
      }

      .input-wrap .field-icon {
        position: absolute;
        left: 17px;
        top: 50%;
        transform: translateY(-50%);
        color: #8b95a7;
        z-index: 2;
      }

      .login-input {
        height: 56px;
        border-radius: 17px;
        border: 1px solid #dfe6f1;
        background: #fff;
        padding-left: 48px;
        font-weight: 600;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
      }

      .login-input:focus {
        border-color: rgba(36, 86, 214, 0.65);
        box-shadow: 0 0 0 4px rgba(36, 86, 214, 0.12);
      }

      .toggle-password {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        border: 0;
        border-radius: 12px;
        background: transparent;
        color: #697386;
        width: 40px;
        height: 40px;
      }

      .toggle-password:hover {
        background: #f2f5fa;
      }

      .login-button {
        height: 56px;
        border: 0;
        border-radius: 17px;
        font-weight: 800;
        letter-spacing: 0.01em;
        background: linear-gradient(135deg, var(--cerene-primary), #1b9fca);
        box-shadow: 0 16px 30px rgba(36, 86, 214, 0.26);
      }

      .login-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 20px 36px rgba(36, 86, 214, 0.32);
      }

      .forgot-link {
        color: var(--cerene-primary);
        font-weight: 700;
        text-decoration: none;
      }

      .forgot-link:hover {
        text-decoration: underline;
      }

      .login-alert {
        border: 0;
        border-radius: 16px;
        font-weight: 600;
      }

      @media (max-width: 991.98px) {
        .brand-panel {
          min-height: auto;
          padding: 34px;
          background: linear-gradient(145deg, rgba(20, 59, 156, 0.96), rgba(31, 128, 181, 0.88));
        }

        .trust-row {
          position: static;
          margin-top: 28px;
        }

        .form-panel {
          min-height: auto;
          padding: 34px;
        }
      }

      @media (max-width: 575.98px) {
        .login-shell {
          padding: 14px;
          align-items: stretch;
        }

        .login-card {
          border-radius: 26px;
        }

        .brand-panel,
        .form-panel {
          padding: 26px 22px;
        }

        .brand-logo {
          width: 136px;
        }

        .trust-row {
          grid-template-columns: 1fr;
        }
      }
    </style>
  </head>
  <body>
    <main class="login-shell">
      <section class="login-card">
        <div class="row g-0">
          <div class="col-lg-6">
            <aside class="brand-panel h-100">
              <div class="brand-content">
                <img src="logo.png" alt="Cerene" class="brand-logo" />
                <div class="brand-kicker"><i class="fas fa-shield-alt"></i> Plataforma Cerene</div>
                <h1 class="brand-title">Agenda clínica clara y segura.</h1>
                <p class="brand-copy">Accede al sistema para gestionar citas, pacientes, pagos y seguimiento operativo desde un solo lugar.</p>
              </div>
              <div class="trust-row">
                <div class="trust-item"><strong>24/7</strong><span>Acceso operativo</span></div>
                <div class="trust-item"><strong>2h</strong><span>Sesión segura</span></div>
                <div class="trust-item"><strong>App</strong><span>Cerene Clinic</span></div>
              </div>
            </aside>
          </div>
          <div class="col-lg-6">
            <div class="form-panel h-100">
              <div class="form-box">
                <div class="form-icon"><i class="fas fa-lock"></i></div>
                <h2 class="form-title">Bienvenido</h2>
                <p class="form-subtitle">Ingresa tus credenciales para continuar.</p>

                <?php if (!empty($message)) : ?>
                  <div class="alert alert-<?php echo htmlspecialchars($message_type ?: 'info', ENT_QUOTES, 'UTF-8'); ?> login-alert" role="alert">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                  </div>
                <?php endif; ?>

                <form action="login.php" method="post" novalidate>
                  <div class="mb-4">
                    <label for="user" class="field-label">Usuario</label>
                    <div class="input-wrap">
                      <i class="fas fa-user field-icon"></i>
                      <input type="text" id="user" name="user" class="form-control login-input" placeholder="Tu usuario" autocomplete="username" required autofocus>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label for="pass" class="field-label">Contraseña</label>
                    <div class="input-wrap">
                      <i class="fas fa-key field-icon"></i>
                      <input type="password" id="pass" name="pass" class="form-control login-input pe-5" placeholder="Tu contraseña" autocomplete="current-password" required>
                      <button type="button" class="toggle-password" aria-label="Mostrar contraseña" onclick="togglePassword()">
                        <i class="fas fa-eye" id="passwordIcon"></i>
                      </button>
                    </div>
                  </div>
                  <div class="d-flex justify-content-end mb-4">
                    <a href="forgot_password.php" class="forgot-link">¿Olvidaste tu contraseña?</a>
                  </div>
                  <button type="submit" class="btn btn-primary login-button w-100">Entrar al sistema</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <script src="assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="assets/js/core/popper.min.js"></script>
    <script src="assets/js/core/bootstrap.min.js"></script>
    <script>
      function togglePassword() {
        var input = document.getElementById('pass');
        var icon = document.getElementById('passwordIcon');
        var visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        icon.className = visible ? 'fas fa-eye' : 'fas fa-eye-slash';
      }
    </script>
  </body>
</html>
