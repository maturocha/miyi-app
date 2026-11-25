<?php

namespace Tests\Feature;

use App\Models\Enums\AccountEntryDirection;
use App\Models\Enums\AccountEntryValidationStatus;
use App\Models\User;
use Tests\Concerns\BuildsLedgerSchema;
use Tests\TestCase;

/**
 * running_balance solo viaja en la API del cliente:
 *   - GET /api/v1/customers/{id}                  (account_entries.data, últimos 5)
 *   - GET /api/v1/customers/{id}/account-entries  (historial paginado)
 * y NO en el listado global GET /api/v1/account-entries.
 */
class CustomerRunningBalanceApiTest extends TestCase
{
    use BuildsLedgerSchema;

    /** @var int */
    private $customerId;

    /** @var array<string, int> */
    private $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildLedgerSchema();

        $this->actingAs(User::findOrFail($this->createUser(1)), 'api');

        $this->customerId = $this->createCustomer();
        $debit = AccountEntryDirection::DEBIT;
        $credit = AccountEntryDirection::CREDIT;
        $this->ids['charge'] = $this->createEntry($this->customerId, $debit, '1000.00', '2026-01-01 10:00:00');
        $this->ids['payment'] = $this->createEntry($this->customerId, $credit, '250.25', '2026-01-02 10:00:00');
        $this->ids['pending'] = $this->createEntry($this->customerId, $debit, '80.00', '2026-01-03 10:00:00', AccountEntryValidationStatus::PENDING);
        $this->ids['tie_low'] = $this->createEntry($this->customerId, $debit, '10.00', '2026-01-04 10:00:00', AccountEntryValidationStatus::VALIDATED, 500);
        $this->ids['tie_high'] = $this->createEntry($this->customerId, $credit, '4.75', '2026-01-04 10:00:00', AccountEntryValidationStatus::VALIDATED, 501);
        $this->ids['oldest'] = $this->createEntry($this->customerId, $debit, '100.00', '2025-12-01 10:00:00');

        $other = $this->createCustomer('Otro');
        $this->createEntry($other, $debit, '777.00', '2026-01-01 00:00:00');
    }

    private function expectedBalances(): array
    {
        return [
            $this->ids['oldest'] => 100.0,
            $this->ids['charge'] => 1100.0,
            $this->ids['payment'] => 849.75,
            $this->ids['pending'] => null,
            $this->ids['tie_low'] => 859.75,
            $this->ids['tie_high'] => 855.0,
        ];
    }

    private function assertRunningBalances(array $rows): void
    {
        $this->assertNotEmpty($rows);
        $expected = $this->expectedBalances();
        foreach ($rows as $row) {
            $this->assertArrayHasKey('running_balance', $row, 'Cada movimiento del cliente trae running_balance');
            $message = 'running_balance del movimiento '.$row['id'];
            if ($expected[$row['id']] === null) {
                $this->assertNull($row['running_balance'], $message);
            } else {
                // json_decode devuelve 855 (int) para 855.0: se compara el valor numérico.
                $this->assertIsNumeric($row['running_balance'], $message);
                $this->assertEquals($expected[$row['id']], $row['running_balance'], $message);
            }
        }
    }

    public function test_customer_show_includes_running_balance_in_recent_entries(): void
    {
        $response = $this->getJson('/api/v1/customers/'.$this->customerId);

        $response->assertOk();
        $rows = $response->json('data.account_entries.data');
        $this->assertCount(5, $rows);
        // Más reciente primero; empate de fecha resuelto por id desc.
        $this->assertSame(
            [$this->ids['tie_high'], $this->ids['tie_low'], $this->ids['pending'], $this->ids['payment'], $this->ids['charge']],
            array_column($rows, 'id')
        );
        $this->assertRunningBalances($rows);
        $this->assertSame(6, $response->json('data.account_entries.total'));
    }

    public function test_customer_account_entries_list_includes_running_balance(): void
    {
        $response = $this->getJson('/api/v1/customers/'.$this->customerId.'/account-entries?per_page=2&page=2');

        $response->assertOk();
        $rows = $response->json('data');
        // Página 2 de 2 por página: tie_high, tie_low | pending, payment | charge, oldest.
        $this->assertSame([$this->ids['pending'], $this->ids['payment']], array_column($rows, 'id'));
        $this->assertRunningBalances($rows);
    }

    public function test_running_balance_is_a_two_decimal_number_or_null_in_json(): void
    {
        $content = $this->getJson('/api/v1/customers/'.$this->customerId.'/account-entries')->getContent();

        $this->assertStringContainsString('"running_balance":849.75', $content);
        $this->assertStringContainsString('"running_balance":null', $content);
    }

    public function test_global_account_entries_list_does_not_include_running_balance(): void
    {
        $response = $this->getJson('/api/v1/account-entries?customer_id='.$this->customerId);

        $response->assertOk();
        $rows = $response->json('data');
        $this->assertCount(6, $rows);
        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('running_balance', $row);
        }
    }

    public function test_single_account_entry_does_not_include_running_balance(): void
    {
        $response = $this->getJson('/api/v1/account-entries/'.$this->ids['charge']);

        $response->assertOk();
        $this->assertArrayNotHasKey('running_balance', $response->json('data'));
    }
}
