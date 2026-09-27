<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
requireMethod('GET');
$db = databaseOrFail();
$userId = (int) authenticatedUser($db)['id'];
$pagination = paginationParams();
$type = (string) ($_GET['type'] ?? '');
if (!in_array($type, ['', 'payment', 'expense', 'group'], true)) {
    $type = '';
}
$where = [
    "((a.group_id IS NOT NULL AND gm.user_id IS NOT NULL) OR (a.group_id IS NULL AND a.actor_id = :actor_user_id))",
];
$parameters = ['user_id' => $userId, 'actor_user_id' => $userId];
if ($type !== '') {
    $where[] = 'a.event_type LIKE :event_type';
    $parameters['event_type'] = $type . '%';
}
$search = trim((string) ($_GET['search'] ?? ''));
if ($search !== '') {
    $where[] = '(a.message LIKE :search OR g.name LIKE :search)';
    $parameters['search'] = '%' . mb_substr($search, 0, 100) . '%';
}
$from = "FROM activity_log a
         LEFT JOIN expense_groups g ON g.id = a.group_id
         LEFT JOIN group_members gm ON gm.group_id = a.group_id
           AND gm.user_id = :user_id AND gm.status = 'active'";
$whereSql = implode(' AND ', $where);
$count = $db->prepare("SELECT COUNT(*) $from WHERE $whereSql");
$count->execute($parameters);
$total = (int) $count->fetchColumn();
$statement = $db->prepare(
    "SELECT a.id,a.event_type,a.message AS title,COALESCE(g.name,'Splitly') AS meta,a.metadata,a.created_at
     $from WHERE $whereSql
     ORDER BY a.created_at DESC,a.id DESC LIMIT :per_page OFFSET :offset"
);
foreach ($parameters as $name => $value) {
    $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$statement->bindValue(':per_page', $pagination['per_page'], PDO::PARAM_INT);
$statement->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$statement->execute();
$items = array_map(static function (array $row): array {
    $metadata = json_decode((string) ($row['metadata'] ?? ''), true) ?: [];
    $eventType = str_contains($row['event_type'], 'payment') ? 'payment'
        : (str_contains($row['event_type'], 'group') ? 'group' : 'expense');
    return [
        'id' => (int) $row['id'],
        'type' => $eventType,
        'title' => $row['title'],
        'meta' => $row['meta'],
        'amount' => isset($metadata['amount']) ? (float) $metadata['amount'] : null,
        'createdAt' => (new DateTimeImmutable((string) $row['created_at']))->format(DateTimeInterface::ATOM),
    ];
}, $statement->fetchAll());
respondPaged($items, $total, $pagination);
