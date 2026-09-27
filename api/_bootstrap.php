<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/session.php';
startSecureSession();

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/LocalizedDecimal.php';

const SPLITLY_API_TOKEN_PREFIX = 'spl_api_';
const SPLITLY_API_TOKEN_DAYS = 30;
const SPLITLY_API_USER_AGENT = 'Splitly API Client';
const SPLITLY_DEFAULT_PER_PAGE = 20;
const SPLITLY_MAX_PER_PAGE = 100;

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function paginationParams(): array
{
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
    $perPage = filter_var($_GET['per_page'] ?? SPLITLY_DEFAULT_PER_PAGE, FILTER_VALIDATE_INT);
    $page = $page && $page > 0 ? $page : 1;
    $perPage = $perPage && $perPage > 0 ? min($perPage, SPLITLY_MAX_PER_PAGE) : SPLITLY_DEFAULT_PER_PAGE;
    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
    ];
}

function paginationMetadata(int $total, array $params): array
{
    $totalPages = $total === 0 ? 0 : (int) ceil($total / $params['per_page']);
    return [
        'page' => $params['page'],
        'per_page' => $params['per_page'],
        'total' => $total,
        'total_pages' => $totalPages,
        'has_next' => $params['page'] < $totalPages,
        'has_previous' => $params['page'] > 1 && $totalPages > 0,
    ];
}

function respondPaged(array $data, int $total, array $params): never
{
    respond(['data' => $data, 'pagination' => paginationMetadata($total, $params)]);
}

function allowedSort(array $allowed, string $defaultField, string $defaultOrder = 'desc'): array
{
    $requested = (string) ($_GET['sort'] ?? $defaultField);
    $field = array_key_exists($requested, $allowed) ? $allowed[$requested] : $allowed[$defaultField];
    $order = strtolower((string) ($_GET['order'] ?? $defaultOrder)) === 'asc' ? 'ASC' : 'DESC';
    return [$field, $order];
}

function requirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        respond(['ok' => false, 'message' => 'Método no permitido.'], 405);
    }

    if (bearerToken() !== null) {
        return;
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        respond(['ok' => false, 'message' => 'La sesión ha caducado. Recarga la página.'], 419);
    }
}

function requireAuthPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        respond(['ok' => false, 'message' => 'Método no permitido.'], 405);
    }
    if (isMobileClient()) {
        return;
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        respond(['ok' => false, 'message' => 'La sesión ha caducado. Recarga la página.'], 419);
    }
}

function requireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        header('Allow: ' . strtoupper($method));
        respond(['ok' => false, 'error' => 'method_not_allowed', 'message' => 'Método no permitido.'], 405);
    }
}

function isMobileClient(): bool
{
    return strcasecmp(trim((string) ($_SERVER['HTTP_X_SPLITLY_CLIENT'] ?? '')), 'mobile') === 0;
}

function bearerToken(): ?string
{
    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($authorization === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authorization = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }
    if (!preg_match('/^Bearer\s+(' . preg_quote(SPLITLY_API_TOKEN_PREFIX, '/') . '[A-Za-z0-9_-]{40,})$/', trim($authorization), $match)) {
        return null;
    }
    return $match[1];
}

function issueApiToken(PDO $db, int $userId): array
{
    $token = SPLITLY_API_TOKEN_PREFIX . rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
    $expiresAt = (new DateTimeImmutable('+' . SPLITLY_API_TOKEN_DAYS . ' days'))->format(DateTimeInterface::ATOM);
    $db->prepare('DELETE FROM user_sessions WHERE expires_at <= NOW()')->execute();
    $db->prepare(
        'INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at)
         VALUES (:id, :user_id, :ip_address, :user_agent, DATE_ADD(NOW(), INTERVAL 30 DAY))'
    )->execute([
        'id' => hash('sha256', $token),
        'user_id' => $userId,
        'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        'user_agent' => SPLITLY_API_USER_AGENT,
    ]);
    return ['access_token' => $token, 'expires_at' => $expiresAt];
}

function bearerUser(PDO $db): ?array
{
    $token = bearerToken();
    if ($token === null) {
        return null;
    }
    $hash = hash('sha256', $token);
    $statement = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_path
         FROM user_sessions s
         INNER JOIN users u ON u.id = s.user_id AND u.is_active = 1
         WHERE s.id = :id AND s.user_agent = :user_agent AND s.expires_at > NOW()
         LIMIT 1'
    );
    $statement->execute(['id' => $hash, 'user_agent' => SPLITLY_API_USER_AGENT]);
    $user = $statement->fetch();
    if (!$user) {
        respond(['ok' => false, 'error' => 'invalid_token', 'message' => 'La sesión ha caducado.'], 401);
    }
    $db->prepare('UPDATE user_sessions SET last_activity_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = :id')
        ->execute(['id' => $hash]);
    return $user;
}

function userPayload(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'name' => trim($user['first_name'] . ' ' . ($user['last_name'] ?? '')),
        'email' => $user['email'],
        'avatar_url' => ($user['avatar_path'] ?? null) ?: null,
    ];
}

function authenticatedUser(PDO $db): array
{
    $bearer = bearerUser($db);
    if ($bearer !== null) {
        return $bearer;
    }
    if (empty($_SESSION['user_id'])) {
        respond(['ok' => false, 'message' => 'Debes iniciar sesión.'], 401);
    }
    $statement = $db->prepare(
        'SELECT id, first_name, last_name, email, avatar_path
         FROM users WHERE id = :id AND is_active = 1 LIMIT 1'
    );
    $statement->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $statement->fetch();
    if (!$user) {
        respond(['ok' => false, 'message' => 'La sesión ha caducado.'], 401);
    }
    return $user;
}

function currentUserId(): int
{
    if (bearerToken() !== null) {
        return (int) bearerUser(databaseOrFail())['id'];
    }
    if (empty($_SESSION['user_id'])) {
        respond(['ok' => false, 'message' => 'Debes iniciar sesión.'], 401);
    }
    return (int) $_SESSION['user_id'];
}

function databaseOrFail(): PDO
{
    try {
        return Database::connection();
    } catch (Throwable) {
        respond(['ok' => false, 'message' => 'No se pudo conectar con MySQL. Comprueba la importación y la configuración.'], 503);
    }
}
