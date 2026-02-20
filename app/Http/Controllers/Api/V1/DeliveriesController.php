<?php

namespace App\Http\Controllers\Api\V1;

use App\Delivery;
use App\DeliveryStatus;
use App\Order;
use App\Services\DeliveryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryStoreRequest;
use App\Http\Requests\DeliveryUpdateRequest;
use App\Http\Requests\DeliveryAddOrderRequest;
use App\Http\Requests\DeliveryOrderUpdateRequest;
use App\Http\Resources\DeliveryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class DeliveriesController extends Controller
{
    protected $deliveryService;

    public function __construct(DeliveryService $deliveryService)
    {
        $this->deliveryService = $deliveryService;
    }

    /**
     * Display a listing of deliveries.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->paginatedQuery($request));
    }

    /**
     * Store a newly created delivery.
     *
     * @param DeliveryStoreRequest $request
     * @return JsonResponse
     */
    public function store(DeliveryStoreRequest $request): JsonResponse
    {
        // Check authorization: only admin (role_id = 1) or administración (role_id = 3) can create
        $this->authorize('create', Delivery::class);
        
        $delivery = $this->deliveryService->createDelivery($request->validated());

        return (new DeliveryResource($delivery))->response()->setStatusCode(201);
    }

    /**
     * Display the specified delivery.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, $id): JsonResponse
    {
        $delivery = Delivery::with([
            'owner:id,name',
            'deliveryOrders.order.customer:id,name,address,cellphone',
            'deliveryOrders.order.customer.neighborhood:id,name',
        ])->find($id);

        if (!$delivery) {
            return response()->json(['data' => 'Resource not found'], 404);
        }

        // Check permissions: repartidores solo pueden ver si son owner
        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3]) && $delivery->owner_user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return (new DeliveryResource($delivery))->response()->setStatusCode(200);
    }

    /**
     * Get loading list (cargas) for the delivery: products and quantities by category.
     *
     * @param Delivery $delivery
     * @return JsonResponse
     */
    public function cargas(Delivery $delivery): JsonResponse
    {
        if ($delivery->status === DeliveryStatus::CLOSED) {
            return response()->json(['message' => 'Reparto cerrado'], 404);
        }

        $user = Auth::user();
        if (!in_array($user->role_id, [1, 3]) && $delivery->owner_user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $details = Order::getProductsByDelivery($delivery->id);
        $detailsArray = $details->map(function ($row) {
            return [
                'zone' => $row->zone,
                'category' => $row->category,
                'name' => $row->name,
                'cant' => (int) $row->cant,
            ];
        })->values()->toArray();

        return response()->json([
            'data' => [
                'delivery_date' => $delivery->delivery_date ? $delivery->delivery_date->format('Y-m-d') : '',
                'details' => $detailsArray,
            ],
        ]);
    }

    /**
     * Update the specified delivery.
     *
     * @param DeliveryUpdateRequest $request
     * @param Delivery $delivery
     * @return JsonResponse
     */
    public function update(DeliveryUpdateRequest $request, Delivery $delivery): JsonResponse
    {
        // Check authorization
        $this->authorize('update', $delivery);
        
        $validated = $request->validated();
        
        // Don't allow updating status directly through update endpoint
        unset($validated['status']);
        
        $delivery->update($validated);

        return (new DeliveryResource($delivery->fresh()))->response()->setStatusCode(200);
    }

    /**
     * Remove the specified delivery.
     *
     * @param Request $request
     * @param Delivery $delivery
     * @return JsonResponse
     */
    public function destroy(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorize('delete', $delivery);
        
        $delivery->delete();

        return response()->json($this->paginatedQuery($request));
    }

    /**
     * Add pending orders from the zone to the delivery.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function addPendingOrders(Request $request, $id): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);
        $this->authorize('addOrders', $delivery);

        $zoneId = (int) $request->input('zone_id');
        $date = $request->input('date', $delivery->delivery_date ? $delivery->delivery_date->format('Y-m-d') : null);
        if (!$zoneId || !$date) {
            return response()->json(['message' => 'zone_id y date son requeridos'], 422);
        }

        $added = $this->deliveryService->addPendingOrders($delivery, $zoneId, $date);

        return response()->json([
            'success' => true,
            'message' => "Se agregaron {$added} pedidos pendientes.",
            'added' => $added,
        ]);
    }

    /**
     * Add a single order to the delivery.
     *
     * @param DeliveryAddOrderRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function addOrder(DeliveryAddOrderRequest $request, $id): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);
        $validated = $request->validated();
        $order = Order::findOrFail($validated['order_id']);

        try {
            $this->deliveryService->addOrder(
                $delivery,
                $order,
                $validated['override'] ?? false
            );

            return response()->json([
                'success' => true,
                'message' => 'Pedido agregado al reparto correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update a delivery order (pivot).
     *
     * @param DeliveryOrderUpdateRequest $request
     * @param int $id
     * @param int $orderId
     * @return JsonResponse
     */
    public function updateOrder(DeliveryOrderUpdateRequest $request, $id, $orderId): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);
        $order = Order::findOrFail($orderId);

        // Verify order belongs to delivery
        if (!$delivery->orders()->where('orders.id', $orderId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'El pedido no pertenece a este reparto.',
            ], 404);
        }

        $this->deliveryService->updateDeliveryOrder($delivery, $order, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Pedido actualizado correctamente.',
        ]);
    }

    /**
     * Update delivery expenses.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateExpenses(Request $request, $id): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);
        $this->authorize('updateExpenses', $delivery);

        $request->validate([
            'expenses_amount' => 'required|numeric|min:0|max:999999.99',
            'expenses_notes' => 'nullable|string|max:2000',
        ]);

        $this->deliveryService->updateExpenses(
            $delivery,
            $request->input('expenses_amount'),
            $request->input('expenses_notes')
        );

        return response()->json([
            'success' => true,
            'message' => 'Gastos actualizados correctamente.',
        ]);
    }

    /**
     * Start the delivery.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function start($id): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);
        $this->authorize('start', $delivery);

        try {
            $this->deliveryService->startDelivery($delivery);

            return response()->json([
                'success' => true,
                'message' => 'Reparto iniciado correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Finish the delivery.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function finish($id): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);
        $this->authorize('finish', $delivery);

        try {
            $this->deliveryService->finishDelivery($delivery);

            return response()->json([
                'success' => true,
                'message' => 'Reparto finalizado correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Close the delivery.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function close($id): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);
        $this->authorize('close', $delivery);

        try {
            $this->deliveryService->closeDelivery($delivery);

            return response()->json([
                'success' => true,
                'message' => 'Reparto cerrado correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get the paginated resource query.
     *
     * @param Request $request
     * @return LengthAwarePaginator
     */
    protected function paginatedQuery(Request $request): LengthAwarePaginator
    {
        $user = Auth::user();

        $deliveries = Delivery::with(['owner:id,name'])
            ->withCount('orders')
            ->when(!in_array($user->role_id, [1, 4]), function ($query) use ($user) {
                // Repartidores solo ven sus propios repartos
                $query->where('owner_user_id', $user->id);
            })
            ->when($request->has('date_from'), function ($query) use ($request) {
                $query->whereDate('delivery_date', '>=', $request->input('date_from'));
            })
            ->when($request->has('date_to'), function ($query) use ($request) {
                $query->whereDate('delivery_date', '<=', $request->input('date_to'));
            })
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->has('owner_user_id'), function ($query) use ($request) {
                $query->where('owner_user_id', $request->input('owner_user_id'));
            })
            ->when($request->has('search'), function ($query) use ($request) {
                $search = trim($request->input('search'));
                if ($search != '') {
                    $query->where('id', $search);
                }
            })
            ->orderBy('delivery_date', $request->input('sortType') ?? 'DESC')
            ->orderBy('id', $request->input('sortType') ?? 'DESC');

        return $deliveries->paginate($request->input('perPage') ?? 40);
    }
}
