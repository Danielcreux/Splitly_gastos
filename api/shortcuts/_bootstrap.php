<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/LocalizedDecimal.php';

function shortcutRespond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function shortcutRequestData(): array
{
    $raw = (string) file_get_contents('php://input');
    $data = json_decode($raw, true);
    if ($raw !== '' && !is_array($data)) {
        shortcutRespond(['ok' => false, 'error' => 'invalid_json', 'message' => 'El cuerpo debe ser JSON válido.'], 400);
    }
    return is_array($data) ? $data : [];
}

function shortcutRequireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        header('Allow: ' . $method);
        shortcutRespond(['ok' => false, 'error' => 'method_not_allowed', 'message' => 'Método no permitido.'], 405);
    }
}

function shortcutBearerToken(): string
{
    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($authorization === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authorization = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }
    if (!preg_match('/^Bearer\s+(spl_ios_[A-Za-z0-9_-]{40,})$/i', trim($authorization), $match)) {
        shortcutRespond(['ok' => false, 'error' => 'missing_token', 'message' => 'Token de Splitly ausente o no válido.'], 401);
    }
    return $match[1];
}

function shortcutCurrentUser(PDO $db): array
{
    $tokenHash = hash('sha256', shortcutBearerToken());
    $statement = $db->prepare(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.currency
         FROM user_sessions s
         INNER JOIN users u ON u.id = s.user_id AND u.is_active = 1
         WHERE s.id = :token_hash AND s.user_agent = 'Splitly iOS Shortcut'
           AND s.expires_at > NOW()
         LIMIT 1"
    );
    $statement->execute(['token_hash' => $tokenHash]);
    $user = $statement->fetch();
    if (!$user) {
        shortcutRespond(['ok' => false, 'error' => 'invalid_token', 'message' => 'El token ha caducado o fue revocado.'], 401);
    }
    $db->prepare('UPDATE user_sessions SET last_activity_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 365 DAY) WHERE id = :token_hash')
        ->execute(['token_hash' => $tokenHash]);
    return $user;
}

function shortcutDatabase(): PDO
{
    try {
        return Database::connection();
    } catch (Throwable $exception) {
        error_log('[iOS Shortcut API] ' . $exception->getMessage());
        shortcutRespond(['ok' => false, 'error' => 'database_unavailable', 'message' => 'Splitly no está disponible temporalmente.'], 503);
    }
}

function shortcutBoolean(mixed $value, bool $default = false): bool
{
    if ($value === null) return $default;
    if (is_bool($value)) return $value;
    return in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'yes', 'sí', 'si', 'on'], true);
}
