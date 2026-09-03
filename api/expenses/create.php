<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requirePost();

$data = requestData();
$userId = currentUserId();
$description = trim((string) ($data['description'] ?? ''));
$amount = filter_var($data['amount'] ?? null, FILTER_VALIDATE_FLOAT);
$date = (string) ($data['date'] ?? '');
$groupId = filter_var($data['group_id'] ?? null, FILTER_VALIDATE_INT);
$categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
$payerId = filter_var($data['payer_id'] ?? $userId, FILTER_VALIDATE_INT);
$splitExpense = isset($data['split_expense']) && in_array((string) $data['split_expense'], ['1', 'true', 'on'], true);

$validDate = DateTimeImmutable::createFromFormat('Y-m-d', $date);
if ($description === '' || mb_strlen($description) > 180) {
    respond(['ok' => false, 'message' => 'Indica una descripción válida.'], 422);
}
if ($amount === false || $amount <= 0 || $amount > 9999999999.99) {
    respond(['ok' => false, 'message' => 'El importe no es válido.'], 422);
}
if (!$validDate || $validDate->format('Y-m-d') !== $date) {
    respond(['ok' => false, 'message' => 'La fecha no es válida.'], 422);
}
if (!$groupId || !$categoryId || !$payerId) {
    respond(['ok' => false, 'message' => 'Selecciona grupo, categoría y pagador.'], 422);
}

$db = databaseOrFail();
try {
    $db->beginTransaction();

    $membership = $db->prepare(
        "SELECT gm.user_id FROM group_members gm
         WHERE gm.group_id = :group_id AND gm.status = 'active'"
    );
    $membership->execute(['group_id' => $groupId]);
    $memberIds = array_map('intval', array_column($membership->fetchAll(), 'user_id'));
    if (!in_array($userId, $memberIds, true) || !in_array((int) $payerId, $memberIds, true)) {
        throw new DomainException('El usuario o pagador no pertenece al grupo.');
    }

    $expense = $db->prepare(
        "INSERT INTO expenses
           (group_id, category_id, paid_by, created_by, description, amount, expense_date, split_method)
         VALUES
           (:group_id, :category_id, :paid_by, :created_by, :description, :amount, :expense_date, :split_method)"
    );
    $expense->execute([
        'group_id' => $groupId,
        'category_id' => $categoryId,
        'paid_by' => $payerId,
        'created_by' => $userId,
        'description' => $description,
        'amount' => number_format((float) $amount, 2, '.', ''),
        'expense_date' => $date,
        'split_method' => $splitExpense ? 'equal' : 'exact',
    ]);
    $expenseId = (int) $db->lastInsertId();

    // Trabajamos en céntimos para que la suma de repartos sea siempre exacta.
    $totalCents = (int) round((float) $amount * 100);
    $splitMemberIds = $splitExpense ? $memberIds : [(int) $payerId];
    $baseCents = intdiv($totalCents, count($splitMemberIds));
    $remainder = $totalCents % count($splitMemberIds);
    $split = $db->prepare(
        "INSERT INTO expense_splits (expense_id, user_id, amount_owed, is_settled)
         VALUES (:expense_id, :user_id, :amount_owed, :is_settled)"
    );
    foreach ($splitMemberIds as $index => $memberId) {
        $memberCents = $baseCents + ($index < $remainder ? 1 : 0);
        $split->execute([
            'expense_id' => $expenseId,
            'user_id' => $memberId,
            'amount_owed' => number_format($memberCents / 100, 2, '.', ''),
            'is_settled' => $memberId === (int) $payerId ? 1 : 0,
        ]);
    }

    $activity = $db->prepare(
        "INSERT INTO activity_log
          (group_id, actor_id, event_type, entity_type, entity_id, message, metadata)
         VALUES
          (:group_id, :actor_id, 'expense.created', 'expense', :entity_id, :message, :metadata)"
    );
    $activity->execute([
        'group_id' => $groupId,
        'actor_id' => $userId,
        'entity_id' => $expenseId,
        'message' => "Se añadió {$description}",
        'metadata' => json_encode(['amount' => (float) $amount, 'currency' => 'EUR']),
    ]);

    $notifyMembers = $db->prepare(
        "INSERT INTO notifications (user_id, type, title, message, action_url)
         SELECT gm.user_id, 'expense_added', 'Nuevo gasto', :message, '#expenses'
         FROM group_members gm
         LEFT JOIN user_preferences p ON p.user_id = gm.user_id
         WHERE gm.group_id = :group_id AND gm.status = 'active'
           AND gm.user_id <> :actor_id AND COALESCE(p.notify_new_expense, 1) = 1"
    );
    if ($splitExpense) {
        $notifyMembers->execute([
            'message' => $description . ' se añadió al grupo.',
            'group_id' => $groupId,
            'actor_id' => $userId,
        ]);
    }

    $db->commit();
    respond(['ok' => true, 'message' => 'Gasto guardado correctamente.', 'id' => $expenseId], 201);
} catch (DomainException $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    respond(['ok' => false, 'message' => $exception->getMessage()], 403);
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[Create expense] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudo guardar el gasto.'], 500);
}
