<?php
// login.php - LOGIN CON BASE DE DATOS + LOGO LOCAL
session_start();
require_once 'vendor/autoload.php';
$host = 'localhost'; $db = 'coleg115_solicitudes'; $user = 'coleg115_solicitudes'; $pass = 'm;uJW)n#=r[@';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    die("Error BD: " . $e->getMessage());
}
// CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Rate limiting
if (!isset($_SESSION['intentos'])) $_SESSION['intentos'] = 0;
if (!isset($_SESSION['bloqueo'])) $_SESSION['bloqueo'] = 0;
$MAX_INTENTOS = 5;
$error = '';
if ($_POST) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Token inválido.";
    } elseif ($_SESSION['bloqueo'] > time()) {
        $tiempo_restante = $_SESSION['bloqueo'] - time();
        $error = "Demasiados intentos. Espera " . $tiempo_restante . " segundos.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_user'] = $user['email'];
            $_SESSION['es_superadmin'] = $user['es_superadmin'];
            $_SESSION['area_usuario'] = $user['area'];
            $_SESSION['intentos'] = 0;
            $_SESSION['bloqueo'] = 0;
        
            // === REDIRECCIÓN INTELIGENTE DESDE CORREO ===
            if (isset($_SESSION['redirect_after_login'])) {
                $redirect = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                header("Location: $redirect");
                exit;
            }
        
            // Por defecto, ir al panel
            header('Location: /acceso-cm');
            exit;
        }
        else {
            $_SESSION['intentos']++;
            if ($_SESSION['intentos'] >= $MAX_INTENTOS) {
                $_SESSION['bloqueo'] = time() + 300;
                $error = "Cuenta bloqueada por 5 minutos por seguridad.";
            } else {
                $restantes = $MAX_INTENTOS - $_SESSION['intentos'];
                $error = "Usuario o contraseña incorrectos, te quedan <strong>$restantes intento" .
                        ($restantes == 1 ? '' : 's') .
                        " disponible" .
                        ($restantes == 1 ? '' : 's') .
                        "</strong>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login - Panel Administrativo</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><rect width='16' height='16' rx='4' ry='4' fill='%2310b981'/><path fill='%23ffffff' d='M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'/><path fill='%23ffffff' d='M13.5 8.5a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V9a.5.5 0 0 1 .5-.5h1zm-3-1a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V7.5zm-3 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V9.5z'/></svg>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><rect width='16' height='16' rx='4' ry='4' fill='%2310b981'/><path fill='%23ffffff' d='M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'/><path fill='%23ffffff' d='M13.5 8.5a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V9a.5.5 0 0 1 .5-.5h1zm-3-1a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V7.5zm-3 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V9.5z'/></svg>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --p:#10b981; --pd:#059669; --bg:#ecfdf5; --b:#d1fae5; --t:#1f2937; }
    body { background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
    .login-card { background: white; border: 1px solid var(--b); border-radius: 28px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); padding: 3rem 2.5rem; max-width: 460px; width: 100%; position: relative; overflow: hidden; }
    .login-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 8px; background: linear-gradient(135deg, var(--p), var(--pd)); }
    .logo-container { text-align: center; margin-bottom: 1.5rem; }
    .logo-container img { height: 82px; width: auto; }
    .btn-login { background: var(--p); border: none; border-radius: 16px; padding: 0.8rem; font-weight: 700; font-size: 1.05rem; transition: all 0.2s; }
    .btn-login:hover { background: var(--pd); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,185,129,0.3); }
    .password-toggle { cursor: pointer; color: #6b7280; }
    .password-toggle:hover { color: var(--p); }
  </style>
  <!-- BLOQUEO TOTAL DE INDEXACIÓN -->
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noodp, notranslate, noimageindex">
  <meta name="googlebot" content="noindex, nofollow, noarchive">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
</head>
<body>
  <div class="login-card">
    
    <!-- LOGO LOCAL + TÍTULO -->
    <div class="logo-container">
      <img src="assets/img/logo-colegio.png" alt="Colegio Mariano">
    </div>
    <div class="text-center mb-4">
      <h2 class="fw-bold" style="background: linear-gradient(135deg, var(--p), var(--pd)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
        Panel Administrativo
      </h2>
      <p class="text-muted">Solicitudes de Compra/Reembolso</p>
    </div>

    <!-- MENSAJE DE ERROR -->
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- FORMULARIO -->
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <div class="mb-3">
        <label class="form-label">Correo</label>
        <input type="email" class="form-control" name="email" required placeholder="micuenta@colegiomariano.cl" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Contraseña</label>
        <div class="input-group">
          <input type="password" class="form-control" name="password" id="password" required>
          <span class="input-group-text password-toggle" onclick="togglePassword()">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </span>
        </div>
      </div>
      <button type="submit" class="btn btn-login w-100 text-white">Iniciar Sesión</button>
    </form>
  </div>

  <script>
    function togglePassword() {
      const field = document.getElementById('password');
      const icon = document.getElementById('eyeIcon');
      if (field.type === 'password') {
        field.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
      } else {
        field.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
      }
    }
  </script>
</body>
</html>