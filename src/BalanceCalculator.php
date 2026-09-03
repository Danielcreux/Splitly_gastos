<?php
declare(strict_types=1);

final class BalanceCalculator
{
    /**
     * Convierte balances netos de un grupo en transferencias concretas.
     * Cada entrada requiere `user_id` y `balance`; positivo cobra, negativo paga.
     */
    public static function settlementPlan(array $balances): array
    {
        $creditors = [];
        $debtors = [];
        foreach ($balances as $balance) {
            $cents = (int) round((float) $balance['balance'] * 100);
            if ($cents > 0) {
                $creditors[] = ['user_id' => (int) $balance['user_id'], 'cents' => $cents];
            } elseif ($cents < 0) {
                $debtors[] = ['user_id' => (int) $balance['user_id'], 'cents' => abs($cents)];
            }
        }

        $plan = [];
        $creditorIndex = 0;
        $debtorIndex = 0;
        while (isset($creditors[$creditorIndex], $debtors[$debtorIndex])) {
            $cents = min($creditors[$creditorIndex]['cents'], $debtors[$debtorIndex]['cents']);
            if ($cents > 0) {
                $plan[] = [
                    'payer_id' => $debtors[$debtorIndex]['user_id'],
                    'receiver_id' => $creditors[$creditorIndex]['user_id'],
                    'amount' => $cents / 100,
                ];
            }
            $creditors[$creditorIndex]['cents'] -= $cents;
            $debtors[$debtorIndex]['cents'] -= $cents;
            if ($creditors[$creditorIndex]['cents'] === 0) $creditorIndex++;
            if ($debtors[$debtorIndex]['cents'] === 0) $debtorIndex++;
        }

        return $plan;
    }
}
