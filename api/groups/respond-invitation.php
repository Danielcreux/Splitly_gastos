<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requirePost();

$data = requestData();
$userId = currentUserId();
$groupId = filter_var($data['group_id'] ?? null, FILTER_VALIDATE_INT);
$action = (string) ($data['action'] ?? '');

if (!$groupId || !in_array($action, ['accept', 'decline'], true)) {
    respond(['ok' => false, 'message' => 'La respuesta a la invitación no es válida.'], 422);
}

$db = databaseOrFail();
try {
    $db->beginTransaction();
    $invitation = $db->prepare(
        "SELECT g.name, g.owner_id
         FROM group_members gm
         INNER JOIN expense_groups g ON g.id = gm.group_id AND g.is_archived = 0
         WHERE gm.group_id = :group_id AND gm.user_id = :user_id
           AND gm.status = 'pending'
         FOR UPDATE"
    );
    $invitation->execute(['group_id' => $groupId, 'user_id' => $userId]);
    $group = $invitation->fetch();
    if (!$group) {
        $db->rollBack();
        respond(['ok' => false, 'message' => 'La invitación ya no está disponible.'], 409);
    }

    if ($action === 'accept') {
        $db->prepare(
            "UPDATE group_members SET status = 'active', joined_at = NOW()
             WHERE group_id = :group_id AND user_id = :user_id AND status = 'pending'"
        )->execute(['group_id' => $groupId, 'user_id' => $userId]);
        $eventType = 'members.joined';
        $activityMessage = 'Un participante aceptó la invitación';
        $responseMessage = 'Ya formas parte de ' . $group['name'] . '.';
        $ownerMessage = 'Una persona aceptó la invitación a ' . $group['name'] . '.';
    } else {
        $db->prepare(
            "UPDATE group_members SET status = 'left', joined_at = NULL
             WHERE group_id = :group_id AND user_id = :user_id AND status = 'pending'"
        )->execute(['group_id' => $groupId, 'user_id' => $userId]);
        $eventType = 'members.declined';
        $activityMessage = 'Un participante rechazó la invitación';
        $responseMessage = 'Has rechazado la invitación a ' . $group['name'] . '.';
        $ownerMessage = 'Una persona rechazó la invitación a ' . $group['name'] . '.';
    }

    $db->prepare(
        "UPDATE notifications
         SET read_at = COALESCE(read_at, NOW()), type = 'group_invitation_answered', action_url = '#groups'
         WHERE user_id = :user_id AND type = 'group_invitation'
           AND action_url = :action_url"
    )->execute(['user_id' => $userId, 'action_url' => '#group-invitation:' . $groupId]);

    $db->prepare(
        "INSERT INTO activity_log
           (group_id, actor_id, event_type, entity_type, entity_id, message)
         VALUES
           (:group_id, :actor_id, :event_type, 'group', :entity_id, :message)"
    )->execute([
        'group_id' => $groupId,
        'actor_id' => $userId,
        'event_type' => $eventType,
        'entity_id' => $groupId,
        'message' => $activityMessage,
    ]);
    $db->prepare(
        "INSERT INTO notifications (user_id, type, title, message, action_url)
         SELECT :notification_user, 'group_invitation_response', 'Respuesta a una invitación', :message, '#groups'
         FROM users u LEFT JOIN user_preferences p ON p.user_id = u.id
         WHERE u.id = :preference_user AND COALESCE(p.notify_group_updates, 1) = 1"
    )->execute([
        'notification_user' => $group['owner_id'],
        'preference_user' => $group['owner_id'],
        'message' => $ownerMessage,
    ]);

    $db->commit();
    respond(['ok' => true, 'message' => $responseMessage]);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Respond group invitation] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudo responder a la invitación.'], 500);
}
