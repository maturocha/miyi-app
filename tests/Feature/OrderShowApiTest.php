<?php

namespace Tests\Feature;

use App\Models\Enums\DeliveryOrderStatus;
use App\Models\Enums\DeliveryStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLedgerSchema;
use Tests\TestCase;

/**
 * GET /api/v1/orders/{id}: campos aditivos `created_at`, cliente ampliado
 * (dirección, celular, barrio, zona) y `delivery` (reparto principal del pedido,
 * recortado si el usuario no puede ver el detalle de ese reparto).
 */
class OrderShowApiTest extends TestCase
{
    use BuildsLedgerSchema;

    /** @var int */
    private $adminId;

    /** @var int */
    private $customerId;

    /** @var int */
    private $orderId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildLedgerSchema();
        $this->buildDeliverySchema();

        $this->adminId = $this->createUser(1);

        $zoneId = DB::table('zones')->insertGetId(['name' => 'Zona Norte']);
        $neighborhoodId = DB::table('neighborhoods')->insertGetId(['name' => 'Centro', 'id_zone' => $zoneId]);
        $this->customerId = DB::table('customers')->insertGetId([
            'name' => 'Almacén Don Pepe',
            'address' => 'San Martín 123',
            'cellphone' => '3511234567',
            'type' => 'minorista',
            'id_neighborhood' => $neighborhoodId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->orderId = DB::table('orders')->insertGetId([
            'id_customer' => $this->customerId,
            'id_user' => $this->adminId,
            'date' => '2026-11-20',
            'total' => 1500,
            'created_at' => '2026-11-20 09:30:00',
            'updated_at' => '2026-11-20 09:30:00',
        ]);
    }

    private function createDelivery(string $date, int $ownerId, string $status = DeliveryStatus::FINISHED): int
    {
        return (int) DB::table('deliveries')->insertGetId([
            'delivery_date' => $date,
            'status' => $status,
            'owner_user_id' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attach(int $deliveryId, string $status, ?string $deliveredAt = null, ?string $failureReason = null): void
    {
        DB::table('delivery_orders')->insert([
            'delivery_id' => $deliveryId,
            'order_id' => $this->orderId,
            'delivery_status' => $status,
            'delivered_at' => $deliveredAt,
            'failure_reason' => $failureReason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function show(?int $userId = null)
    {
        $this->actingAs(User::findOrFail($userId ?? $this->adminId), 'api');

        return $this->getJson('/api/v1/orders/'.$this->orderId)->assertOk();
    }

    public function test_keeps_existing_fields_and_adds_created_at_and_full_customer(): void
    {
        $data = $this->show()->json('data');

        foreach (['id', 'date', 'total', 'discount', 'notes', 'total_bruto', 'delivery_cost', 'payment_method',
            'status', 'user', 'customer', 'details', 'is_locked', 'was_delivered', 'created_at', 'delivery'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }

        $this->assertSame($this->orderId, $data['id']);
        $this->assertSame('Admin 1', $data['user']);
        $this->assertSame('in_process', $data['status']);
        $this->assertFalse($data['is_locked']);
        $this->assertFalse($data['was_delivered']);
        $this->assertSame('2026-11-20T09:30:00+00:00', $data['created_at']);

        $this->assertSame([
            'id' => $this->customerId,
            'name' => 'Almacén Don Pepe',
            'type' => 'minorista',
            'address' => 'San Martín 123',
            'cellphone' => '3511234567',
            'neighborhood' => 'Centro',
            'zone' => 'Zona Norte',
        ], $data['customer']);
    }

    public function test_customer_without_neighborhood_has_null_location(): void
    {
        DB::table('customers')->where('id', $this->customerId)->update(['id_neighborhood' => null]);

        $customer = $this->show()->json('data.customer');

        $this->assertSame('San Martín 123', $customer['address']);
        $this->assertNull($customer['neighborhood']);
        $this->assertNull($customer['zone']);
    }

    public function test_order_without_delivery_has_null_delivery(): void
    {
        $data = $this->show()->json('data');

        $this->assertArrayHasKey('delivery', $data);
        $this->assertNull($data['delivery']);
    }

    public function test_delivered_order_exposes_its_delivery(): void
    {
        $ownerId = $this->createUser(2);
        $deliveryId = $this->createDelivery('2026-11-21', $ownerId, DeliveryStatus::CLOSED);
        $this->attach($deliveryId, DeliveryOrderStatus::DELIVERED, '2026-11-21 11:15:00');

        $data = $this->show()->json('data');

        $this->assertSame([
            'id' => $deliveryId,
            'delivery_date' => '2026-11-21',
            'status' => DeliveryStatus::CLOSED,
            'owner' => ['id' => $ownerId, 'name' => 'Admin 2'],
            'delivery_status' => DeliveryOrderStatus::DELIVERED,
            'delivered_at' => '2026-11-21T11:15:00+00:00',
            'failure_reason' => null,
        ], $data['delivery']);
        $this->assertTrue($data['was_delivered']);
        $this->assertTrue($data['is_locked']);
    }

    public function test_with_several_deliveries_prefers_the_one_where_it_was_delivered(): void
    {
        $ownerId = $this->createUser(2);
        $failed = $this->createDelivery('2026-11-21', $ownerId);
        $delivered = $this->createDelivery('2026-11-22', $ownerId);
        $later = $this->createDelivery('2026-11-25', $ownerId, DeliveryStatus::NOT_STARTED);
        $this->attach($failed, DeliveryOrderStatus::FAILED, null, 'Cerrado');
        $this->attach($delivered, DeliveryOrderStatus::DELIVERED, '2026-11-22 10:00:00');
        $this->attach($later, DeliveryOrderStatus::UNTOUCHED);

        $delivery = $this->show()->json('data.delivery');

        $this->assertSame($delivered, $delivery['id']);
        $this->assertSame(DeliveryOrderStatus::DELIVERED, $delivery['delivery_status']);
    }

    public function test_with_several_deliveries_and_none_delivered_uses_the_most_recent(): void
    {
        $ownerId = $this->createUser(2);
        $older = $this->createDelivery('2026-11-21', $ownerId);
        $newer = $this->createDelivery('2026-11-23', $ownerId);
        // Creado último (id mayor) pero con fecha anterior: manda la fecha.
        $createdLast = $this->createDelivery('2026-11-22', $ownerId);
        $this->attach($newer, DeliveryOrderStatus::FAILED, null, 'No atendió');
        $this->attach($older, DeliveryOrderStatus::SKIPPED);
        $this->attach($createdLast, DeliveryOrderStatus::FAILED, null, 'Otro');

        $delivery = $this->show()->json('data.delivery');

        $this->assertSame($newer, $delivery['id']);
        $this->assertSame('2026-11-23', $delivery['delivery_date']);
        $this->assertSame(DeliveryOrderStatus::FAILED, $delivery['delivery_status']);
        $this->assertSame('No atendió', $delivery['failure_reason']);
        $this->assertNull($delivery['delivered_at']);
    }

    public function test_same_date_ties_are_broken_by_highest_id(): void
    {
        $ownerId = $this->createUser(2);
        $first = $this->createDelivery('2026-11-23', $ownerId);
        $second = $this->createDelivery('2026-11-23', $ownerId);
        $this->attach($second, DeliveryOrderStatus::SKIPPED);
        $this->attach($first, DeliveryOrderStatus::FAILED);

        $this->assertSame($second, $this->show()->json('data.delivery.id'));
    }

    public function test_user_who_cannot_view_the_delivery_gets_only_summary(): void
    {
        $ownerId = $this->createUser(2);
        $otherSeller = $this->createUser(2);
        $deliveryId = $this->createDelivery('2026-11-21', $ownerId);
        $this->attach($deliveryId, DeliveryOrderStatus::DELIVERED, '2026-11-21 11:15:00');

        $delivery = $this->show($otherSeller)->json('data.delivery');

        $this->assertSame([
            'id' => $deliveryId,
            'delivery_date' => '2026-11-21',
            'status' => DeliveryStatus::FINISHED,
        ], $delivery);
    }

    public function test_delivery_owner_without_admin_role_gets_full_detail(): void
    {
        $ownerId = $this->createUser(2);
        $deliveryId = $this->createDelivery('2026-11-21', $ownerId);
        $this->attach($deliveryId, DeliveryOrderStatus::DELIVERED, '2026-11-21 11:15:00');

        $delivery = $this->show($ownerId)->json('data.delivery');

        $this->assertSame($deliveryId, $delivery['id']);
        $this->assertSame(['id' => $ownerId, 'name' => 'Admin 2'], $delivery['owner']);
        $this->assertSame(DeliveryOrderStatus::DELIVERED, $delivery['delivery_status']);
    }

    public function test_query_count_does_not_grow_with_number_of_deliveries(): void
    {
        $ownerA = $this->createUser(2);
        $this->attach($this->createDelivery('2026-11-21', $ownerA), DeliveryOrderStatus::FAILED);
        $countWithOne = $this->countShowQueries();

        foreach (['2026-11-22', '2026-11-23', '2026-11-24'] as $date) {
            $this->attach($this->createDelivery($date, $this->createUser(2)), DeliveryOrderStatus::SKIPPED);
        }

        $this->assertSame($countWithOne, $this->countShowQueries());
    }

    private function countShowQueries(): int
    {
        $this->actingAs(User::findOrFail($this->adminId), 'api');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/orders/'.$this->orderId)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
