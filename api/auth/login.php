<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../src/AuthService.php';
requirePost();

$now = time();
$attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'started_at' => $now];
if ($now - $attempts['started_at'] > 300) {
    $attempts = ['count' => 0, 'started_at' => $now];
}
if ($attempts['count'] >= 5) {
    respond(['ok' => false, 'message' => 'Demasiados intentos. Espera cinco minutos.'], 429);
}

$data = requestData();
$email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$password = (string) ($data['password'] ?? '');
$remember = isset($data['remember']) && in_array((string) $data['remember'], ['1', 'true', 'on'], true);
if (!$email || $password === '') {
    respond(['ok' => false, 'message' => 'Introduce un correo y una contraseña válidos.'], 422);
}

try {
    $db = databaseOrFail();
    $user = (new AuthService($db))->authenticate($email, $password);
    if (!$user) {
        $attempts['count']++;
        $_SESSION['login_attempts'] = $attempts;
        respond(['ok' => false, 'message' => 'El correo o la contraseña no son correctos.'], 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['first_name'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    if ($remember) {
        issuePersistentLogin($db, (int) $user['id']);
    } else {
        forgetPersistentLogin($db);
    }
    unset($_SESSION['login_attempts']);
    respond(['ok' => true, 'message' => 'Sesión iniciada.', 'redirect' => 'index.php']);
} catch (Throwable $exception) {
    error_log('[Login] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudo iniciar sesión.'], 500);
}
