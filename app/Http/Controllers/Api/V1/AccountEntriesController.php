<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\AccountEntrySourceHelper;
use App\Models\AccountEntry;
use App\Models\AccountEntryPaymentMethod;
use App\Models\Enums\AccountEntryValidationStatus;
use App\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountEntryRequest;
use App\Http\Requests\UpdateAccountEntryRequest;
use App\Http\Resources\AccountEntryResource;
use App\Http\Resources\DeliveryResource;
use App\Services\DeliveryService;
use App\Models\Enums\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountEntriesController extends Controller
{
    /**
     * List all account entries with optional filters (customer_id, date_from, date_to, id_zone).
     */
    public function index(Request $request): JsonResponse
    {
        // Solo Cuenta Corriente (admin / administración) consume estos listados globales.
        abort_unless(in_array((int) optional($request->user())->role_id, [1, 4], true), 403);

        $query = AccountEntry::query()
            ->with(['customer:id,name', 'createdByUser:id,name', 'paymentMethods'])
            ->orderByDesc('occurred_at');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->input('customer_id'));
        }
        if ($request->filled('date_from')) {
            $query->where('occurred_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('occurred_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('id_zone')) {
            $query->join('customers', 'customers.id', '=', 'account_entries.customer_id')
                ->join('neighborhoods', 'neighborhoods.id', '=', 'customers.id_neighborhood')
                ->where('neighborhoods.id_zone', (int) $request->input('id_zone'))
                ->select('account_entries.*');
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('account_entries.notes', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('fullname', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        // Tope 100 (máximo que ofrece el front).
        $perPage = min(max((int) ($request->input('per_page') ?? 20), 1), 100);
        $paginator = $query->paginate($perPage);
        AccountEntrySourceHelper::preload($paginator->items());

        return response()->json([
            'data' => AccountEntryResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Grand total debt + debt breakdown by zone (debtor customers only).
     */
    public function balanceSummary(Request $request): JsonResponse
    {
        // Solo Cuenta Corriente (admin / administración) consume estos listados globales.
        abort_unless(in_array((int) optional($request->user())->role_id, [1, 4], true), 403);

        // leftJoin: los deudores sin barrio/zona también suman al total general
        // (antes el inner join los excluía); en el desglose van como "Sin zona".
        $query = DB::table('customers')
            ->leftJoin('neighborhoods', 'neighborhoods.id', '=', 'customers.id_neighborhood')
            ->leftJoin('zones', 'zones.id', '=', 'neighborhoods.id_zone')
            ->whereNull('customers.deleted_at')
            ->where('customers.current_balance', '>', 0);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('customers.fullname', 'like', "%{$search}%")
                  ->orWhere('customers.name', 'like', "%{$search}%");
            });
        }
        if ($request->filled('id_neighborhood')) {
            $query->where('neighborhoods.id', (int) $request->input('id_neighborhood'));
        }
        if ($request->filled('id_zone')) {
            $query->where('zones.id', (int) $request->input('id_zone'));
        }

        $grandTotal = (clone $query)->sum('customers.current_balance');

        $byZone = (clone $query)
            ->select(
                'zones.id as zone_id',
                DB::raw("COALESCE(zones.name, 'Sin zona') as zone_name"),
                DB::raw('COALESCE(SUM(customers.current_balance), 0) as total_debt'),
                DB::raw('COUNT(*) as customers_count')
            )
            ->groupBy('zones.id', 'zones.name')
            ->orderByDesc('total_debt')
            ->get();

        return response()->json([
            'data' => [
                'grand_total' => round((float) $grandTotal, 2),
                'by_zone' => $byZone,
            ],
        ]);
    }

    public function store(StoreAccountEntryRequest $request): JsonResponse
    {
        // Idempotencia: si este usuario ya creó un asiento con la misma clave (reintento
        // tras un timeout), se devuelve ese asiento sin crear nada. La clave manda: aunque
        // el payload difiera, se responde el asiento original (el cliente regenera la clave
        // cuando cambia el cobro). El FormRequest ya hizo el lookup y en ese caso solo
        // validó la clave.
        $existing = $request->replayEntry();
        if ($existing) {
            return $this->storeResponse($request, $existing, true);
        }

        $data = $request->validated();
        $data['created_by_user_id'] = Auth::id();

        $deliveryId = isset($data['delivery_id']) ? (int) $data['delivery_id'] : null;
        $data['source_type'] = $deliveryId ? 'delivery' : 'manual';
        $data['source_id'] = $deliveryId;
        unset($data['delivery_id']);

        $customer = Customer::find((int) $data['customer_id']);
        $data['balance_at_entry'] = $customer ? (float) $customer->current_balance : null;

        if ($deliveryId) {
            $data['validation_status'] = AccountEntryValidationStatus::PENDING;
        } else {
            $data['validation_status'] = Auth::user()->role_id === 1
                ? AccountEntryValidationStatus::VALIDATED
                : AccountEntryValidationStatus::NOT_VALIDATED;
        }

        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        $idempotencyKey = isset($data['idempotency_key']) ? (string) $data['idempotency_key'] : null;
        if ($idempotencyKey === null) {
            unset($data['idempotency_key']);
        }

        try {
            $entry = DB::transaction(function () use ($data, $lines) {
                $entry = AccountEntry::create($data);
                if ($entry->source_type === 'manual') {
                    $entry->update(['source_id' => $entry->id]);
                }
                if ($entry->type === 'payment' && !empty($lines)) {
                    foreach ($lines as $line) {
                        $amount = (float) ($line['amount'] ?? 0);
                        if ($amount <= 0) {
                            continue;
                        }
                        AccountEntryPaymentMethod::create([
                            'account_entry_id' => $entry->id,
                            'payment_method' => $line['method'] ?? PaymentMethod::CASH,
                            'amount' => $amount,
                            'payment_reference' => $line['reference'] ?? null,
                        ]);
                    }
                }
                return $entry->load('paymentMethods');
            });
        } catch (QueryException $e) {
            // Carrera: otra request con la misma clave insertó primero. El INSERT falla
            // antes de observers y payment methods, la transacción ya hizo rollback.
            $isDuplicate = isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062;
            $existing = ($idempotencyKey !== null && $isDuplicate)
                ? $this->findByIdempotencyKey($idempotencyKey)
                : null;
            if (!$existing) {
                throw $e;
            }
            return $this->storeResponse($request, $existing, true);
        }

        return $this->storeResponse($request, $entry, false);
    }

    protected function findByIdempotencyKey(string $key): ?AccountEntry
    {
        return AccountEntry::with('paymentMethods')
            ->where('created_by_user_id', Auth::id())
            ->where('idempotency_key', $key)
            ->first();
    }

    /**
     * Cuerpo de POST /account-entries (creación: 201; replay idempotente: 200 + idempotent_replay).
     */
    protected function storeResponse(Request $request, AccountEntry $entry, bool $replay): JsonResponse
    {
        $deliveryId = $entry->source_type === 'delivery' && $entry->source_id ? (int) $entry->source_id : null;

        // F2-01: además de `data` (sin cambios), `account_entry` con la misma forma que
        // `data.account_entries[]` de GET /deliveries/{id}; y si el cobro es de un reparto
        // que el usuario puede ver, sus `totals` releídos de la DB tras el commit.
        $additional = [
            'account_entry' => (new AccountEntryResource(
                (clone $entry)->load(['customer:id,name', 'createdByUser:id,name'])
            ))->resolve($request),
        ];
        if ($deliveryId) {
            $delivery = app(DeliveryService::class)->loadForOperationResponse($deliveryId);
            if ($delivery && DeliveriesController::canViewDetail(Auth::user(), $delivery)) {
                $additional['totals'] = (new DeliveryResource($delivery))->totals();
            }
        }
        if ($replay) {
            $additional['idempotent_replay'] = true;
        }

        return (new AccountEntryResource($entry))->additional($additional)->response()->setStatusCode($replay ? 200 : 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $entry = AccountEntry::with('paymentMethods')->find($id);
        if (!$entry) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $this->authorize('view', $entry);
        return response()->json(['data' => new AccountEntryResource($entry)]);
    }

    public function update(UpdateAccountEntryRequest $request, AccountEntry $account_entry): JsonResponse
    {
        $data = $request->validated();
        $hasLines = array_key_exists('lines', $data);
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        DB::transaction(function () use ($account_entry, $data, $hasLines, $lines) {
            $account_entry->update($data);
            // Mismo criterio que store: las líneas por método solo aplican a cobros.
            // Si se envían, reemplazan a las existentes para no desincronizar monto vs. métodos.
            if ($hasLines && $account_entry->type === 'payment') {
                $account_entry->paymentMethods()->delete();
                foreach ($lines as $line) {
                    $amount = (float) ($line['amount'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }
                    AccountEntryPaymentMethod::create([
                        'account_entry_id' => $account_entry->id,
                        'payment_method' => $line['method'] ?? PaymentMethod::CASH,
                        'amount' => $amount,
                        'payment_reference' => $line['reference'] ?? null,
                    ]);
                }
            }
        });

        return response()->json(['data' => new AccountEntryResource($account_entry->fresh('paymentMethods'))]);
    }

    public function destroy(Request $request, AccountEntry $account_entry): JsonResponse
    {
        $this->authorize('delete', $account_entry);
        $account_entry->delete();
        return response()->json(null, 204);
    }

    public function validateEntry(Request $request, AccountEntry $account_entry): JsonResponse
    {
        $this->authorize('validate', $account_entry);
        $account_entry->update(['validation_status' => AccountEntryValidationStatus::VALIDATED]);
        return response()->json(['data' => new AccountEntryResource($account_entry->fresh())]);
    }

    public function bulkValidate(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3, 4])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $ids = $request->input('ids', []);
        if (!is_array($ids)) {
            return response()->json(['message' => 'ids must be an array'], 422);
        }
        $ids = array_filter(array_map('intval', $ids));
        $entries = AccountEntry::whereIn('id', $ids)
            ->where('validation_status', AccountEntryValidationStatus::NOT_VALIDATED)
            ->get();
        $count = 0;
        foreach ($entries as $entry) {
            if (!Auth::user()->can('validate', $entry)) {
                continue;
            }
            $entry->update(['validation_status' => AccountEntryValidationStatus::VALIDATED]);
            $count++;
        }
        return response()->json(['validated' => $count]);
    }
}
