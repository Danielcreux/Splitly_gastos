<?php
declare(strict_types=1);

require_once __DIR__ . '/BalanceCalculator.php';

final class SplitlyRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function userById(int $userId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_path, u.currency, u.locale, u.timezone,
                    COALESCE(p.notify_new_expense, 1) AS notify_new_expense,
                    COALESCE(p.notify_payment, 1) AS notify_payment,
                    COALESCE(p.notify_payment_reminder, 1) AS notify_payment_reminder,
                    COALESCE(p.notify_group_updates, 1) AS notify_group_updates
             FROM users u LEFT JOIN user_preferences p ON p.user_id = u.id
             WHERE u.id = :id AND u.is_active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        return $statement->fetch() ?: null;
    }

    public function groupsForUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $statement = $this->db->prepare(
            "SELECT g.id, g.name, g.icon, g.color, g.budget, mine.role AS user_role,
                    summary.member_count,
                    summary.total_spent AS total
             FROM expense_groups g
             INNER JOIN group_members mine ON mine.group_id = g.id
                AND mine.user_id = :user_id AND mine.status = 'active'
             INNER JOIN v_group_summaries summary ON summary.group_id = g.id
             WHERE g.is_archived = 0
             ORDER BY g.created_at ASC, g.id ASC
             LIMIT :row_limit OFFSET :row_offset"
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':row_limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $statement->bindValue(':row_offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();

        $membersByGroup = [];
        if ($rows !== []) {
            $groupIds = array_map('intval', array_column($rows, 'id'));
            $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
            $members = $this->db->prepare(
                "SELECT gm.group_id,u.id,TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS name,gm.role
                 FROM group_members gm INNER JOIN users u ON u.id=gm.user_id
                 WHERE gm.status='active' AND gm.group_id IN ($placeholders)
                 ORDER BY gm.group_id,gm.joined_at,gm.user_id"
            );
            $members->execute($groupIds);
            foreach ($members->fetchAll() as $member) {
                $membersByGroup[(int) $member['group_id']][] = $member;
            }
        }

        foreach ($rows as &$row) {
            $row['member_options'] = $membersByGroup[(int) $row['id']] ?? [];
            $row['members'] = array_column($row['member_options'], 'name');
            $row['total'] = (float) $row['total'];
            $row['budget'] = (float) ($row['budget'] ?? 0);
            $row['progress'] = $row['budget'] > 0
                ? min(100, (int) round($row['total'] / $row['budget'] * 100))
                : 0;
            $row['tone'] = $this->toneForIcon((string) $row['icon']);
        }

        return $rows;
    }

    public function expensesForUser(int $userId, int $limit = 20): array
    {
        $statement = $this->db->prepare(
            "SELECT e.id, e.group_id, e.category_id, e.paid_by, e.split_method,
                    (SELECT COUNT(*) FROM expense_splits all_splits WHERE all_splits.expense_id = e.id) AS split_count,
                    c.name AS category,
                    e.description, g.name AS `group`,
                    CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) AS person,
                    DATE_FORMAT(e.expense_date, '%d/%m/%Y') AS date,
                    DATE_FORMAT(e.expense_date, '%Y-%m-%d') AS expense_date,
                    e.amount AS total, COALESCE(es.amount_owed, 0) AS share,
                    CASE
                      WHEN es.is_settled = 1 THEN 'Liquidado'
                      WHEN e.paid_by = :payer_user THEN 'Pagado'
                      ELSE 'Pendiente'
                    END AS status,
                    COALESCE(c.icon, 'receipt') AS icon
             FROM expenses e
             INNER JOIN expense_groups g ON g.id = e.group_id
             INNER JOIN group_members gm ON gm.group_id = g.id
                AND gm.user_id = :member_user AND gm.status = 'active'
             INNER JOIN users u ON u.id = e.paid_by
             LEFT JOIN categories c ON c.id = e.category_id
             LEFT JOIN expense_splits es ON es.expense_id = e.id AND es.user_id = :split_user
             WHERE e.status = 'active'
             ORDER BY e.expense_date DESC, e.id DESC
             LIMIT :row_limit"
        );
        $statement->bindValue(':payer_user', $userId, PDO::PARAM_INT);
        $statement->bindValue(':member_user', $userId, PDO::PARAM_INT);
        $statement->bindValue(':split_user', $userId, PDO::PARAM_INT);
        $statement->bindValue(':row_limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['total'] = (float) $row['total'];
            $row['share'] = (float) $row['share'];
        }
        return $rows;
    }

    public function activityForUser(int $userId, int $limit = 12): array
    {
        $statement = $this->db->prepare(
            "SELECT a.event_type, a.message AS title, g.name AS meta, a.metadata,
                    a.created_at
             FROM activity_log a
             LEFT JOIN expense_groups g ON g.id = a.group_id
             LEFT JOIN group_members gm ON gm.group_id = a.group_id
               AND gm.user_id = :user_id AND gm.status = 'active'
             WHERE a.group_id IS NULL OR gm.user_id IS NOT NULL
             ORDER BY a.created_at DESC LIMIT :row_limit"
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':row_limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(function (array $row): array {
            $metadata = json_decode((string) ($row['metadata'] ?? ''), true) ?: [];
            $amount = isset($metadata['amount']) ? money((float) $metadata['amount']) : '';
            return [
                'icon' => str_contains($row['event_type'], 'payment') ? 'check' : (str_contains($row['event_type'], 'group') ? 'users' : 'receipt'),
                'title' => $row['title'],
                'meta' => $row['meta'] ?? 'Splitly',
                'amount' => $amount,
                'time' => date('d M', strtotime($row['created_at'])),
            ];
        }, $statement->fetchAll());
    }

    public function balancesForUser(int $userId, int $limit = 20): array
    {
        $statement = $this->db->prepare(
            "SELECT b.group_id, b.user_id, b.balance,
                    TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) AS name,
                    g.name AS detail
             FROM v_member_balances b
             INNER JOIN expense_groups g ON g.id = b.group_id
             INNER JOIN group_members mine ON mine.group_id = b.group_id
               AND mine.user_id = :user_id AND mine.status = 'active'
             INNER JOIN users u ON u.id = b.user_id
             WHERE ABS(b.balance) >= 0.01
             ORDER BY b.group_id, b.user_id"
        );
        $statement->execute(['user_id' => $userId]);
        $groups = [];
        foreach ($statement->fetchAll() as $row) {
            $groups[(int) $row['group_id']][] = $row;
        }

        $result = [];
        foreach ($groups as $groupRows) {
            $people = [];
            foreach ($groupRows as $row) $people[(int) $row['user_id']] = $row;
            foreach (BalanceCalculator::settlementPlan($groupRows) as $transfer) {
                if ($transfer['receiver_id'] === $userId) {
                    $counterpartyId = $transfer['payer_id'];
                    $amount = $transfer['amount'];
                } elseif ($transfer['payer_id'] === $userId) {
                    $counterpartyId = $transfer['receiver_id'];
                    $amount = -$transfer['amount'];
                } else {
                    continue;
                }
                $result[] = [
                    'group_id' => (int) $groupRows[0]['group_id'],
                    'user_id' => $counterpartyId,
                    'name' => $people[$counterpartyId]['name'],
                    'detail' => $groupRows[0]['detail'],
                    'amount' => $amount,
                ];
            }
        }
        usort($result, static fn(array $a, array $b): int => abs($b['amount']) <=> abs($a['amount']));
        return $limit > 0 ? array_slice($result, 0, $limit) : $result;
    }

    public function notificationsForUser(int $userId, int $limit = 8): array
    {
        $statement = $this->db->prepare(
            'SELECT id, type, title, message, action_url, read_at, created_at
             FROM notifications WHERE user_id = :user_id
             ORDER BY created_at DESC LIMIT :row_limit'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':row_limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function monthlyEvolution(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT DATE_FORMAT(e.expense_date, '%Y-%m') AS month_key, SUM(es.amount_owed) AS total
             FROM expense_splits es
             INNER JOIN expenses e ON e.id = es.expense_id AND e.status = 'active'
             WHERE es.user_id = :user_id
             GROUP BY DATE_FORMAT(e.expense_date, '%Y-%m')
             ORDER BY month_key DESC LIMIT 12"
        );
        $statement->execute(['user_id' => $userId]);
        return array_reverse($statement->fetchAll());
    }

    public function expenseSummaryForUser(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT COALESCE(SUM(es.amount_owed),0) AS total_spent,
                    COALESCE(SUM(CASE WHEN e.expense_date >= DATE_FORMAT(CURRENT_DATE(),'%Y-%m-01')
                                      AND e.expense_date < DATE_ADD(DATE_FORMAT(CURRENT_DATE(),'%Y-%m-01'),INTERVAL 1 MONTH)
                                THEN es.amount_owed ELSE 0 END),0) AS month_spent,
                    COUNT(*) AS split_count
             FROM expense_splits es INNER JOIN expenses e ON e.id=es.expense_id AND e.status='active'
             WHERE es.user_id=:user_id"
        );
        $statement->execute(['user_id' => $userId]);
        $summary = $statement->fetch() ?: [];
        // El historial muestra todos los gastos de los grupos del usuario, no solo
        // aquellos que tienen una fila en expense_splits para él.
        $expenses = $this->db->prepare(
            "SELECT COUNT(*)
             FROM expenses e
             INNER JOIN group_members gm ON gm.group_id=e.group_id
                AND gm.user_id=:user_id AND gm.status='active'
             WHERE e.status='active'"
        );
        $expenses->execute(['user_id' => $userId]);
        $groups = $this->db->prepare("SELECT COUNT(*) FROM group_members WHERE user_id=:user_id AND status='active'");
        $groups->execute(['user_id' => $userId]);
        return [
            'total_spent' => (float) ($summary['total_spent'] ?? 0),
            'month_spent' => (float) ($summary['month_spent'] ?? 0),
            'expense_count' => (int) $expenses->fetchColumn(),
            'group_count' => (int) $groups->fetchColumn(),
        ];
    }

    public function categoryTotalsForUser(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT COALESCE(c.name,'Otros') AS category,SUM(es.amount_owed) AS total
             FROM expense_splits es INNER JOIN expenses e ON e.id=es.expense_id AND e.status='active'
             LEFT JOIN categories c ON c.id=e.category_id
             WHERE es.user_id=:user_id
             GROUP BY e.category_id,c.name ORDER BY total DESC,c.name ASC"
        );
        $statement->execute(['user_id' => $userId]);
        $result = [];
        foreach ($statement->fetchAll() as $row) $result[$row['category']] = (float) $row['total'];
        return $result;
    }

    private function membersForGroup(int $groupId): array
    {
        $statement = $this->db->prepare(
            "SELECT u.id, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) AS name, gm.role
             FROM group_members gm
             INNER JOIN users u ON u.id = gm.user_id
             WHERE gm.group_id = :group_id AND gm.status = 'active'
             ORDER BY gm.joined_at"
        );
        $statement->execute(['group_id' => $groupId]);
        return $statement->fetchAll();
    }

    private function toneForIcon(string $icon): string
    {
        return match ($icon) {
            'plane' => 'aqua',
            'food' => 'coral',
            'card' => 'blue',
            default => 'mint',
        };
    }
}
