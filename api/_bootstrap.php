<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/session.php';
startSecureSession();

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/LocalizedDecimal.php';

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

function requirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        respond(['ok' => false, 'message' => 'Método no permitido.'], 405);
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        respond(['ok' => false, 'message' => 'La sesión ha caducado. Recarga la página.'], 419);
    }
}

function currentUserId(): int
{
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
