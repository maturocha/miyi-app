<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Order;
use App\Models\Order_details;
use App\Models\DeliveryOrder;
use App\Models\Delivery;
use App\Models\AccountEntry;
use App\Models\Enums\OrderStatus;
use App\Models\Enums\DeliveryOrderStatus;
use App\Models\Enums\DeliveryStatus;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use PDF;
use App\Http\Requests\OrderUpdateRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\DB;

class OrdersController extends Controller
{
    public function index(Request $request) : JsonResponse
    {
        return response()->json($this->paginatedQuery($request));
    }

    public function store(Request $request) : JsonResponse
    {
        $userid = \Auth::id();
        $today = Carbon::now()->timezone('America/Argentina/Buenos_Aires');

        $order = Order::create([
            'id_user' => $userid,
            'id_customer' => $request->id_customer,
            'date' => $today->format('Y-m-d H:i:s')
        ]);

        if ($order) {
            $response = response()->json($order, 201);
        } else {
            $response = response()->json(['data' => 'Resource can not be created'], 500);
        }

        return $response;
    }

    public function show(Request $request, $id) : JsonResponse
    {
        $order = Order::with([
            'user:id,name',
            'customer:id,name,address,time_visit,cellphone',
            'customer.neighborhood:id,name',
            'customer.neighborhood.zone:id,name',
            'details.promotion:id,name,type',
            'details.product'
        ])->find($id);
        
        if (!$order) {
            return response()->json(['data' => 'Resource not found'], 404);
        }
        
        return (new OrderResource($order))->response()->setStatusCode(200);
    }

