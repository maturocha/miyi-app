<?php

namespace Tests\Feature;

use App\Helpers\AccountEntryRunningBalanceHelper;
use App\Models\AccountEntry;
use App\Models\Enums\AccountEntryDirection;
use App\Models\Enums\AccountEntryValidationStatus;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLedgerSchema;
use Tests\TestCase;

class AccountEntryRunningBalanceHelperTest extends TestCase
{
    use BuildsLedgerSchema;

    const DEBIT = AccountEntryDirection::DEBIT;
    const CREDIT = AccountEntryDirection::CREDIT;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildLedgerSchema();
    }

    private function balancesFor(int $customerId): array
    {
        $entries = AccountEntry::where('customer_id', $customerId)->orderBy('id')->get();
        AccountEntryRunningBalanceHelper::attach($entries);

        return $entries->pluck('running_balance', 'id')->all();
    }

    public function test_only_validated_entries_add_up_and_the_rest_are_null(): void
    {
        $customer = $this->createCustomer();
        $a = $this->createEntry($customer, self::DEBIT, '1000.00', '2026-01-01 10:00:00');
        $b = $this->createEntry($customer, self::DEBIT, '500.00', '2026-01-02 10:00:00', AccountEntryValidationStatus::PENDING);
        $c = $this->createEntry($customer, self::CREDIT, '300.50', '2026-01-03 10:00:00');
        $d = $this->createEntry($customer, self::DEBIT, '99.99', '2026-01-04 10:00:00', AccountEntryValidationStatus::NOT_VALIDATED);
        $e = $this->createEntry($customer, self::DEBIT, '0.75', '2026-01-05 10:00:00');

        $balances = $this->balancesFor($customer);

        $this->assertSame(1000.0, $balances[$a]);
        $this->assertNull($balances[$b]);
        $this->assertSame(699.5, $balances[$c]);
        $this->assertNull($balances[$d]);
        $this->assertSame(700.25, $balances[$e]);
    }

    public function test_balance_follows_occurred_at_not_insertion_order(): void
    {
        $customer = $this->createCustomer();
        // Insertados en desorden: el id no coincide con el orden cronológico.
        $late = $this->createEntry($customer, self::CREDIT, '200.00', '2026-03-10 09:00:00');
        $early = $this->createEntry($customer, self::DEBIT, '1000.00', '2026-03-01 09:00:00');
        $middle = $this->createEntry($customer, self::DEBIT, '50.00', '2026-03-05 09:00:00');

        $balances = $this->balancesFor($customer);

        $this->assertSame(1000.0, $balances[$early]);
        $this->assertSame(1050.0, $balances[$middle]);
        $this->assertSame(850.0, $balances[$late]);
    }

    public function test_same_occurred_at_ties_are_broken_by_id_ascending(): void
    {
        $customer = $this->createCustomer();
        $sameMoment = '2026-04-01 12:00:00';
        // Se insertan con ids explícitos fuera de orden para que el desempate no dependa del orden físico.
        $this->createEntry($customer, self::CREDIT, '30.00', $sameMoment, AccountEntryValidationStatus::VALIDATED, 30);
        $this->createEntry($customer, self::DEBIT, '100.00', $sameMoment, AccountEntryValidationStatus::VALIDATED, 10);
        $this->createEntry($customer, self::DEBIT, '5.00', $sameMoment, AccountEntryValidationStatus::VALIDATED, 20);

        $balances = $this->balancesFor($customer);

        $this->assertSame(100.0, $balances[10]);
        $this->assertSame(105.0, $balances[20]);
        $this->assertSame(75.0, $balances[30]);
    }

    public function test_other_customers_do_not_affect_the_balance(): void
    {
        $customer = $this->createCustomer('A');
        $other = $this->createCustomer('B');
        $this->createEntry($other, self::DEBIT, '9999.00', '2026-01-01 00:00:00');
        $mine = $this->createEntry($customer, self::DEBIT, '10.00', '2026-01-02 00:00:00');

        $this->assertSame(10.0, $this->balancesFor($customer)[$mine]);
    }

    public function test_sum_has_no_floating_point_drift(): void
    {
        $customer = $this->createCustomer();
        $last = null;
        for ($i = 0; $i < 10; $i++) {
            $last = $this->createEntry($customer, self::DEBIT, '0.10', sprintf('2026-05-01 10:%02d:00', $i));
        }
        $last2 = $this->createEntry($customer, self::CREDIT, '0.30', '2026-05-01 11:00:00');

        $balances = $this->balancesFor($customer);

        $this->assertSame(1.0, $balances[$last]);
        $this->assertSame(0.7, $balances[$last2]);
    }

    public function test_final_balance_matches_customer_current_balance_rule(): void
    {
        $customer = $this->createCustomer();
        $lastId = null;
        mt_srand(42);
        $expectedCents = 0;
        for ($i = 0; $i < 200; $i++) {
            $cents = mt_rand(1, 500000);
            $direction = mt_rand(0, 2) === 0 ? self::CREDIT : self::DEBIT;
            $status = mt_rand(0, 4) === 0 ? AccountEntryValidationStatus::PENDING : AccountEntryValidationStatus::VALIDATED;
            $lastId = $this->createEntry($customer, $direction, number_format($cents / 100, 2, '.', ''), sprintf('2026-06-01 00:%02d:%02d', intdiv($i, 60), $i % 60), $status);
            if ($status === AccountEntryValidationStatus::VALIDATED) {
                $expectedCents += $direction === self::DEBIT ? $cents : -$cents;
                $lastValidatedId = $lastId;
            }
        }

        $balances = AccountEntryRunningBalanceHelper::balancesForCustomer($customer);

        $this->assertSame(round($expectedCents / 100, 2), $balances[$lastValidatedId]);
    }

    public function test_uses_a_single_lightweight_query_per_customer(): void
    {
        $a = $this->createCustomer('A');
        $b = $this->createCustomer('B');
        for ($i = 0; $i < 20; $i++) {
            $this->createEntry($a, self::DEBIT, '1.00', sprintf('2026-07-01 00:00:%02d', $i));
            $this->createEntry($b, self::CREDIT, '1.00', sprintf('2026-07-01 00:00:%02d', $i));
        }
        $entries = AccountEntry::orderBy('id')->get();

        DB::flushQueryLog();
        DB::enableQueryLog();
        AccountEntryRunningBalanceHelper::attach($entries);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(2, $queries, 'Una consulta por cliente presente en el lote');
        foreach ($queries as $query) {
            $this->assertStringContainsString('select "id", "direction", "amount" from "account_entries"', $query['query']);
        }
        $this->assertSame(20.0, $entries->where('customer_id', $a)->last()->running_balance);
        $this->assertSame(-20.0, $entries->where('customer_id', $b)->last()->running_balance);
    }
}
