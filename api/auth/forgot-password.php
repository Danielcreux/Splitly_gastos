<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
$app = require __DIR__ . '/../../config/app.php';
if (!$app['password_reset_enabled']) {
    respond(['ok' => false, 'message' => 'La recuperación de contraseña está temporalmente deshabilitada.'], 404);
}
require_once __DIR__ . '/../../src/AuthService.php';
require_once __DIR__ . '/../../src/MailService.php';
requirePost();

$data = requestData();
$email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_VALIDATE_EMAIL);
if (!$email) {
    respond(['ok' => false, 'message' => 'Introduce un correo electrónico válido.'], 422);
}

try {
    $token = (new AuthService(databaseOrFail()))->createPasswordReset($email);
    $payload = ['ok' => true, 'message' => 'Si la cuenta existe, hemos enviado un enlace de recuperación.'];

    // En local mostramos el enlace para poder probar el flujo sin servidor SMTP.
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $hostname = explode(':', $host)[0];
    if ($token) {
        if ($app['url'] !== '') {
            $resetUrl = $app['url'] . '/reset-password.php?token=' . urlencode($token);
        } else {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $basePath = preg_replace('#/api/auth/forgot-password\.php$#', '', $_SERVER['SCRIPT_NAME'] ?? '');
            $resetUrl = "{$scheme}://{$host}{$basePath}/reset-password.php?token=" . urlencode($token);
        }
        (new MailService())->sendPasswordReset($email, $resetUrl);
        if (in_array($hostname, ['localhost', '127.0.0.1'], true)) {
            $payload['reset_url'] = $resetUrl;
        }
    }
    respond($payload);
} catch (Throwable $exception) {
    error_log('[Forgot password] ' . $exception->getMessage());
    // Respuesta deliberadamente genérica para no revelar cuentas existentes.
    respond(['ok' => true, 'message' => 'Si la cuenta existe, recibirás instrucciones de recuperación.']);
}