    public function update(OrderUpdateRequest $request, Order $order) : JsonResponse
    {
        $validatedData = $request->validated();

        try {
            DB::transaction(function () use ($order, $validatedData) {
                $locked = Order::lockForEdit($order->id);
                $locked->update($validatedData);
                // Recalcular siempre con el lock tomado: el total que arma
                // OrderUpdateRequest se calcula antes del lock y una edición de
                // líneas concurrente lo dejaría viejo. También sincroniza el cargo.
                $locked->recalculateTotals();
            });
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Orden actualizada exitosamente',
            'data' => $order->fresh()
        ]);
    }

    /**
     * Actualizar sólo el estado de un pedido.
     *
     * @param Request $request
     * @param Order   $order
     * @return JsonResponse
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', OrderStatus::all()),
        ]);

        try {
            DB::transaction(function () use ($order, $data) {
                Order::lockForEdit($order->id)->update([
                    'status' => $data['status'],
                ]);
            });
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Estado del pedido actualizado correctamente',
            'data' => $order->fresh(),
        ]);
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Order $order
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Order $order) : JsonResponse
    {
        abort_unless(in_array((int) optional($request->user())->role_id, [1, 2, 4], true), 403);

        // Regla: se borra solo si NO fue entregado. Si tenía un cargo (pendiente
        // o, por datos viejos, validado) se borra también: el observer revierte
        // el saldo si estaba validado. No se borra si tiene cobros registrados
        // (plata recibida en un reparto). Todo con el pedido bloqueado: el cierre
        // de reparto también lo bloquea al generar el cargo.
        $blockedMessage = DB::transaction(function () use ($order) {
            // Mismo orden de locks que el flujo de repartos (reparto → pedido)
            // para no generar deadlocks con updateDeliveryOrder / cierre.
            $deliveryIds = DeliveryOrder::where('order_id', $order->id)->pluck('delivery_id')->all();
            if (!empty($deliveryIds)) {
                Delivery::whereIn('id', $deliveryIds)->orderBy('id')->lockForUpdate()->get();
            }
            Order::whereKey($order->id)->lockForUpdate()->first();

            if ($order->wasDelivered()) {
                return 'No se puede eliminar un pedido entregado.';
            }

            $deliveryOrderIds = DeliveryOrder::where('order_id', $order->id)->pluck('id');
            $hasCollections = DeliveryOrder::where('order_id', $order->id)->where('collected_amount', '>', 0)->exists()
                || AccountEntry::where('source_type', 'delivery_orders')->whereIn('source_id', $deliveryOrderIds)->exists();
            if ($hasCollections) {
                return 'No se puede eliminar: el pedido tiene cobros registrados en un reparto.';
            }

            foreach (AccountEntry::where('source_type', 'orders')->where('source_id', $order->id)->get() as $entry) {
                $entry->delete();
            }

            $ids = Order::getDetailsToDelete($order->id);
            Order_details::destroy($ids);
            // delivery_orders (y sus pagos) se borran en cascada: el pedido sale
            // de cualquier reparto donde no se haya entregado.
            $order->delete();

            return null;
        });

        if ($blockedMessage !== null) {
            return response()->json([
                'success' => false,
                'message' => $blockedMessage,
            ], 422);
        }

        return response()->json($this->paginatedQuery($request));
    }

    /**
     * Actualizar el estado de múltiples pedidos en bloque.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        abort_unless(in_array((int) optional($request->user())->role_id, [1, 2, 4], true), 403);

        $data = $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'integer|exists:orders,id',
            'status' => 'required|in:' . implode(',', OrderStatus::all()),
        ]);

        $skipped = [];
        DB::transaction(function () use ($data, &$skipped) {
            $orders = Order::whereIn('id', $data['order_ids'])->lockForUpdate()->get();
            $editableIds = [];
            foreach ($orders as $order) {
                // Entregados en reparto cerrado: no se tocan (ver Order::lockForEdit).
                if ($order->isLockedByClosedDelivery()) {
                    $skipped[] = $order->id;
                } else {
                    $editableIds[] = $order->id;
                }
            }
            if (!empty($editableIds)) {
                Order::whereIn('id', $editableIds)->update(['status' => $data['status']]);
            }
        });

        $message = 'Estados de pedidos actualizados correctamente';
        if (!empty($skipped)) {
            $message = count($skipped) . ' pedido(s) no se modificaron porque fueron entregados en un reparto cerrado: #'
                . implode(', #', $skipped);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'skipped_ids' => $skipped,
        ]);
    }

    /**
     * Restore a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param string $id
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $order = Order::withTrashed()->where('id', $id)->first();
        $order->deleted_at = null;
        $order->update();

        return response()->json($this->paginatedQuery($request));
    }


    /**
     * Get the paginated resource query.
     *
     * @param Illuminate\Http\Request
     *
     * @return Illuminate\Pagination\LengthAwarePaginator
     */
    protected function paginatedQuery(Request $request) : LengthAwarePaginator
    {
        $user = Auth::user();

        $orders = Order::leftJoin('customers','customers.id','=','orders.id_customer')
            ->leftJoin('neighborhoods','neighborhoods.id','=','customers.id_neighborhood')
            ->leftJoin('zones','zones.id','=','neighborhoods.id_zone')
            ->when(($user->role_id <> 1 && $user->role_id <> 3), function ($query) use ($user) {
                    $query->where('id_user', '=', $user->id);
            })
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $search = trim($search);
                if ($search != '') {
                    $query->where(function ($query) use ($search) {
                        $query->where('orders.id', '=', "$search");
                    });
                }
            })
            // `filled()` (no `has()`): al limpiar el filtro en el frontend se
            // manda `id_zone=`/`status=` (string vacío), que `has()` sigue
            // viendo como "presente" -> `WHERE zones.id = ''`/`WHERE
            // orders.status = ''`, que no matchea nada y deja la lista vacía.
            ->when($request->filled('id_zone'), function ($query) use ($request) {
                $zone = $request->input('id_zone');
                $query->where('zones.id', '=', "$zone");
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = $request->input('status');
                if (is_array($status)) {
                    $query->whereIn('orders.status', $status);
                } else {
                    $query->where('orders.status', $status);
                }
            })
             ->orderBy(
                'orders.date',
                $this->sortDirection($request, 'DESC'))
                ->orderBy(
                    'orders.id',
                    $this->sortDirection($request, 'DESC'))
            ->select(
                'orders.*',
                'customers.name as customer',
                'customers.address as customer_address',
                'zones.name as zone_name'
            )
            // Flags para el front (ver Order::isLockedByClosedDelivery / wasDelivered):
            // ocultar Editar/Eliminar según la regla de reparto.
            ->selectRaw(
                "EXISTS (SELECT 1 FROM delivery_orders dl JOIN deliveries dd ON dd.id = dl.delivery_id"
                . " WHERE dl.order_id = orders.id AND dl.delivery_status = ? AND dd.status = ?) as is_locked",
                [DeliveryOrderStatus::DELIVERED, DeliveryStatus::CLOSED]
            )
            ->selectRaw(
                "EXISTS (SELECT 1 FROM delivery_orders dw WHERE dw.order_id = orders.id AND dw.delivery_status = ?) as was_delivered",
                [DeliveryOrderStatus::DELIVERED]
            );

        return $orders->paginate($this->perPage($request, 40, 1000));
    }

    /**
     * Filter a specific column property
     *
     * @param mixed $orders
     * @param string $property
     * @param array $filters
     *
     * @return void
     */
    protected function filter($orders, string $property, array $filters)
    {
        foreach ($filters as $keyword => $value) {
            // Needed since LIKE statements requires values to be wrapped by %
            if (in_array($keyword, ['like', 'nlike'])) {
                $orders->where(
                    $property,
                    _to_sql_operator($keyword),
                    "%{$value}%"
                );

                return;
            }

            $orders->where($property, _to_sql_operator($keyword), "{$value}");
        }
    }

    /**
     * Imprime el comprobante de un pedido.
     * Puede recibir un id numérico o un modelo Order.
     *
     * @param mixed $orderOrId
     * @return \Illuminate\Http\Response
     */
    public function print(Order $order)
    {
        // Eager-load everything the view needs (avoid N+1 / lazy-loads)
        $order->loadMissing([
            'user:id,name',
            'customer:id,name,address,time_visit,cellphone,id_neighborhood,current_balance',
            'customer.neighborhood:id,name,id_zone',
            'customer.neighborhood.zone:id,name',
            'details.product:id,name,type_product',
        ]);

        $orderDate = Carbon::parse($order->date)->format('d/m/Y');

        // Build only the keys the Blade template uses (keeps compatibility with $order['...'])
        $orderArr = [
            'id' => $order->id,
            'date' => $orderDate,
            'customer' => $order->customer->name ?? null,
            'time_visit' => $order->customer->time_visit ?? null,
            'address' => $order->customer->address ?? null,
            'neighborhood' => \optional($order->customer->neighborhood)->name,
            'zone' => \optional(\optional($order->customer->neighborhood)->zone)->name,
            'cellphone' => $order->customer->cellphone ?? null,
            'name' => \optional($order->user)->name,
            'total_bruto' => $order->total_bruto,
            'discount' => $order->discount,
            'delivery_cost' => $order->delivery_cost,
            'total' => $order->total,
            'notes' => $order->notes ?? '',
        ];

        $data = [
            'order' => $orderArr,
            'details' => $order->details,
            'balance' => $order->customer->current_balance ?? 0,
            'customer' => $order->customer ?? null,
        ];

        $pdf = PDF::loadView('templates.factura', $data);

        $customerForFilename = $orderArr['customer'] ?: 'cliente';
        $safeCustomer = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $customerForFilename);
        $filename = 'pedido_' . $safeCustomer . '_' . $orderArr['date'] . '.pdf';

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }
}
