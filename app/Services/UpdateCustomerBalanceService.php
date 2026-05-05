<?php

namespace App\Services;

use App\Models\Enums\AccountEntryDirection;
use Illuminate\Support\Facades\DB;

class UpdateCustomerBalanceService
{
    /**
     * Apply a delta to the customer's current_balance.
     * Delta: positive = increase balance (debt), negative = decrease.
     *
     * @param int $customerId
     * @param float $delta
     * @return void
     */
    public function increment(int $customerId, float $delta): void
    {
        if ($delta === 0.0) {
            return;
        }
        DB::table('customers')
            ->where('id', $customerId)
            ->update([
                'current_balance' => DB::raw('current_balance + ' . (float) $delta),
            ]);
    }

    /**
     * Compute delta for an account entry: DEBIT => +amount, CREDIT => -amount.
     *
     * @param string $direction
     * @param float $amount
     * @return float
     */
    public static function deltaForEntry(string $direction, float $amount): float
    {
        return $direction === AccountEntryDirection::DEBIT ? (float) $amount : -(float) $amount;
    }
}
