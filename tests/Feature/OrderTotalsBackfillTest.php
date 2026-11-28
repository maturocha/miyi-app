<?php

namespace Tests\Feature;

use App\Models\Enums\AccountEntryValidationStatus;
use App\Models\Enums\DeliveryOrderStatus;
use App\Models\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsLedgerSchema;
use Tests\TestCase;

/**
 * total_bruto > 999999.99 (antes DOUBLE(8,2) saturaba), el backfill de
 * total_bruto (`orders:backfill-total-bruto`) y la auditoría de solo lectura
 * de totales (`orders:audit-totals`).
 */
class OrderTotalsBackfillTest extends TestCase
{
    use BuildsLedgerSchema;

    /** @var int */
    private $adminId;

    /** @var int */
    private $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildLedgerSchema();
        $this->buildDeliverySchema();
        $this->adminId = $this->createUser(1);
        $this->customerId = $this->createCustomer();
        DB::table('products')->insert(['id' => 1, 'name' => 'Producto']);
        // El show carga details.product (SoftDeletes) y details.promotion.
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::create('promotions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('type')->nullable();
        });
    }

    /** Corre un comando y devuelve [exit code, salida]. */
    private function runCommand(string $command, array $params = []): array
    {
        $code = Artisan::call($command, $params);

        return [$code, Artisan::output()];
    }

    /**
     * @param float[] $lines price_final de cada línea
     */
    private function createOrder(string $date, float $total, float $totalBruto, array $lines, float $discount = 0, float $deliveryCost = 0): int
    {
        $id = (int) DB::table('orders')->insertGetId([
            'id_customer' => $this->customerId,
            'id_user' => $this->adminId,
            'date' => $date,
            'total' => $total,
            'total_bruto' => $totalBruto,
            'discount' => $discount,
            'delivery_cost' => $deliveryCost,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        foreach ($lines as $price) {
            DB::table('order_details')->insert(['id_order' => $id, 'id_product' => 1, 'price_final' => $price]);
        }

        return $id;
    }

    private function createCharge(int $orderId, string $amount, string $status): void
    {
        DB::table('account_entries')->insert([
            'customer_id' => $this->customerId,
            'type' => 'charge',
            'direction' => 'debit',
            'amount' => $amount,
            'occurred_at' => '2026-01-01 10:00:00',
            'source_type' => 'orders',
            'source_id' => $orderId,
            'validation_status' => $status,
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-01 10:00:00',
        ]);
    }

    private function snapshot(): array
    {
        return [
            'orders' => DB::table('orders')->orderBy('id')->get(['id', 'total', 'updated_at'])->toArray(),
            'account_entries' => DB::table('account_entries')->orderBy('id')->get()->toArray(),
            'customers' => DB::table('customers')->orderBy('id')->get(['id', 'current_balance'])->toArray(),
        ];
    }

    private function totalBruto(int $orderId): float
    {
        return (float) DB::table('orders')->where('id', $orderId)->value('total_bruto');
    }

    public function test_order_over_999999_99_stores_and_returns_total_bruto_as_number(): void
    {
        $orderId = $this->createOrder('2026-11-06', 0, 0, [600000.50, 524556.31]);

        Order::findOrFail($orderId)->recalculateTotals();

        $this->actingAs(User::findOrFail($this->adminId), 'api');
        $response = $this->getJson('/api/v1/orders/'.$orderId)->assertOk();

        $this->assertIsFloat($response->json('data.total_bruto'), 'total_bruto viaja como número, no string');
        $this->assertSame(1124556.81, $response->json('data.total_bruto'));
        $this->assertEquals(1124556.81, $response->json('data.total'));
        $this->assertStringContainsString('"total_bruto":1124556.81', $response->getContent());
    }

    public function test_backfill_fixes_only_total_bruto_and_leaves_totals_and_charges_untouched(): void
    {
        $saturated = $this->createOrder('2026-11-06', 1124556.81, 999999.99, [600000.50, 524556.31]);
        $doubled = $this->createOrder('2025-03-10', 9900, 19800, [9900]);
        $ok = $this->createOrder('2026-02-01', 1000, 1000, [400, 600]);
        $before2025 = $this->createOrder('2024-12-30', 1500000, 999999.99, [1500000]);
        $noLines = $this->createOrder('2026-01-05', 42135, 42135, []);
        $this->createCharge($saturated, '1124556.81', AccountEntryValidationStatus::VALIDATED);
        $this->createCharge($doubled, '9900.00', AccountEntryValidationStatus::PENDING);

        $before = $this->snapshot();

        [$code, $output] = $this->runCommand('orders:backfill-total-bruto', ['--dry-run' => true]);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('[dry-run] Cambiaría 2 pedido(s) desde 2025-01-01 (1 saturado(s) en 999999.99).', $output);
        $this->assertSame(999999.99, $this->totalBruto($saturated), 'dry-run no escribe');

        [$code, $output] = $this->runCommand('orders:backfill-total-bruto');
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Listo: 2 pedido(s) actualizados.', $output);

        $this->assertSame(1124556.81, $this->totalBruto($saturated));
        $this->assertSame(9900.0, $this->totalBruto($doubled));
        $this->assertSame(1000.0, $this->totalBruto($ok));
        $this->assertSame(999999.99, $this->totalBruto($before2025), 'antes de 2025 no se toca');
        $this->assertSame(42135.0, $this->totalBruto($noLines), 'sin líneas no se toca');

        // Ni total, ni updated_at, ni cargos, ni saldos.
        $this->assertEquals($before, $this->snapshot());

        // Idempotente.
        [, $output] = $this->runCommand('orders:backfill-total-bruto', ['--dry-run' => true]);
        $this->assertStringContainsString('[dry-run] Cambiaría 0 pedido(s)', $output);
    }

    public function test_audit_lists_orders_whose_total_does_not_add_up_without_writing(): void
    {
        // líneas 1000, 10% desc, envío 50 => esperado 950; total 1000 => descuadrado.
        $off = $this->createOrder('2025-06-01', 1000, 1000, [1000], 10, 50);
        $ok = $this->createOrder('2025-06-02', 950, 1000, [1000], 10, 50);
        $old = $this->createOrder('2024-06-01', 1000, 1000, [1000], 10, 50);
        $offLocked = $this->createOrder('2026-02-01', 500, 400, [400]);
        $this->createCharge($offLocked, '500.00', AccountEntryValidationStatus::VALIDATED);
        $deliveryId = DB::table('deliveries')->insertGetId([
            'delivery_date' => '2026-02-01', 'status' => DeliveryStatus::CLOSED, 'owner_user_id' => $this->adminId,
        ]);
        DB::table('delivery_orders')->insert([
            'delivery_id' => $deliveryId, 'order_id' => $offLocked, 'delivery_status' => DeliveryOrderStatus::DELIVERED,
        ]);

        $before = $this->snapshot();
        $csv = tempnam(sys_get_temp_dir(), 'audit');

        [$code, $output] = $this->runCommand('orders:audit-totals', ['--output' => $csv]);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('2 pedido(s) desde 2025-01-01 con total descuadrado (> 0.02). Con cargo en cuenta corriente: 1. En reparto cerrado: 1.', $output);

        $rows = array_map('str_getcsv', array_filter(explode("\n", file_get_contents($csv))));
        unlink($csv);
        $header = array_shift($rows);
        $byId = [];
        foreach ($rows as $row) {
            $row = array_combine($header, $row);
            $byId[(int) $row['id']] = $row;
        }

        $this->assertSame([$off, $offLocked], array_keys($byId));
        $this->assertArrayNotHasKey($ok, $byId);
        $this->assertArrayNotHasKey($old, $byId);
        $this->assertEquals(950, $byId[$off]['total_esperado']);
        $this->assertEquals(50, $byId[$off]['diferencia']);
        $this->assertSame('0', $byId[$off]['cargos_cc']);
        $this->assertSame('no', $byId[$off]['entregado_reparto_cerrado']);
        $this->assertSame('1', $byId[$offLocked]['cargos_cc']);
        $this->assertSame(AccountEntryValidationStatus::VALIDATED, $byId[$offLocked]['estado_cargos']);
        $this->assertSame('si', $byId[$offLocked]['entregado_reparto_cerrado']);

        $this->assertEquals($before, $this->snapshot(), 'la auditoría es de solo lectura');
    }
}
