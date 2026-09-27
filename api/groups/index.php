<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
requireMethod('GET');
$db = databaseOrFail();
$userId = (int) authenticatedUser($db)['id'];
$pagination = paginationParams();
[$sortField, $sortOrder] = allowedSort([
    'updated_at' => 'g.updated_at',
    'created_at' => 'g.created_at',
    'name' => 'g.name',
], 'updated_at');
$where = ["gm.user_id = :user_id", "gm.status = 'active'", 'g.is_archived = 0'];
$parameters = ['user_id' => $userId];
$search = trim((string) ($_GET['search'] ?? ''));
if ($search !== '') {
    $where[] = '(g.name LIKE :search OR g.description LIKE :search)';
    $parameters['search'] = '%' . mb_substr($search, 0, 100) . '%';
}
$whereSql = implode(' AND ', $where);
$count = $db->prepare(
    "SELECT COUNT(*) FROM expense_groups g
     INNER JOIN group_members gm ON gm.group_id=g.id WHERE $whereSql"
);
$count->execute($parameters);
$total = (int) $count->fetchColumn();
$statement = $db->prepare(
    "SELECT g.id,g.name,g.description,g.icon,g.color,g.currency,g.budget,gm.role,
            s.member_count,s.total_spent,
            (SELECT COUNT(*) FROM expenses e WHERE e.group_id=g.id AND e.status='active') AS expense_count
     FROM expense_groups g
     INNER JOIN group_members gm ON gm.group_id=g.id
     INNER JOIN v_group_summaries s ON s.group_id=g.id
     WHERE $whereSql
     ORDER BY $sortField $sortOrder,g.id $sortOrder
     LIMIT :per_page OFFSET :offset"
);
foreach ($parameters as $name => $value) {
    $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$statement->bindValue(':per_page', $pagination['per_page'], PDO::PARAM_INT);
$statement->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$statement->execute();
$groups = array_map(static fn(array $group): array => [
    'id' => (int) $group['id'], 'name' => $group['name'],
    'description' => $group['description'] ?: null, 'icon' => $group['icon'],
    'color' => $group['color'], 'currency' => $group['currency'], 'role' => $group['role'],
    'budget' => (float) ($group['budget'] ?? 0),
    'progress' => (float) ($group['budget'] ?? 0) > 0 ? min(100, (int) round((float) $group['total_spent'] / (float) $group['budget'] * 100)) : 0,
    'memberCount' => (int) $group['member_count'], 'expenseCount' => (int) $group['expense_count'],
    'totalSpent' => round((float) $group['total_spent'], 2),
], $statement->fetchAll());
respondPaged($groups, $total, $pagination);
