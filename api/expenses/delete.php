<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requirePost();

$data = requestData();
$expenseId = filter_var($data['expense_id'] ?? null, FILTER_VALIDATE_INT);
$userId = currentUserId();
if (!$expenseId) {
    respond(['ok' => false, 'message' => 'Gasto no válido.'], 422);
}

$db = databaseOrFail();
try {
    $statement = $db->prepare(
        "SELECT e.id, e.group_id, e.description, e.created_by, gm.role
         FROM expenses e
         INNER JOIN group_members gm ON gm.group_id = e.group_id
           AND gm.user_id = :user_id AND gm.status = 'active'
         WHERE e.id = :expense_id AND e.status = 'active' LIMIT 1"
    );
    $statement->execute(['user_id' => $userId, 'expense_id' => $expenseId]);
    $expense = $statement->fetch();
    if (!$expense) {
        respond(['ok' => false, 'message' => 'El gasto no existe o no tienes acceso.'], 404);
    }
    if ((int) $expense['created_by'] !== $userId && !in_array($expense['role'], ['owner', 'admin'], true)) {
        respond(['ok' => false, 'message' => 'No tienes permisos para eliminar este gasto.'], 403);
    }

    $db->beginTransaction();
    $db->prepare("UPDATE expenses SET status = 'cancelled' WHERE id = :id")
        ->execute(['id' => $expenseId]);
    $db->prepare(
        "INSERT INTO activity_log (group_id, actor_id, event_type, entity_type, entity_id, message)
         VALUES (:group_id, :actor_id, 'expense.deleted', 'expense', :entity_id, :message)"
    )->execute([
        'group_id' => $expense['group_id'], 'actor_id' => $userId,
        'entity_id' => $expenseId, 'message' => "Se eliminó {$expense['description']}",
    ]);
    $db->commit();
    respond(['ok' => true, 'message' => 'Gasto eliminado correctamente.']);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Delete expense] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudo eliminar el gasto.'], 500);
}
