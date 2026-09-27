<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
shortcutRequireMethod('GET');
$db = shortcutDatabase();
$user = shortcutCurrentUser($db);
$userId = (int) $user['id'];

$groupsStatement = $db->prepare(
    "SELECT g.id, g.name FROM expense_groups g
     INNER JOIN group_members mine ON mine.group_id = g.id
       AND mine.user_id = :user_id AND mine.status = 'active'
     WHERE g.is_archived = 0 ORDER BY g.name,g.id LIMIT 100"
);
$groupsStatement->execute(['user_id' => $userId]);
$groups = $groupsStatement->fetchAll();
$requestedGroup = trim((string) ($_GET['group'] ?? ''));
$selectedGroup = null;
foreach ($groups as $candidate) {
    if ($requestedGroup !== '' && ((ctype_digit($requestedGroup) && (int) $candidate['id'] === (int) $requestedGroup)
        || mb_strtolower($candidate['name']) === mb_strtolower($requestedGroup))) {
        $selectedGroup = $candidate;
        break;
    }
}
if ($requestedGroup !== '' && $selectedGroup === null) {
    shortcutRespond(['ok' => false, 'error' => 'invalid_group', 'message' => 'Ese grupo no está disponible para este usuario.'], 422);
}
if ($selectedGroup === null && count($groups) === 1) $selectedGroup = $groups[0];

$groupsById = [];
foreach ($groups as $index => &$group) {
    $group['id'] = (int) $group['id'];
    $group['members'] = [];
    $groupsById[$group['id']] = $index;
}
unset($group);
if ($groupsById !== []) {
    $placeholders = implode(',', array_fill(0, count($groupsById), '?'));
    $membersStatement = $db->prepare(
        "SELECT gm.group_id,u.id,TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS name
         FROM group_members gm INNER JOIN users u ON u.id=gm.user_id
         WHERE gm.group_id IN ($placeholders) AND gm.status='active'
         ORDER BY gm.group_id,gm.joined_at,u.id"
    );
    $membersStatement->execute(array_keys($groupsById));
    foreach ($membersStatement->fetchAll() as $member) {
        $groups[$groupsById[(int) $member['group_id']]]['members'][] = [
            'id' => (int) $member['id'], 'name' => $member['name'],
        ];
    }
}

$categories = $db->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name,id LIMIT 100')->fetchAll();
$payers = [];
if ($selectedGroup !== null) {
    foreach ($groups as $group) {
        if ((int) $group['id'] !== (int) $selectedGroup['id']) continue;
        foreach ($group['members'] as $member) {
            $payers[] = (int) $member['id'] === $userId ? 'Yo' : $member['name'];
        }
        break;
    }
}
shortcutRespond([
    'ok' => true,
    'user' => ['id' => $userId, 'name' => trim($user['first_name'] . ' ' . ($user['last_name'] ?? ''))],
    'groups' => array_values(array_column($groups, 'name')),
    'categories' => array_values(array_column($categories, 'name')),
    'payers' => $payers,
    'selected_group' => $selectedGroup['name'] ?? null,
    'group_details' => $groups,
]);
