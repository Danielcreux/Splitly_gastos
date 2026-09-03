<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
$app = require __DIR__ . '/../../config/app.php';
if (!$app['password_reset_enabled']) {
    respond(['ok' => false, 'message' => 'La recuperación de contraseña está temporalmente deshabilitada.'], 404);
}
require_once __DIR__ . '/../../src/AuthService.php';
requirePost();

$data = requestData();
$token = trim((string) ($data['token'] ?? ''));
$password = (string) ($data['password'] ?? '');
$confirmation = (string) ($data['password_confirmation'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    respond(['ok' => false, 'message' => 'El enlace de recuperación no es válido.'], 422);
}
if (strlen($password) < 10 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
    respond(['ok' => false, 'message' => 'La contraseña debe tener 10 caracteres, mayúscula, minúscula y número.'], 422);
}
if (!hash_equals($password, $confirmation)) {
    respond(['ok' => false, 'message' => 'Las contraseñas no coinciden.'], 422);
}

try {
    $updated = (new AuthService(databaseOrFail()))->resetPassword($token, $password);
    if (!$updated) {
        respond(['ok' => false, 'message' => 'El enlace ha caducado o ya fue utilizado.'], 422);
    }
    respond(['ok' => true, 'message' => 'Contraseña actualizada.', 'redirect' => 'login.php']);
} catch (Throwable $exception) {
    error_log('[Reset password] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudo actualizar la contraseña.'], 500);
}
