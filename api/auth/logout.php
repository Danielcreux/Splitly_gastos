<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requirePost();

$bearer = bearerToken();
if ($bearer !== null) {
    $db = databaseOrFail();
    $db->prepare('DELETE FROM user_sessions WHERE id = :id AND user_agent = :user_agent')
        ->execute(['id' => hash('sha256', $bearer), 'user_agent' => SPLITLY_API_USER_AGENT]);
    respond(['ok' => true, 'message' => 'Sesión cerrada.']);
}

try {
    forgetPersistentLogin(Database::connection());
} catch (Throwable $exception) {
    error_log('[Logout persistent session] ' . $exception->getMessage());
    clearPersistentLoginCookie();
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
}
session_destroy();
respond(['ok' => true, 'message' => 'Sesión cerrada.', 'redirect' => 'login.php']);
