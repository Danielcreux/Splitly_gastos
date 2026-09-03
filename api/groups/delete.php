<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requirePost();

$userId = currentUserId();
$groupId = filter_var(requestData()['group_id'] ?? null, FILTER_VALIDATE_INT);
if (!$groupId) {
    respond(['ok' => false, 'message' => 'El grupo no es válido.'], 422);
}

$db = databaseOrFail();
try {
    $db->beginTransaction();
    $statement = $db->prepare(
        'SELECT name, owner_id FROM expense_groups WHERE id = :group_id AND is_archived = 0 FOR UPDATE'
    );
    $statement->execute(['group_id' => $groupId]);
    $group = $statement->fetch();
    if (!$group) {
        $db->rollBack();
        respond(['ok' => false, 'message' => 'El grupo ya no existe.'], 404);
    }
    if ((int) $group['owner_id'] !== $userId) {
        $db->rollBack();
        respond(['ok' => false, 'message' => 'Solo el propietario puede eliminar este grupo.'], 403);
    }

    $db->prepare(
        "INSERT INTO notifications (user_id, type, title, message, action_url)
         SELECT gm.user_id, 'group_deleted', 'Grupo eliminado', :message, '#groups'
         FROM group_members gm
         WHERE gm.group_id = :group_id AND gm.user_id <> :owner_id
           AND gm.status IN ('active', 'pending')"
    )->execute([
        'message' => 'El grupo ' . $group['name'] . ' ha sido eliminado por su propietario.',
        'group_id' => $groupId,
        'owner_id' => $userId,
    ]);
    $db->prepare(
        "DELETE FROM notifications WHERE type = 'group_invitation' AND action_url = :action_url"
    )->execute(['action_url' => '#group-invitation:' . $groupId]);
    $db->prepare('DELETE FROM expense_groups WHERE id = :group_id AND owner_id = :owner_id')
        ->execute(['group_id' => $groupId, 'owner_id' => $userId]);
    $db->commit();

    respond(['ok' => true, 'message' => 'Grupo eliminado correctamente.']);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Delete group] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudo eliminar el grupo.'], 500);
}
