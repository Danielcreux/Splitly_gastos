<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requirePost();

$data = requestData();
$userId = currentUserId();
$expenseId = filter_var($data['expense_id'] ?? null, FILTER_VALIDATE_INT);
$groupId = filter_var($data['group_id'] ?? null, FILTER_VALIDATE_INT);
$categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
$payerId = filter_var($data['payer_id'] ?? null, FILTER_VALIDATE_INT);
$amount = parseLocalizedDecimal($data['amount'] ?? null);
$description = trim((string) ($data['description'] ?? ''));
$date = (string) ($data['date'] ?? '');
$splitExpense = isset($data['split_expense']) && in_array((string) $data['split_expense'], ['1', 'true', 'on'], true);
$validDate = DateTimeImmutable::createFromFormat('Y-m-d', $date);

if (!$expenseId || !$groupId || !$categoryId || !$payerId || $amount === false || $amount <= 0 || $description === '' || mb_strlen($description) > 180 || !$validDate || $validDate->format('Y-m-d') !== $date) {
    respond(['ok' => false, 'message' => 'Revisa los datos del gasto.'], 422);
}

$db = databaseOrFail();
try {
    $db->beginTransaction();
    $access = $db->prepare(
        "SELECT e.created_by, gm.role FROM expenses e
         INNER JOIN group_members gm ON gm.group_id = e.group_id
           AND gm.user_id = :user_id AND gm.status = 'active'
         WHERE e.id = :expense_id AND e.status = 'active' FOR UPDATE"
    );
    $access->execute(['user_id' => $userId, 'expense_id' => $expenseId]);
    $existing = $access->fetch();
    if (!$existing || ((int) $existing['created_by'] !== $userId && !in_array($existing['role'], ['owner', 'admin'], true))) {
        throw new DomainException('No tienes permisos para editar este gasto.');
    }

    $members = $db->prepare("SELECT user_id FROM group_members WHERE group_id = :group_id AND status = 'active' ORDER BY joined_at, user_id");
    $members->execute(['group_id' => $groupId]);
    $memberIds = array_map('intval', array_column($members->fetchAll(), 'user_id'));
    if (!in_array($userId, $memberIds, true) || !in_array((int) $payerId, $memberIds, true)) {
        throw new DomainException('El usuario o pagador no pertenece al grupo.');
    }

    $db->prepare(
        "UPDATE expenses SET group_id = :group_id, category_id = :category_id,
           paid_by = :paid_by, description = :description, amount = :amount, split_method = :split_method,
           expense_date = :expense_date, updated_at = NOW()
         WHERE id = :expense_id"
    )->execute([
        'group_id' => $groupId, 'category_id' => $categoryId, 'paid_by' => $payerId,
        'description' => $description, 'amount' => number_format((float) $amount, 2, '.', ''),
        'expense_date' => $date, 'split_method' => $splitExpense ? 'equal' : 'exact', 'expense_id' => $expenseId,
    ]);

    $db->prepare('DELETE FROM expense_splits WHERE expense_id = :expense_id')->execute(['expense_id' => $expenseId]);
    $totalCents = (int) round((float) $amount * 100);
    $splitMemberIds = $splitExpense ? $memberIds : [(int) $payerId];
    $baseCents = intdiv($totalCents, count($splitMemberIds));
    $remainder = $totalCents % count($splitMemberIds);
    $split = $db->prepare('INSERT INTO expense_splits (expense_id, user_id, amount_owed, is_settled) VALUES (:expense_id, :user_id, :amount_owed, :is_settled)');
    foreach ($splitMemberIds as $index => $memberId) {
        $cents = $baseCents + ($index < $remainder ? 1 : 0);
        $split->execute([
            'expense_id' => $expenseId, 'user_id' => $memberId,
            'amount_owed' => number_format($cents / 100, 2, '.', ''),
            'is_settled' => $memberId === (int) $payerId ? 1 : 0,
        ]);
    }
    $db->prepare(
        "INSERT INTO activity_log (group_id, actor_id, event_type, entity_type, entity_id, message)
         VALUES (:group_id, :actor_id, 'expense.updated', 'expense', :entity_id, :message)"
    )->execute(['group_id' => $groupId, 'actor_id' => $userId, 'entity_id' => $expenseId, 'message' => "Se actualizó {$description}"]);
    $db->commit();
    respond(['ok' => true, 'message' => 'Gasto actualizado correctamente.']);
} catch (DomainException $exception) {
    if ($db->inTransaction()) $db->rollBack();
    respond(['ok' => false, 'message' => $exception->getMessage()], 403);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Update expense] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudo actualizar el gasto.'], 500);
}
