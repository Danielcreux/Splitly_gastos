<?php
declare(strict_types=1);

const SPLITLY_REMEMBER_COOKIE = 'SPLITLYREMEMBER';
const SPLITLY_REMEMBER_DAYS = 30;

function requestUsesHttps(array $app): bool
{
    $forwardedHttps = $app['trust_proxy'] && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedHttps;
}

function persistentCookieOptions(int $expires): array
{
    $app = require __DIR__ . '/app.php';
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => requestUsesHttps($app),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function clearPersistentLoginCookie(): void
{
    setcookie(SPLITLY_REMEMBER_COOKIE, '', persistentCookieOptions(time() - 3600));
    unset($_COOKIE[SPLITLY_REMEMBER_COOKIE]);
}

function forgetPersistentLogin(?PDO $db = null): void
{
    $token = (string) ($_COOKIE[SPLITLY_REMEMBER_COOKIE] ?? '');
    if ($db && preg_match('/^[a-f0-9]{64}$/', $token)) {
        $db->prepare('DELETE FROM user_sessions WHERE id = :id')
            ->execute(['id' => hash('sha256', $token)]);
    }
    clearPersistentLoginCookie();
    unset($_SESSION['remember_session_id']);
}

function issuePersistentLogin(PDO $db, int $userId): void
{
    forgetPersistentLogin($db);
    $plainToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $plainToken);
    $db->prepare('DELETE FROM user_sessions WHERE expires_at <= NOW()')->execute();
    $statement = $db->prepare(
        'INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at)
         VALUES (:id, :user_id, :ip_address, :user_agent, DATE_ADD(NOW(), INTERVAL 30 DAY))'
    );
    $statement->execute([
        'id' => $tokenHash,
        'user_id' => $userId,
        'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null,
    ]);
    setcookie(SPLITLY_REMEMBER_COOKIE, $plainToken, persistentCookieOptions(time() + SPLITLY_REMEMBER_DAYS * 86400));
    $_COOKIE[SPLITLY_REMEMBER_COOKIE] = $plainToken;
    $_SESSION['remember_session_id'] = $tokenHash;
}

function restorePersistentLogin(): void
{
    if (!empty($_SESSION['user_id'])) return;
    $plainToken = (string) ($_COOKIE[SPLITLY_REMEMBER_COOKIE] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $plainToken)) {
        if ($plainToken !== '') clearPersistentLoginCookie();
        return;
    }

    try {
        require_once __DIR__ . '/../src/Database.php';
        $db = Database::connection();
        $tokenHash = hash('sha256', $plainToken);
        $statement = $db->prepare(
            'SELECT u.id, u.first_name FROM user_sessions s
             INNER JOIN users u ON u.id = s.user_id AND u.is_active = 1
             WHERE s.id = :id AND s.expires_at > NOW() LIMIT 1'
        );
        $statement->execute(['id' => $tokenHash]);
        $user = $statement->fetch();
        if (!$user) {
            $db->prepare('DELETE FROM user_sessions WHERE id = :id')->execute(['id' => $tokenHash]);
            clearPersistentLoginCookie();
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['first_name'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['remember_session_id'] = $tokenHash;
        $db->prepare('UPDATE user_sessions SET last_activity_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = :id')
            ->execute(['id' => $tokenHash]);
        setcookie(SPLITLY_REMEMBER_COOKIE, $plainToken, persistentCookieOptions(time() + SPLITLY_REMEMBER_DAYS * 86400));
    } catch (Throwable $exception) {
        error_log('[Persistent session] ' . $exception->getMessage());
    }
}

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        restorePersistentLogin();
        return;
    }

    $app = require __DIR__ . '/app.php';
    $isHttps = requestUsesHttps($app);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_name('SPLITLYSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if ($app['production'] && $isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    session_start();
    restorePersistentLogin();
}
