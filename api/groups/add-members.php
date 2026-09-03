<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requirePost();

$data = requestData();
$userId = currentUserId();
$participantQuery = trim((string) ($data['participant_query'] ?? ''));
$groupId = filter_var($data['group_id'] ?? null, FILTER_VALIDATE_INT);
$participantIds = array_values(array_unique(array_filter(array_map(
    'intval',
    explode(',', (string) ($data['participant_ids'] ?? ''))
), fn(int $id): bool => $id > 0 && $id !== $userId)));

if (!$groupId || $participantIds === [] || count($participantIds) > 20) {
    respond(['ok' => false, 'message' => 'Selecciona al menos un participante válido.'], 422);
}
if ($participantQuery !== '') {
    respond(['ok' => false, 'message' => 'La persona escrita no existe o no fue seleccionada. Comprueba el resultado y pulsa Añadir.'], 422);
}

$db = databaseOrFail();
try {
    $db->beginTransaction();
    $access = $db->prepare(
        "SELECT g.name, gm.role FROM expense_groups g
         INNER JOIN group_members gm ON gm.group_id = g.id
           AND gm.user_id = :user_id AND gm.status = 'active'
         WHERE g.id = :group_id AND g.is_archived = 0 FOR UPDATE"
    );
    $access->execute(['user_id' => $userId, 'group_id' => $groupId]);
    $group = $access->fetch();
    if (!$group || !in_array($group['role'], ['owner', 'admin'], true)) {
        throw new DomainException('Solo el propietario o un administrador puede añadir personas.');
    }

    $placeholders = implode(',', array_fill(0, count($participantIds), '?'));
    $users = $db->prepare(
        "SELECT u.id, gm.status AS membership_status
         FROM users u
         LEFT JOIN group_members gm ON gm.group_id = ? AND gm.user_id = u.id
         WHERE u.is_active = 1 AND u.id IN ({$placeholders})"
    );
    $users->execute(array_merge([$groupId], $participantIds));
    $availableUsers = $users->fetchAll();
    $validIds = array_map('intval', array_column($availableUsers, 'id'));
    if (count($validIds) !== count($participantIds)) {
        throw new DomainException('Uno de los usuarios ya no existe o está desactivado.');
    }
    $newMemberIds = array_map(
        static fn(array $user): int => (int) $user['id'],
        array_filter($availableUsers, static fn(array $user): bool => !in_array($user['membership_status'], ['active', 'pending'], true))
    );
    if ($newMemberIds === []) {
        $db->rollBack();
        respond(['ok' => false, 'message' => 'Las personas seleccionadas ya pertenecen al grupo o tienen una invitación pendiente.'], 409);
    }

    $member = $db->prepare(
        "INSERT INTO group_members (group_id, user_id, role, status, joined_at)
         VALUES (:group_id, :user_id, 'member', 'pending', NULL)
         ON DUPLICATE KEY UPDATE status = 'pending', joined_at = NULL"
    );
    $notification = $db->prepare(
        "INSERT INTO notifications (user_id, type, title, message, action_url)
         VALUES (:user_id, 'group_invitation', 'Invitación a un grupo', :message, :action_url)"
    );
    foreach ($newMemberIds as $participantId) {
        $member->execute(['group_id' => $groupId, 'user_id' => $participantId]);
        $notification->execute([
            'user_id' => $participantId,
            'message' => 'Te han invitado a unirte a ' . $group['name'] . '.',
            'action_url' => '#group-invitation:' . $groupId,
        ]);
    }
    $db->prepare(
        "INSERT INTO activity_log (group_id, actor_id, event_type, entity_type, entity_id, message, metadata)
         VALUES (:group_id, :actor_id, 'members.added', 'group', :entity_id, :message, :metadata)"
    )->execute([
        'group_id' => $groupId, 'actor_id' => $userId, 'entity_id' => $groupId,
        'message' => 'Se enviaron invitaciones al grupo',
        'metadata' => json_encode(['user_ids' => $newMemberIds]),
    ]);
    $db->commit();
    respond(['ok' => true, 'message' => count($newMemberIds) === 1 ? 'Invitación enviada correctamente.' : 'Invitaciones enviadas correctamente.']);
} catch (DomainException $exception) {
    if ($db->inTransaction()) $db->rollBack();
    respond(['ok' => false, 'message' => $exception->getMessage()], 403);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Add group members] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudieron añadir los participantes.'], 500);
}
