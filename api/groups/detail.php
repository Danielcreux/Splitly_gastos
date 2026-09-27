<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
requireMethod('GET');
$db = databaseOrFail();
$userId = (int) authenticatedUser($db)['id'];
$groupId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$groupId) respond(['ok' => false, 'error' => 'invalid_group', 'message' => 'El grupo no es válido.'], 422);
$group = groupAccess($db, (int) $groupId, $userId);
$expenseCount = $db->prepare("SELECT COUNT(*) FROM expenses WHERE group_id=:group_id AND status='active'");
$expenseCount->execute(['group_id' => $groupId]);
$summary = $db->prepare("SELECT member_count,total_spent FROM v_group_summaries WHERE group_id=:group_id");
$summary->execute(['group_id' => $groupId]);
$groupSummary = $summary->fetch() ?: ['member_count' => 0, 'total_spent' => 0];
respond(['ok' => true, 'group' => [
    'id' => (int) $group['id'], 'name' => $group['name'], 'description' => $group['description'] ?: null,
    'currency' => $group['currency'], 'role' => $group['user_role'],
    'memberCount' => (int) $groupSummary['member_count'], 'expenseCount' => (int) $expenseCount->fetchColumn(),
    'totalSpent' => round((float) $groupSummary['total_spent'], 2), 'members' => [], 'expenses' => [],
    'canManage' => in_array($group['user_role'], ['owner', 'admin'], true),
]]);
