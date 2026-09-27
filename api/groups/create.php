<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requirePost();

$data = requestData();
$name = trim((string) ($data['name'] ?? ''));
$description = trim((string) ($data['description'] ?? ''));
$participantQuery = trim((string) ($data['participant_query'] ?? ''));
$budget = parseLocalizedDecimal($data['budget'] ?? null);
$rawParticipantIds = $data['participant_ids'] ?? [];
if (!is_array($rawParticipantIds)) {
    $rawParticipantIds = explode(',', (string) $rawParticipantIds);
}
$participantIds = array_values(array_unique(array_filter(
    array_map('intval', $rawParticipantIds),
    fn(int $id): bool => $id > 0
)));
$userId = currentUserId();

if ($name === '' || mb_strlen($name) > 120) {
    respond(['ok' => false, 'message' => 'Indica un nombre de grupo válido.'], 422);
}
if ($budget === false || $budget < 0) {
    respond(['ok' => false, 'message' => 'El presupuesto no es válido.'], 422);
}
if (count($participantIds) > 20) {
    respond(['ok' => false, 'message' => 'Puedes añadir hasta 20 participantes a la vez.'], 422);
}
if ($participantQuery !== '') {
    respond(['ok' => false, 'message' => 'La persona escrita no existe o no fue seleccionada. Comprueba el resultado y pulsa Añadir.'], 422);
}

$db = databaseOrFail();
try {
    $db->beginTransaction();
    $statement = $db->prepare(
        "INSERT INTO expense_groups (owner_id, name, description, icon, budget)
         VALUES (:owner_id, :name, :description, 'users', :budget)"
    );
    $statement->execute([
        'owner_id' => $userId,
        'name' => $name,
        'description' => $description ?: null,
        'budget' => $budget,
    ]);
    $groupId = (int) $db->lastInsertId();

    $member = $db->prepare(
        "INSERT INTO group_members (group_id, user_id, role, status)
         VALUES (:group_id, :user_id, 'owner', 'active')"
    );
    $member->execute(['group_id' => $groupId, 'user_id' => $userId]);

    if ($participantIds !== []) {
        $placeholders = implode(',', array_fill(0, count($participantIds), '?'));
        $users = $db->prepare("SELECT id FROM users WHERE is_active = 1 AND id IN ({$placeholders})");
        $users->execute($participantIds);
        $validIds = array_map('intval', array_column($users->fetchAll(), 'id'));
        if (count($validIds) !== count($participantIds)) {
            throw new DomainException('Uno de los participantes ya no está disponible.');
        }
        $addMember = $db->prepare(
            "INSERT INTO group_members (group_id, user_id, role, status, joined_at)
             VALUES (:group_id, :user_id, 'member', 'pending', NULL)"
        );
        $notification = $db->prepare(
            "INSERT INTO notifications (user_id, type, title, message, action_url)
             VALUES (:user_id, 'group_invitation', 'Invitación a un grupo', :message, :action_url)"
        );
        foreach ($validIds as $participantId) {
            if ($participantId !== $userId) {
                $addMember->execute(['group_id' => $groupId, 'user_id' => $participantId]);
                $notification->execute([
                    'user_id' => $participantId,
                    'message' => 'Te han invitado a unirte a ' . $name . '.',
                    'action_url' => '#group-invitation:' . $groupId,
                ]);
            }
        }
    }

    $activity = $db->prepare(
        "INSERT INTO activity_log (group_id, actor_id, event_type, entity_type, entity_id, message)
         VALUES (:group_id, :actor_id, 'group.created', 'group', :entity_id, :message)"
    );
    $activity->execute([
        'group_id' => $groupId,
        'actor_id' => $userId,
        'entity_id' => $groupId,
        'message' => "Se creó el grupo {$name}",
    ]);
    $db->commit();
    respond(['ok' => true, 'message' => 'Grupo creado correctamente.', 'id' => $groupId], 201);
} catch (DomainException $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    respond(['ok' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[Create group] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudo crear el grupo.'], 500);
}
