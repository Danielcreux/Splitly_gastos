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
    $databaseExpenses = $repository->expensesForUser($currentUserId);
    $databaseActivity = $repository->activityForUser($currentUserId);
    $databaseBalances = $repository->balancesForUser($currentUserId);
    $notifications = $repository->notificationsForUser($currentUserId);

    // Se respeta incluso un resultado vacío para cuentas nuevas sin grupos.
    $groups = $databaseGroups;
    $expenses = $databaseExpenses;
    $activity = $databaseActivity;
    $balances = $databaseBalances;
    $monthlyEvolution = $repository->monthlyEvolution($currentUserId);
    $databaseConnected = true;
} catch (Throwable $exception) {
    error_log('[Splitly database] ' . $exception->getMessage());
    http_response_code(503);
    exit('Splitly no está disponible temporalmente. Inténtalo de nuevo más tarde.');
}

// Métricas derivadas de una única fuente para evitar cifras hardcodeadas.
$totalSpent = array_sum(array_map(fn(array $expense): float => (float) ($expense['share'] ?? 0), $expenses));
$owedToUser = array_sum(array_map(fn(array $balance): float => max(0, (float) $balance['amount']), $balances));
$userOwes = abs(array_sum(array_map(fn(array $balance): float => min(0, (float) $balance['amount']), $balances)));
$summary = [
    'total_spent' => $totalSpent,
    'owed_to_user' => $owedToUser,
    'user_owes' => $userOwes,
    'net_balance' => $owedToUser - $userOwes,
    'expense_count' => count($expenses),
    'group_count' => count($groups),
];
$categoryTotals = [];
foreach ($expenses as $expense) {
    $category = (string) ($expense['category'] ?? 'Otros');
    $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + (float) ($expense['share'] ?? 0);
}
arsort($categoryTotals);
