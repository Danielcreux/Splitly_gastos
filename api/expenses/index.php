<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
requireMethod('GET');
$db = databaseOrFail();
$userId = (int) authenticatedUser($db)['id'];
$pagination = paginationParams();
[$sortField, $sortOrder] = allowedSort([
    'created_at' => 'e.created_at',
    'date' => 'e.expense_date',
    'amount' => 'e.amount',
    'description' => 'e.description',
], 'date');

$where = ["e.status = 'active'", "gm.user_id = :user_id", "gm.status = 'active'"];
$parameters = ['user_id' => $userId, 'share_user' => $userId, 'payer_user' => $userId];
$countParameters = ['user_id' => $userId, 'share_user' => $userId];
$query = trim((string) ($_GET['search'] ?? $_GET['q'] ?? ''));
if ($query !== '') {
    $where[] = 'e.description LIKE :query';
    $parameters['query'] = '%' . mb_substr($query, 0, 100) . '%';
    $countParameters['query'] = $parameters['query'];
}
foreach (['group_id' => 'e.group_id', 'category_id' => 'e.category_id', 'paid_by' => 'e.paid_by'] as $parameter => $column) {
    $value = filter_var($_GET[$parameter] ?? null, FILTER_VALIDATE_INT);
    if ($value) {
        $where[] = "$column = :$parameter";
        $parameters[$parameter] = $value;
        $countParameters[$parameter] = $value;
    }
}
foreach (['date_from' => '>=', 'date_to' => '<='] as $parameter => $operator) {
    $value = (string) ($_GET[$parameter] ?? '');
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if ($value !== '' && $date && $date->format('Y-m-d') === $value) {
        $where[] = "e.expense_date $operator :$parameter";
        $parameters[$parameter] = $value;
        $countParameters[$parameter] = $value;
    }
}
$status = (string) ($_GET['status'] ?? '');
if (in_array($status, ['settled', 'paid', 'pending'], true)) {
    $where[] = match ($status) {
        'settled' => 'es.is_settled = 1',
        'paid' => 'COALESCE(es.is_settled,0) = 0 AND e.paid_by = :status_user',
        default => 'COALESCE(es.is_settled,0) = 0 AND e.paid_by <> :status_user',
    };
    if ($status !== 'settled') {
        $parameters['status_user'] = $userId;
        $countParameters['status_user'] = $userId;
    }
}
$whereSql = implode(' AND ', $where);
$count = $db->prepare(
    "SELECT COUNT(*) FROM expenses e
     INNER JOIN group_members gm ON gm.group_id=e.group_id
     LEFT JOIN expense_splits es ON es.expense_id=e.id AND es.user_id=:share_user
     WHERE $whereSql"
);
$count->execute($countParameters);
$total = (int) $count->fetchColumn();

$statement = $db->prepare(
    "SELECT e.id,e.description,e.amount,e.notes,e.group_id,e.category_id,e.paid_by,e.expense_date,e.split_method,
            g.name AS group_name,c.name AS category,c.icon AS category_icon,c.color AS category_color,
            TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS paid_by_name,
            COALESCE(es.amount_owed,0) AS your_share,
            CASE WHEN es.is_settled=1 THEN 'Liquidado' WHEN e.paid_by=:payer_user THEN 'Pagado' ELSE 'Pendiente' END AS expense_status
     FROM expenses e
     INNER JOIN expense_groups g ON g.id=e.group_id
     INNER JOIN group_members gm ON gm.group_id=e.group_id
     INNER JOIN users u ON u.id=e.paid_by
     LEFT JOIN categories c ON c.id=e.category_id
     LEFT JOIN expense_splits es ON es.expense_id=e.id AND es.user_id=:share_user
     WHERE $whereSql
     ORDER BY $sortField $sortOrder, e.id $sortOrder
     LIMIT :per_page OFFSET :offset"
);
foreach ($parameters as $name => $value) {
    $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$statement->bindValue(':per_page', $pagination['per_page'], PDO::PARAM_INT);
$statement->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$statement->execute();
respondPaged(array_map('expensePayload', $statement->fetchAll()), $total, $pagination);
