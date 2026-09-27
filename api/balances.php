<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../src/SplitlyRepository.php';
requireMethod('GET');
$db = databaseOrFail();
$userId = (int) authenticatedUser($db)['id'];
try {
    $balances = (new SplitlyRepository($db))->balancesForUser($userId, 0);
    $owed = 0.0;
    $owes = 0.0;
    $items = [];
    foreach ($balances as $balance) {
        $amount = round((float) $balance['amount'], 2);
        if ($amount > 0) $owed += $amount;
        if ($amount < 0) $owes += abs($amount);
        $items[] = [
            'groupId' => (int) $balance['group_id'],
            'userId' => (int) $balance['user_id'],
            'name' => $balance['name'],
            'detail' => $balance['detail'],
            'amount' => $amount,
        ];
    }
    respond(['ok' => true, 'summary' => [
        'owedToUser' => round($owed, 2), 'userOwes' => round($owes, 2),
        'netBalance' => round($owed - $owes, 2),
    ], 'balances' => $items]);
} catch (Throwable $exception) {
    error_log('[Mobile balances] ' . $exception->getMessage());
    respond(['ok' => false, 'error' => 'balances_failed', 'message' => 'No se pudieron cargar los saldos.'], 500);
}
