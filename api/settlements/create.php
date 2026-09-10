<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../src/BalanceCalculator.php';
requirePost();

$data = requestData();
$userId = currentUserId();
$groupId = filter_var($data['group_id'] ?? null, FILTER_VALIDATE_INT);
$counterpartyId = filter_var($data['counterparty_id'] ?? null, FILTER_VALIDATE_INT);
$amount = parseLocalizedDecimal($data['amount'] ?? null);
$direction = (string) ($data['direction'] ?? '');
if (!$groupId || !$counterpartyId || $counterpartyId === $userId || $amount === false || $amount <= 0 || !in_array($direction, ['paid_by_me', 'paid_to_me'], true)) {
    respond(['ok' => false, 'message' => 'Los datos de la liquidación no son válidos.'], 422);
}

$db = databaseOrFail();
try {
    $db->beginTransaction();
    $balanceQuery = $db->prepare(
        "SELECT b.user_id, b.balance
         FROM v_member_balances b
         INNER JOIN group_members mine ON mine.group_id = b.group_id
           AND mine.user_id = :current_user AND mine.status = 'active'
         WHERE b.group_id = :group_id
         ORDER BY b.user_id"
    );
    $balanceQuery->execute(['current_user' => $userId, 'group_id' => $groupId]);
    $expectedTransfer = null;
    foreach (BalanceCalculator::settlementPlan($balanceQuery->fetchAll()) as $transfer) {
        if (($transfer['payer_id'] === $userId && $transfer['receiver_id'] === (int) $counterpartyId)
            || ($transfer['receiver_id'] === $userId && $transfer['payer_id'] === (int) $counterpartyId)) {
            $expectedTransfer = $transfer;
            break;
        }
    }
    $expectedDirection = $expectedTransfer && $expectedTransfer['payer_id'] === $userId ? 'paid_by_me' : 'paid_to_me';
    if (!$expectedTransfer || $direction !== $expectedDirection || $amount > $expectedTransfer['amount'] + 0.01) {
        throw new DomainException('El saldo ha cambiado. Recarga la página e inténtalo de nuevo.');
    }

    $payerId = $direction === 'paid_by_me' ? $userId : $counterpartyId;
    $receiverId = $direction === 'paid_by_me' ? $counterpartyId : $userId;
    $db->prepare(
        "INSERT INTO settlements
          (group_id, payer_id, receiver_id, recorded_by, amount, payment_method, status, paid_at)
         VALUES (:group_id, :payer_id, :receiver_id, :recorded_by, :amount, 'other', 'completed', NOW())"
    )->execute([
        'group_id' => $groupId, 'payer_id' => $payerId, 'receiver_id' => $receiverId,
        'recorded_by' => $userId, 'amount' => number_format((float) $amount, 2, '.', ''),
    ]);
    $db->prepare(
        "INSERT INTO activity_log (group_id, actor_id, event_type, entity_type, entity_id, message, metadata)
         VALUES (:group_id, :actor_id, 'payment.created', 'settlement', LAST_INSERT_ID(), :message, :metadata)"
    )->execute([
        'group_id' => $groupId, 'actor_id' => $userId,
        'message' => 'Se registró una liquidación',
        'metadata' => json_encode(['amount' => (float) $amount, 'currency' => 'EUR']),
    ]);
    $db->prepare(
        "INSERT INTO notifications (user_id, type, title, message, action_url)
         SELECT :notification_user, 'payment_received', 'Liquidación registrada', :message, '#balances'
         FROM users u LEFT JOIN user_preferences p ON p.user_id = u.id
         WHERE u.id = :preference_user AND COALESCE(p.notify_payment, 1) = 1"
    )->execute([
        'notification_user' => $counterpartyId,
        'preference_user' => $counterpartyId,
        'message' => 'Se registró una liquidación de ' . number_format((float) $amount, 2, ',', '.') . ' €.',
    ]);
    $db->commit();
    respond(['ok' => true, 'message' => 'Liquidación registrada correctamente.']);
} catch (DomainException $exception) {
    if ($db->inTransaction()) $db->rollBack();
    respond(['ok' => false, 'message' => $exception->getMessage()], 409);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[Create settlement] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudo registrar la liquidación.'], 500);
}
