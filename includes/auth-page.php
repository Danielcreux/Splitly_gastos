<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
startSecureSession();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$authMode = $authMode ?? 'login';
$config = [
    'login' => ['Iniciar sesión', 'Bienvenido de nuevo a Splitly'],
    'register' => ['Crear una cuenta', 'Empieza a compartir gastos fácilmente'],
    'forgot' => ['Recuperar contraseña', 'Te ayudaremos a volver a tu cuenta'],
    'reset' => ['Nueva contraseña', 'Elige una contraseña segura para tu cuenta'],
][$authMode] ?? ['Iniciar sesión', 'Bienvenido de nuevo a Splitly'];
$resetToken = $authMode === 'reset' ? (string) ($_GET['token'] ?? '') : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#075a45">
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($config[0]) ?> · Splitly</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-mode-<?= htmlspecialchars($authMode, ENT_QUOTES, 'UTF-8') ?>">
  <main class="auth-shell">
    <section class="auth-showcase">
      <a class="auth-brand" href="login.php"><i>S</i><span>Splitly</span></a>
      <div class="showcase-copy">
        <span class="eyebrow">GASTOS COMPARTIDOS, SIN COMPLICACIONES</span>
        <h1>Las cuentas claras.<br><em>Los amigos, también.</em></h1>
        <p>Organiza grupos, divide gastos y salda cuentas desde un único lugar.</p>
        <div class="showcase-card"><div class="showcase-icon">✓</div><div><strong>Balances actualizados</strong><span>Controla gastos y pagos desde un solo lugar.</span></div></div>
      </div>
      <p class="auth-quote">“Por fin sabemos quién pagó qué sin revisar veinte mensajes.”</p>
    </section>

    <section class="auth-panel">
      <div class="auth-box">
        <div class="mobile-brand"><i>S</i><span>Splitly</span></div>
        <div class="auth-card">
        <header><h2><?= htmlspecialchars($config[0]) ?></h2><p><?= htmlspecialchars($config[1]) ?></p></header>

        <div class="auth-alert" id="authAlert" role="alert"></div>

        <?php if ($authMode === 'login'): ?>
          <form class="auth-form" data-auth-form data-endpoint="api/auth/login.php">
            <label><span>Correo electrónico</span><input type="email" name="email" autocomplete="email" required placeholder="tu@email.com"></label>
            <label><span>Contraseña</span><div class="password-field"><input type="password" name="password" autocomplete="current-password" required placeholder="Tu contraseña"><button type="button" data-toggle-password aria-label="Mostrar contraseña">◉</button></div></label>
            <label class="remember"><input type="checkbox" name="remember" value="1"><span>Mantener la sesión iniciada</span></label>
            <button class="auth-submit" type="submit">Iniciar sesión <span>→</span></button>
          </form>
          <p class="auth-switch">¿Todavía no tienes cuenta? <a href="register.php">Crear cuenta</a></p>
        <?php elseif ($authMode === 'register'): ?>
          <form class="auth-form" data-auth-form data-endpoint="api/auth/register.php">
            <div class="auth-row"><label><span>Nombre</span><input name="first_name" required maxlength="80" autocomplete="given-name" placeholder="Tu nombre"></label><label><span>Apellidos</span><input name="last_name" maxlength="120" autocomplete="family-name" placeholder="Tus apellidos"></label></div>
            <label><span>Correo electrónico</span><input type="email" name="email" autocomplete="email" required placeholder="tu@email.com"></label>
            <label><span>Contraseña</span><div class="password-field"><input type="password" name="password" autocomplete="new-password" minlength="10" required placeholder="Mínimo 10 caracteres"><button type="button" data-toggle-password>◉</button></div><small>Incluye mayúscula, minúscula y número.</small></label>
            <label><span>Repetir contraseña</span><div class="password-field"><input type="password" name="password_confirmation" autocomplete="new-password" minlength="10" required placeholder="Repite tu contraseña"><button type="button" data-toggle-password>◉</button></div></label>
            <label class="remember"><input type="checkbox" required><span>Acepto los términos y la política de privacidad</span></label>
            <button class="auth-submit" type="submit">Crear mi cuenta <span>→</span></button>
          </form>
          <p class="auth-switch">¿Ya tienes una cuenta? <a href="login.php">Iniciar sesión</a></p>
        <?php elseif ($authMode === 'forgot'): ?>
          <div class="recovery-icon">✉</div>
          <form class="auth-form" data-auth-form data-endpoint="api/auth/forgot-password.php">
            <label><span>Correo electrónico</span><input type="email" name="email" autocomplete="email" required placeholder="tu@email.com"></label>
            <button class="auth-submit" type="submit">Enviar enlace <span>→</span></button>
          </form>
          <div id="resetLinkBox" class="reset-link-box"></div>
          <p class="auth-switch"><a href="login.php">← Volver a iniciar sesión</a></p>
        <?php else: ?>
          <div class="recovery-icon">◆</div>
          <?php if (!preg_match('/^[a-f0-9]{64}$/', $resetToken)): ?>
            <div class="invalid-token"><strong>Enlace no válido</strong><p>Solicita un nuevo enlace de recuperación.</p><a href="forgot-password.php">Solicitar otro enlace</a></div>
          <?php else: ?>
            <form class="auth-form" data-auth-form data-endpoint="api/auth/reset-password.php">
              <input type="hidden" name="token" value="<?= htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8') ?>">
              <label><span>Nueva contraseña</span><div class="password-field"><input type="password" name="password" autocomplete="new-password" minlength="10" required placeholder="Mínimo 10 caracteres"><button type="button" data-toggle-password>◉</button></div><small>Incluye mayúscula, minúscula y número.</small></label>
              <label><span>Repetir contraseña</span><div class="password-field"><input type="password" name="password_confirmation" autocomplete="new-password" minlength="10" required placeholder="Repite tu contraseña"><button type="button" data-toggle-password>◉</button></div></label>
              <button class="auth-submit" type="submit">Actualizar contraseña <span>→</span></button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
        </div>
      </div>
      <footer>© <?= date('Y') ?> Splitly · Privacidad · Términos</footer>
    </section>
  </main>
  <script src="assets/js/auth.js"></script>
</body>
</html>
