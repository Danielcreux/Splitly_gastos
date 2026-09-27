<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
requireMethod('GET');
$db = databaseOrFail();
$userId = (int) authenticatedUser($db)['id'];
$range = (string) ($_GET['range'] ?? 'month');
$today = new DateTimeImmutable('today');
$start = match ($range) {
    'six-months' => $today->modify('first day of this month')->modify('-5 months'),
    'year' => $today->setDate((int) $today->format('Y'), 1, 1),
    default => $today->modify('first day of this month'),
};
$summary = $db->prepare(
    "SELECT COUNT(*) AS expense_count,COALESCE(SUM(es.amount_owed),0) AS total_spent,
            COALESCE(AVG(es.amount_owed),0) AS average_expense
     FROM expense_splits es INNER JOIN expenses e ON e.id=es.expense_id AND e.status='active'
     WHERE es.user_id=:user_id AND e.expense_date BETWEEN :date_from AND :date_to"
);
$params = ['user_id' => $userId, 'date_from' => $start->format('Y-m-d'), 'date_to' => $today->format('Y-m-d')];
$summary->execute($params);
$totals = $summary->fetch();
$totalSpent = (float) $totals['total_spent'];
$groupCount = $db->prepare("SELECT COUNT(*) FROM group_members WHERE user_id=:user_id AND status='active'");
$groupCount->execute(['user_id' => $userId]);
$categories = $db->prepare(
    "SELECT COALESCE(c.name,'Otros') AS name,SUM(es.amount_owed) AS amount
     FROM expense_splits es INNER JOIN expenses e ON e.id=es.expense_id AND e.status='active'
     LEFT JOIN categories c ON c.id=e.category_id
     WHERE es.user_id=:user_id AND e.expense_date BETWEEN :date_from AND :date_to
     GROUP BY e.category_id,c.name ORDER BY amount DESC,c.name ASC"
);
$categories->execute($params);
$categoryRows = array_map(static fn(array $row): array => [
    'name' => $row['name'],
    'amount' => round((float) $row['amount'], 2),
    'percentage' => $totalSpent > 0 ? round((float) $row['amount'] / $totalSpent * 100, 2) : 0,
], $categories->fetchAll());
$dateExpression = $range === 'month' ? "DATE_FORMAT(e.expense_date,'%Y-%m-%d')" : "DATE_FORMAT(e.expense_date,'%Y-%m')";
$trend = $db->prepare(
    "SELECT $dateExpression AS bucket,SUM(es.amount_owed) AS amount
     FROM expense_splits es INNER JOIN expenses e ON e.id=es.expense_id AND e.status='active'
     WHERE es.user_id=:user_id AND e.expense_date BETWEEN :date_from AND :date_to
     GROUP BY bucket ORDER BY bucket ASC"
);
$trend->execute($params);
$trendRows = array_map(static fn(array $row): array => [
    'label' => $range === 'month'
        ? (new DateTimeImmutable($row['bucket']))->format('d')
        : (new DateTimeImmutable($row['bucket'] . '-01'))->format('M'),
    'amount' => round((float) $row['amount'], 2),
], $trend->fetchAll());
$top = $categoryRows[0] ?? ['name' => 'Sin datos', 'amount' => 0];
respond([
    'totalSpent' => round($totalSpent, 2),
    'expenseCount' => (int) $totals['expense_count'],
    'averageExpense' => round((float) $totals['average_expense'], 2),
    'groupCount' => (int) $groupCount->fetchColumn(),
    'topCategory' => $top['name'],
    'topCategoryAmount' => $top['amount'],
    'trend' => $trendRows,
    'categories' => $categoryRows,
]);
