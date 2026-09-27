<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
requireMethod('GET');
$db = databaseOrFail();
$userId = (int) authenticatedUser($db)['id'];
$pagination = paginationParams();

$count = $db->prepare(
    "SELECT COUNT(*) FROM expense_groups g
     INNER JOIN group_members gm ON gm.group_id=g.id
     WHERE gm.user_id=:user_id AND gm.status='active' AND g.is_archived=0"
);
$count->execute(['user_id' => $userId]);
$total = (int) $count->fetchColumn();
$groupsStatement = $db->prepare(
    "SELECT g.id,g.name FROM expense_groups g
     INNER JOIN group_members gm ON gm.group_id=g.id
     WHERE gm.user_id=:user_id AND gm.status='active' AND g.is_archived=0
     ORDER BY g.name ASC,g.id ASC LIMIT :per_page OFFSET :offset"
);
$groupsStatement->bindValue(':user_id', $userId, PDO::PARAM_INT);
$groupsStatement->bindValue(':per_page', $pagination['per_page'], PDO::PARAM_INT);
$groupsStatement->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$groupsStatement->execute();
$groups = $groupsStatement->fetchAll();
$groupsById = [];
foreach ($groups as $group) {
    $id = (int) $group['id'];
    $groupsById[$id] = ['id' => $id, 'name' => $group['name'], 'members' => []];
}
if ($groupsById !== []) {
    $placeholders = implode(',', array_fill(0, count($groupsById), '?'));
    $membersStatement = $db->prepare(
        "SELECT gm.group_id,u.id,TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS name
         FROM group_members gm INNER JOIN users u ON u.id=gm.user_id AND u.is_active=1
         WHERE gm.group_id IN ($placeholders) AND gm.status='active'
         ORDER BY gm.group_id ASC,gm.joined_at ASC,u.id ASC"
    );
    $membersStatement->execute(array_keys($groupsById));
    foreach ($membersStatement->fetchAll() as $member) {
        $groupsById[(int) $member['group_id']]['members'][] = [
            'id' => (int) $member['id'], 'name' => $member['name'],
        ];
    }
}
$categoriesStatement = $db->query(
    'SELECT id,name,icon,color FROM categories WHERE is_active=1 ORDER BY name ASC,id ASC LIMIT 100'
);
$categories = array_map(static fn(array $category): array => [
    'id' => (int) $category['id'], 'name' => $category['name'],
    'icon' => $category['icon'], 'color' => $category['color'],
], $categoriesStatement->fetchAll());
respond([
    'ok' => true,
    'groups' => array_values($groupsById),
    'categories' => $categories,
    'pagination' => paginationMetadata($total, $pagination),
]);
