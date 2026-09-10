<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requirePost();

$userId = currentUserId();
$db = databaseOrFail();
$data = requestData();
$action = (string) ($data['action'] ?? 'generate');

try {
    $db->beginTransaction();
    $db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id AND user_agent = 'Splitly iOS Shortcut'")
        ->execute(['user_id' => $userId]);

    if ($action === 'revoke') {
        $db->commit();
        respond(['ok' => true, 'message' => 'Token del Atajo revocado.']);
    }

    $token = 'spl_ios_' . rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
    $db->prepare(
        "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at)
         VALUES (:id, :user_id, :ip_address, 'Splitly iOS Shortcut', DATE_ADD(NOW(), INTERVAL 365 DAY))"
    )->execute([
        'id' => hash('sha256', $token),
        'user_id' => $userId,
        'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
    ]);
    $db->commit();

    respond([
        'ok' => true,
        'message' => 'Token creado. Cópialo ahora: solo se muestra una vez.',
        'token' => $token,
        'expires_in_days' => 365,
        'api_path' => '/api/shortcuts/expenses.php',
    ], 201);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Shortcut token] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudo crear el token del Atajo.'], 500);
}
