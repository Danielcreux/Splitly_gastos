<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
$currentUser = currentUserId();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(['ok' => false, 'message' => 'Método no permitido.'], 405);
}

$now = time();
$window = $_SESSION['user_search_rate'] ?? ['count' => 0, 'started_at' => $now];
if ($now - $window['started_at'] >= 60) {
    $window = ['count' => 0, 'started_at' => $now];
}
if (++$window['count'] > 30) {
    $_SESSION['user_search_rate'] = $window;
    respond(['ok' => false, 'message' => 'Demasiadas búsquedas. Espera un minuto.'], 429);
}
$_SESSION['user_search_rate'] = $window;

$query = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($query) < 3 || mb_strlen($query) > 190) {
    respond(['ok' => true, 'exists' => false, 'users' => []]);
}

$db = databaseOrFail();
$groupId = filter_var($_GET['group_id'] ?? null, FILTER_VALIDATE_INT);
$parameters = ['current_user' => $currentUser];

if ($groupId) {
    $access = $db->prepare(
        "SELECT role FROM group_members
         WHERE group_id = :group_id AND user_id = :current_user AND status = 'active'
         LIMIT 1"
    );
    $access->execute(['group_id' => $groupId, 'current_user' => $currentUser]);
    $role = $access->fetchColumn();
    if (!in_array($role, ['owner', 'admin'], true)) {
        respond(['ok' => false, 'message' => 'No tienes permisos para añadir personas a este grupo.'], 403);
    }

}

if (str_contains($query, '@')) {
    // Solo se aceptan coincidencias exactas para impedir enumeraciones parciales.
    $statement = $db->prepare(
        "SELECT id, first_name, last_name, email
         FROM users
         WHERE is_active = 1 AND id <> :current_user AND email = :email
         LIMIT 1"
    );
    $parameters['email'] = mb_strtolower($query);
} else {
    $statement = $db->prepare(
        "SELECT id, first_name, last_name, email
         FROM users
         WHERE is_active = 1 AND id <> :current_user
           AND LOWER(TRIM(CONCAT(first_name, ' ', COALESCE(last_name, '')))) = LOWER(:full_name)
         ORDER BY first_name, last_name
         LIMIT 6"
    );
    $parameters['full_name'] = preg_replace('/\s+/u', ' ', $query);
}
$statement->execute($parameters);

$matchedUsers = $statement->fetchAll();
$availableUsers = $matchedUsers;
$membershipStatuses = [];
if ($groupId && $matchedUsers !== []) {
    $membership = $db->prepare(
        'SELECT status FROM group_members WHERE group_id = :group_id AND user_id = :user_id LIMIT 1'
    );
    $availableUsers = [];
    foreach ($matchedUsers as $matchedUser) {
        $membership->execute(['group_id' => $groupId, 'user_id' => $matchedUser['id']]);
        $status = $membership->fetchColumn() ?: null;
        if (in_array($status, ['active', 'pending'], true)) {
            $membershipStatuses[] = $status;
        } else {
            $availableUsers[] = $matchedUser;
        }
    }
}

if ($matchedUsers !== [] && $availableUsers === [] && in_array('active', $membershipStatuses, true)) {
    respond([
        'ok' => true, 'exists' => true, 'users' => [], 'already_member' => true,
        'message' => 'Ya tienes a esta persona agregada al grupo.',
    ]);
}
if ($matchedUsers !== [] && $availableUsers === [] && in_array('pending', $membershipStatuses, true)) {
    respond([
        'ok' => true, 'exists' => true, 'users' => [], 'pending_invitation' => true,
        'message' => 'Esta persona ya tiene una invitación pendiente.',
    ]);
}

$users = array_map(static fn(array $user): array => [
    'id' => (int) $user['id'],
    'name' => trim($user['first_name'] . ' ' . ($user['last_name'] ?? '')),
    'email' => $user['email'],
], $availableUsers);
respond(['ok' => true, 'exists' => $users !== [], 'users' => $users]);
