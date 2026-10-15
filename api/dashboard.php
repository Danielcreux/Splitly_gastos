<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../src/SplitlyRepository.php';
requireMethod('GET');

$db = databaseOrFail();
$authenticatedUser = authenticatedUser($db);
$userId = (int) $authenticatedUser['id'];

try {
    $repository = new SplitlyRepository($db);
    $user = $repository->userById($userId);
    // The dashboard only renders four cards. Limit the query at the source so
    // opening Inicio never fetches the user's complete group history.
    $groups = $repository->groupsForUser($userId, 4);
    $recentExpenses = $repository->expensesForUser($userId, 5);
    $balances = $repository->balancesForUser($userId, 0);

    $expenseSummaryStatement = $db->prepare(
        "SELECT COALESCE(SUM(CASE
                    WHEN e.expense_date >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
                     AND e.expense_date < DATE_ADD(DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                    THEN es.amount_owed ELSE 0 END), 0) AS month_spent,
                (SELECT COUNT(*)
                   FROM expenses registered
                   INNER JOIN group_members membership ON membership.group_id = registered.group_id
                      AND membership.user_id = :count_user_id AND membership.status = 'active'
                  WHERE registered.status = 'active') AS expense_count
         FROM expense_splits es
         INNER JOIN expenses e ON e.id = es.expense_id AND e.status = 'active'
         WHERE es.user_id = :summary_user_id"
    );
    $expenseSummaryStatement->execute([
        'count_user_id' => $userId,
        'summary_user_id' => $userId,
    ]);
    $expenseSummary = $expenseSummaryStatement->fetch() ?: ['month_spent' => 0, 'expense_count' => 0];

    $owedToUser = 0.0;
    $userOwes = 0.0;
    $groupBalances = [];
    foreach ($balances as $balance) {
        $amount = (float) $balance['amount'];
        if ($amount > 0) $owedToUser += $amount;
        if ($amount < 0) $userOwes += abs($amount);
        $groupId = (int) $balance['group_id'];
        $groupBalances[$groupId] = ($groupBalances[$groupId] ?? 0.0) + $amount;
    }

    $expensePayload = array_map(static fn(array $expense): array => [
        'id' => (int) $expense['id'],
        'concept' => $expense['description'],
        'amount' => (float) $expense['total'],
        'yourShare' => (float) $expense['share'],
        'category' => $expense['category'] ?? 'Otros',
        'categoryIcon' => $expense['icon'] ?? 'receipt',
        'groupId' => (int) $expense['group_id'],
        'groupName' => $expense['group'],
        'paidById' => (int) $expense['paid_by'],
        'paidByName' => trim((string) $expense['person']),
        'date' => $expense['expense_date'],
    ], $recentExpenses);

    $groupPayload = array_map(static fn(array $group): array => [
        'id' => (int) $group['id'],
        'name' => $group['name'],
        'memberCount' => (int) $group['member_count'],
        'balance' => round((float) ($groupBalances[(int) $group['id']] ?? 0), 2),
        'totalSpent' => (float) $group['total'],
        'icon' => $group['icon'] ?? 'users',
    ], $groups);

    respond([
        'ok' => true,
        'userName' => $user['first_name'] ?? $authenticatedUser['first_name'],
        'currency' => $user['currency'] ?? 'EUR',
        'summary' => [
            'balance' => round($owedToUser - $userOwes, 2),
            'owes' => round($userOwes, 2),
            'owedToUser' => round($owedToUser, 2),
            'monthSpent' => round((float) $expenseSummary['month_spent'], 2),
            'expenseCount' => (int) $expenseSummary['expense_count'],
        ],
        'recentExpenses' => $expensePayload,
        'activeGroups' => $groupPayload,
    ]);
} catch (Throwable $exception) {
    error_log('[Mobile dashboard] ' . $exception->getMessage());
    respond(['ok' => false, 'error' => 'dashboard_failed', 'message' => 'No se pudo cargar el resumen.'], 500);
}
