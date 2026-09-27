<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
requireMethod('GET');
$db = databaseOrFail();
$userId = (int) authenticatedUser($db)['id'];
$groupId = filter_var($_GET['group_id'] ?? null, FILTER_VALIDATE_INT);
if (!$groupId) {
    respond(['ok' => false, 'message' => 'El grupo no es válido.'], 422);
}
groupAccess($db, (int) $groupId, $userId);
$pagination = paginationParams();
$search = trim((string) ($_GET['search'] ?? ''));
$where = ["e.group_id=:group_id", "e.status='active'"];
$parameters = ['group_id' => (int) $groupId];
if ($search !== '') {
    $where[] = 'e.description LIKE :search';
    $parameters['search'] = '%' . mb_substr($search, 0, 100) . '%';
}
$whereSql = implode(' AND ', $where);
$count = $db->prepare("SELECT COUNT(*) FROM expenses e WHERE $whereSql");
$count->execute($parameters);
$total = (int) $count->fetchColumn();
$statement = $db->prepare(
    "SELECT e.id,e.description AS concept,e.amount,e.expense_date AS date,
            c.name AS categoryName,TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS paidByName
     FROM expenses e INNER JOIN users u ON u.id=e.paid_by LEFT JOIN categories c ON c.id=e.category_id
     WHERE $whereSql ORDER BY e.expense_date DESC,e.id DESC LIMIT :per_page OFFSET :offset"
);
foreach ($parameters as $name => $value) {
    $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$statement->bindValue(':per_page', $pagination['per_page'], PDO::PARAM_INT);
$statement->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$statement->execute();
$expenses = array_map(static fn(array $expense): array => [
    'id' => (int) $expense['id'], 'concept' => $expense['concept'], 'amount' => (float) $expense['amount'],
    'date' => $expense['date'], 'categoryName' => $expense['categoryName'] ?? 'Otros', 'paidByName' => $expense['paidByName'],
], $statement->fetchAll());
respondPaged($expenses, $total, $pagination);
