<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../src/AuthService.php';
requireAuthPost();

$data = requestData();
$firstName = trim((string) ($data['first_name'] ?? ''));
$lastName = trim((string) ($data['last_name'] ?? ''));
$email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$password = (string) ($data['password'] ?? '');
$confirmation = (string) ($data['password_confirmation'] ?? '');

if ($firstName === '' || mb_strlen($firstName) > 80 || mb_strlen($lastName) > 120) {
    respond(['ok' => false, 'message' => 'Revisa el nombre y los apellidos.'], 422);
}
if (!$email) {
    respond(['ok' => false, 'message' => 'Introduce un correo electrónico válido.'], 422);
}
if (strlen($password) < 10 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
    respond(['ok' => false, 'message' => 'La contraseña debe tener 10 caracteres, mayúscula, minúscula y número.'], 422);
}
if (!hash_equals($password, $confirmation)) {
    respond(['ok' => false, 'message' => 'Las contraseñas no coinciden.'], 422);
}

try {
    $user = (new AuthService(databaseOrFail()))->register($firstName, $lastName, $email, $password);
    if (isMobileClient()) {
        $issued = issueApiToken(databaseOrFail(), (int) $user['id']);
        respond([
            'ok' => true,
            'message' => 'Cuenta creada correctamente.',
            'access_token' => $issued['access_token'],
            'expires_at' => $issued['expires_at'],
            'user' => userPayload($user),
        ], 201);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['first_name'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    respond(['ok' => true, 'message' => 'Cuenta creada correctamente.', 'redirect' => 'index.php'], 201);
} catch (PDOException $exception) {
    if ((string) $exception->getCode() === '23000') {
        respond(['ok' => false, 'message' => 'Ya existe una cuenta con este correo.'], 409);
    }
    error_log('[Register] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudo crear la cuenta.'], 500);
}
