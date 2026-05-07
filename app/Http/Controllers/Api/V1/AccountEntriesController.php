<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AccountEntry;
use App\Models\AccountEntryPaymentMethod;
use App\Models\Enums\AccountEntryValidationStatus;
use App\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountEntryRequest;
use App\Http\Requests\UpdateAccountEntryRequest;
use App\Http\Resources\AccountEntryResource;
use App\Models\Enums\PaymentMethod;
use Illuminate\Http\JsonResponse;
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

        $perPage = (int) ($request->input('per_page') ?? 20);
        $paginator = $query->paginate($perPage);

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

    public function store(StoreAccountEntryRequest $request): JsonResponse
    {
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

        return (new AccountEntryResource($entry))->response()->setStatusCode(201);
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
        $account_entry->update($request->validated());
        return response()->json(['data' => new AccountEntryResource($account_entry->fresh())]);
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
