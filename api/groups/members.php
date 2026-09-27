<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
requireMethod('GET');
$db = databaseOrFail();
$userId = (int) authenticatedUser($db)['id'];
$groupId = filter_var($_GET['group_id'] ?? null, FILTER_VALIDATE_INT);
if (!$groupId) {
    respond(['ok' => false, 'message' => 'El grupo no es válido.'], 422);
}
groupAccess($db, (int) $groupId, $userId);
$pagination = paginationParams();
$search = trim((string) ($_GET['search'] ?? ''));
$where = ["gm.group_id=:group_id", "gm.status IN ('active','pending')", 'u.is_active=1'];
$parameters = ['group_id' => (int) $groupId];
if ($search !== '') {
    $where[] = "(u.email LIKE :search OR TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) LIKE :search)";
    $parameters['search'] = '%' . mb_substr($search, 0, 100) . '%';
}
$whereSql = implode(' AND ', $where);
$count = $db->prepare("SELECT COUNT(*) FROM group_members gm INNER JOIN users u ON u.id=gm.user_id WHERE $whereSql");
$count->execute($parameters);
$total = (int) $count->fetchColumn();
$statement = $db->prepare(
    "SELECT u.id,TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS name,u.email,gm.role,gm.status
     FROM group_members gm INNER JOIN users u ON u.id=gm.user_id
     WHERE $whereSql
     ORDER BY (gm.status='pending') ASC,gm.joined_at ASC,u.id ASC
     LIMIT :per_page OFFSET :offset"
);
foreach ($parameters as $name => $value) {
    $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$statement->bindValue(':per_page', $pagination['per_page'], PDO::PARAM_INT);
$statement->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$statement->execute();
$members = array_map(static fn(array $member): array => [
    'id' => (int) $member['id'], 'name' => $member['name'], 'email' => $member['email'],
    'role' => $member['role'], 'status' => $member['status'],
], $statement->fetchAll());
respondPaged($members, $total, $pagination);
