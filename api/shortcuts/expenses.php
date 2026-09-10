<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
shortcutRequireMethod('POST');
$db = shortcutDatabase();
$user = shortcutCurrentUser($db);
$userId = (int) $user['id'];
$data = shortcutRequestData();

$amount = parseLocalizedDecimal($data['amount'] ?? null);
$description = trim((string) ($data['description'] ?? ''));
$categoryInput = trim((string) ($data['category'] ?? $data['category_id'] ?? ''));
$groupInput = trim((string) ($data['group'] ?? $data['group_id'] ?? ''));
$payerInput = trim((string) ($data['paid_by'] ?? 'Yo'));
$isShared = shortcutBoolean($data['is_shared'] ?? true, true);
$splitType = mb_strtolower(trim((string) ($data['split_type'] ?? 'equal')));
$date = (string) ($data['date'] ?? date('Y-m-d'));

if ($amount === false || $amount <= 0 || $amount > 9999999999.99) {
    shortcutRespond(['ok' => false, 'error' => 'invalid_amount', 'message' => 'El importe no es válido.'], 422);
}
if ($description === '' || mb_strlen($description) > 180) {
    shortcutRespond(['ok' => false, 'error' => 'invalid_description', 'message' => 'Indica un concepto de hasta 180 caracteres.'], 422);
}
$validDate = DateTimeImmutable::createFromFormat('Y-m-d', $date);
if (!$validDate || $validDate->format('Y-m-d') !== $date) {
    shortcutRespond(['ok' => false, 'error' => 'invalid_date', 'message' => 'La fecha debe usar el formato AAAA-MM-DD.'], 422);
}
if ($splitType !== 'equal') {
    shortcutRespond(['ok' => false, 'error' => 'unsupported_split', 'message' => 'La primera versión del Atajo admite split_type equal.'], 422);
}

