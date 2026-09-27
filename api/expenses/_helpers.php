<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

function expensePayload(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'concept' => $row['description'],
        'amount' => (float) $row['amount'],
        'categoryId' => (int) $row['category_id'],
        'categoryName' => $row['category'],
        'categoryIcon' => $row['category_icon'] ?? 'receipt',
        'categoryColor' => $row['category_color'] ?? '#0B6B57',
        'groupId' => (int) $row['group_id'],
        'groupName' => $row['group_name'],
        'paidById' => (int) $row['paid_by'],
        'paidByName' => trim((string) $row['paid_by_name']),
        'date' => $row['expense_date'],
        'notes' => ($row['notes'] ?? null) ?: null,
        'splitMethod' => $row['split_method'],
        'yourShare' => (float) ($row['your_share'] ?? 0),
        'status' => $row['expense_status'] ?? null,
    ];
}
