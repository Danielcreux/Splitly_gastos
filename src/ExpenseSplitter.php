<?php
declare(strict_types=1);

final class ExpenseSplitException extends DomainException
{
}

final class ExpenseSplitter
{
    public static function isRequested(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * @return array<int, array{user_id: int, amount_owed: string, is_settled: int}>
     */
    public static function distribute(float $amount, array $memberIds, int $payerId, bool $splitExpense): array
    {
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        if (!in_array($payerId, $memberIds, true)) {
            throw new DomainException('El pagador no pertenece al grupo.');
        }
        if ($splitExpense && count($memberIds) < 2) {
            throw new ExpenseSplitException('Para dividir un gasto, el grupo debe tener al menos dos participantes activos.');
        }

        $participants = $splitExpense ? $memberIds : [$payerId];
        $totalCents = (int) round($amount * 100);
        $baseCents = intdiv($totalCents, count($participants));
        $remainder = $totalCents % count($participants);

        return array_map(
            static function (int $memberId, int $index) use ($baseCents, $remainder, $payerId): array {
                $cents = $baseCents + ($index < $remainder ? 1 : 0);
                return [
                    'user_id' => $memberId,
                    'amount_owed' => number_format($cents / 100, 2, '.', ''),
                    'is_settled' => $memberId === $payerId ? 1 : 0,
                ];
            },
            $participants,
            array_keys($participants)
        );
    }
}