try {
    $groupStatement = $db->prepare(
        "SELECT g.id, g.name FROM expense_groups g
         INNER JOIN group_members gm ON gm.group_id = g.id
           AND gm.user_id = :user_id AND gm.status = 'active'
         WHERE g.is_archived = 0 ORDER BY g.name"
    );
    $groupStatement->execute(['user_id' => $userId]);
    $groups = $groupStatement->fetchAll();
    $group = null;
    foreach ($groups as $candidate) {
        if ($groupInput !== '' && ((ctype_digit($groupInput) && (int) $candidate['id'] === (int) $groupInput)
            || mb_strtolower($candidate['name']) === mb_strtolower($groupInput))) {
            $group = $candidate;
            break;
        }
    }
    if ($group === null && $groupInput === '' && count($groups) === 1) $group = $groups[0];
    if ($group === null) {
        shortcutRespond([
            'ok' => false,
            'error' => 'group_required',
            'message' => count($groups) > 1 ? 'Indica el grupo porque perteneces a más de uno.' : 'No se encontró un grupo activo.',
            'groups' => array_column($groups, 'name'),
        ], 422);
    }
    $groupId = (int) $group['id'];

    $categoryStatement = $db->prepare('SELECT id, name FROM categories WHERE is_active = 1 AND (id = :id OR LOWER(name) = LOWER(:name)) LIMIT 1');
    $categoryStatement->execute(['id' => ctype_digit($categoryInput) ? (int) $categoryInput : 0, 'name' => $categoryInput]);
    $category = $categoryStatement->fetch();
    if (!$category) {
        shortcutRespond(['ok' => false, 'error' => 'invalid_category', 'message' => 'La categoría no existe. Consulta options.php para ver las disponibles.'], 422);
    }

    $membersStatement = $db->prepare(
        "SELECT u.id, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) AS name
         FROM group_members gm INNER JOIN users u ON u.id = gm.user_id
         WHERE gm.group_id = :group_id AND gm.status = 'active' ORDER BY gm.joined_at, u.id"
    );
    $membersStatement->execute(['group_id' => $groupId]);
    $members = $membersStatement->fetchAll();
    $payer = null;
    if (in_array(mb_strtolower($payerInput), ['yo', 'me', 'mí', 'mi'], true)) {
        foreach ($members as $member) if ((int) $member['id'] === $userId) $payer = $member;
    } else {
        foreach ($members as $member) {
            $firstName = strtok($member['name'], ' ') ?: $member['name'];
            if (in_array(mb_strtolower($payerInput), [mb_strtolower($member['name']), mb_strtolower($firstName)], true)) {
                if ($payer !== null) shortcutRespond(['ok' => false, 'error' => 'ambiguous_payer', 'message' => 'Usa el nombre completo del pagador.'], 422);
                $payer = $member;
            }
        }
    }
    if (!$payer) {
        shortcutRespond(['ok' => false, 'error' => 'invalid_payer', 'message' => 'El pagador no pertenece al grupo.', 'members' => array_column($members, 'name')], 422);
    }
    $payerId = (int) $payer['id'];

    $splitMembers = $isShared ? $members : [$payer];
    if ($isShared && isset($data['shared_with']) && is_array($data['shared_with'])) {
        $wanted = array_map(static fn($value): string => mb_strtolower(trim((string) $value)), $data['shared_with']);
        $splitMembers = array_values(array_filter($members, static function (array $member) use ($wanted, $payerId): bool {
            return (int) $member['id'] === $payerId
                || in_array((string) $member['id'], $wanted, true)
                || in_array(mb_strtolower($member['name']), $wanted, true);
        }));
    }
    if ($splitMembers === []) shortcutRespond(['ok' => false, 'error' => 'empty_split', 'message' => 'Selecciona al menos una persona para el reparto.'], 422);

    $db->beginTransaction();
    $expense = $db->prepare(
        "INSERT INTO expenses (group_id, category_id, paid_by, created_by, description, amount, expense_date, split_method)
         VALUES (:group_id, :category_id, :paid_by, :created_by, :description, :amount, :expense_date, :split_method)"
    );
    $expense->execute([
        'group_id' => $groupId, 'category_id' => (int) $category['id'], 'paid_by' => $payerId,
        'created_by' => $userId, 'description' => $description,
        'amount' => number_format((float) $amount, 2, '.', ''), 'expense_date' => $date,
        'split_method' => $isShared ? 'equal' : 'exact',
    ]);
    $expenseId = (int) $db->lastInsertId();
    $totalCents = (int) round((float) $amount * 100);
    $baseCents = intdiv($totalCents, count($splitMembers));
    $remainder = $totalCents % count($splitMembers);
    $split = $db->prepare('INSERT INTO expense_splits (expense_id, user_id, amount_owed, is_settled) VALUES (:expense_id, :user_id, :amount_owed, :is_settled)');
    foreach ($splitMembers as $index => $member) {
        $memberId = (int) $member['id'];
        $memberCents = $baseCents + ($index < $remainder ? 1 : 0);
        $split->execute([
            'expense_id' => $expenseId, 'user_id' => $memberId,
            'amount_owed' => number_format($memberCents / 100, 2, '.', ''),
            'is_settled' => $memberId === $payerId ? 1 : 0,
        ]);
    }
    $db->prepare(
        "INSERT INTO activity_log (group_id, actor_id, event_type, entity_type, entity_id, message, metadata)
         VALUES (:group_id, :actor_id, 'expense.created', 'expense', :entity_id, :message, :metadata)"
    )->execute([
        'group_id' => $groupId, 'actor_id' => $userId, 'entity_id' => $expenseId,
        'message' => 'Se añadió ' . $description,
        'metadata' => json_encode(['amount' => (float) $amount, 'currency' => $user['currency'] ?? 'EUR', 'source' => 'ios_shortcut']),
    ]);
    if ($isShared) {
        $db->prepare(
            "INSERT INTO notifications (user_id, type, title, message, action_url)
             SELECT gm.user_id, 'expense_added', 'Nuevo gasto', :message, '#expenses'
             FROM group_members gm LEFT JOIN user_preferences p ON p.user_id = gm.user_id
             WHERE gm.group_id = :group_id AND gm.status = 'active' AND gm.user_id <> :actor_id
               AND COALESCE(p.notify_new_expense, 1) = 1"
        )->execute(['message' => $description . ' se añadió al grupo.', 'group_id' => $groupId, 'actor_id' => $userId]);
    }
    $db->commit();
    shortcutRespond([
        'ok' => true, 'id' => $expenseId, 'message' => 'Gasto guardado en Splitly.',
        'expense' => [
            'amount' => (float) $amount, 'category' => $category['name'], 'description' => $description,
            'group' => $group['name'], 'paid_by' => $payer['name'], 'is_shared' => $isShared,
            'split_type' => $isShared ? 'equal' : null, 'participants' => array_column($splitMembers, 'name'),
        ],
    ], 201);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Shortcut expense] ' . $exception->getMessage());
    shortcutRespond(['ok' => false, 'error' => 'expense_failed', 'message' => 'No se pudo guardar el gasto.'], 500);
}
