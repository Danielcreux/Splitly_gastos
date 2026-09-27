<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

function groupAccess(PDO $db, int $groupId, int $userId): array
{
    $statement = $db->prepare(
        "SELECT g.*, gm.role AS user_role
         FROM expense_groups g
         INNER JOIN group_members gm ON gm.group_id = g.id
           AND gm.user_id = :user_id AND gm.status = 'active'
         WHERE g.id = :group_id AND g.is_archived = 0
         LIMIT 1"
    );
    $statement->execute(['group_id' => $groupId, 'user_id' => $userId]);
    $group = $statement->fetch();
    if (!$group) {
        respond(['ok' => false, 'error' => 'not_found', 'message' => 'El grupo no existe o no tienes acceso.'], 404);
    }
    return $group;
}
