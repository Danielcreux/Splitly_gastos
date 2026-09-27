<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
startSecureSession();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/SplitlyRepository.php';

$currentUserId = (int) $_SESSION['user_id'];
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$databaseConnected = false;
$monthlyEvolution = [];
$currentUser = null;
$notifications = [];

try {
    $repository = new SplitlyRepository(Database::connection());
    $currentUser = $repository->userById($currentUserId);
    if (!$currentUser) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }
    $databaseGroups = $repository->groupsForUser($currentUserId);
    $databaseExpenses = $repository->expensesForUser($currentUserId, 20);
    $databaseActivity = $repository->activityForUser($currentUserId, 20);
    $databaseBalances = $repository->balancesForUser($currentUserId);
    $notifications = $repository->notificationsForUser($currentUserId);

    // Se respeta incluso un resultado vacío para cuentas nuevas sin grupos.
    $groups = $databaseGroups;
    $expenses = $databaseExpenses;
    $activity = $databaseActivity;
    $balances = $databaseBalances;
    $monthlyEvolution = $repository->monthlyEvolution($currentUserId);
    $expenseSummary = $repository->expenseSummaryForUser($currentUserId);
    $categoryTotals = $repository->categoryTotalsForUser($currentUserId);
    $databaseConnected = true;
} catch (Throwable $exception) {
    error_log('[Splitly database] ' . $exception->getMessage());
    http_response_code(503);
    exit('Splitly no está disponible temporalmente. Inténtalo de nuevo más tarde.');
}

// Métricas derivadas de una única fuente para evitar cifras hardcodeadas.
$totalSpent = (float) ($expenseSummary['total_spent'] ?? 0);
$monthSpent = (float) ($expenseSummary['month_spent'] ?? 0);
$owedToUser = array_sum(array_map(fn(array $balance): float => max(0, (float) $balance['amount']), $balances));
$userOwes = abs(array_sum(array_map(fn(array $balance): float => min(0, (float) $balance['amount']), $balances)));
$summary = [
    'total_spent' => $totalSpent,
    'month_spent' => $monthSpent,
    'owed_to_user' => $owedToUser,
    'user_owes' => $userOwes,
    'net_balance' => $owedToUser - $userOwes,
    'expense_count' => (int) ($expenseSummary['expense_count'] ?? 0),
    'group_count' => (int) ($expenseSummary['group_count'] ?? 0),
];
