<?php

namespace App\Helpers;

use App\Models\Enums\AccountEntryDirection;
use App\Models\Enums\AccountEntryValidationStatus;
use Illuminate\Support\Facades\DB;

/**
 * Saldo corrido por línea de cuenta corriente, calculado en vivo.
 *
 * Orden canónico: occurred_at asc, id asc. Solo los movimientos validados suman
 * (igual que customers.current_balance): débito +monto, crédito -monto.
 * Las líneas no validadas quedan con running_balance = null.
 *
 * Solo se expone en la API del cliente (GET /customers/{id} y
 * GET /customers/{id}/account-entries), no en el listado global.
 */
class AccountEntryRunningBalanceHelper
{
    /**
     * Setea el atributo `running_balance` (float con 2 decimales o null) en cada
     * entry. Una sola consulta liviana por cliente presente en el lote.
     *
     * @param iterable $entries AccountEntry
     */
    public static function attach($entries): void
    {
        $wantedByCustomer = [];
        foreach ($entries as $entry) {
            $wantedByCustomer[(int) $entry->customer_id][(int) $entry->id] = true;
        }

        $balances = [];
        foreach ($wantedByCustomer as $customerId => $wantedIds) {
            $balances += self::balancesForCustomer($customerId, $wantedIds);
        }

        foreach ($entries as $entry) {
            $id = (int) $entry->id;
            $entry->setAttribute('running_balance', array_key_exists($id, $balances) ? $balances[$id] : null);
        }
    }

    /**
     * Saldo corrido de los movimientos validados del cliente, indexado por id.
     * Usa el query builder (sin hidratar modelos) y suma en centavos enteros
     * para no acumular error de punto flotante.
     *
     * @param int $customerId
     * @param array<int, true>|null $onlyIds si se pasa, solo devuelve esos ids
     * @return array<int, float>
     */
    public static function balancesForCustomer(int $customerId, ?array $onlyIds = null): array
    {
        $rows = DB::table('account_entries')
            ->where('customer_id', $customerId)
            ->where('validation_status', AccountEntryValidationStatus::VALIDATED)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['id', 'direction', 'amount']);

        $cents = 0;
        $balances = [];
        foreach ($rows as $row) {
            $amountCents = (int) round(((float) $row->amount) * 100);
            $cents += $row->direction === AccountEntryDirection::DEBIT ? $amountCents : -$amountCents;

            $id = (int) $row->id;
            if ($onlyIds === null || isset($onlyIds[$id])) {
                $balances[$id] = round($cents / 100, 2);
            }
        }

        return $balances;
    }
}
