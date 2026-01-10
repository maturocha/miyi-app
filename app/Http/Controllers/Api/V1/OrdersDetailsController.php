<?php

namespace App\Http\Controllers\Api\V1;

use App\Order;
use App\Order_details;
use App\Promotion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\OrderDetailsStoreRequest;
use App\Http\Requests\OrderDetailsUpdateRequest;
use App\Http\Resources\OrderDetailsResource;

class OrdersDetailsController extends Controller
{
    /**
     * List all resource.
     *
     * @param Illuminate\Http\Request $request
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) : JsonResponse
    {
        return response()->json($this->paginatedQuery($request));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param App\Http\Requests\OrderDetailsStoreRequest $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(OrderDetailsStoreRequest $request) : JsonResponse
    {
        $data = $request->validated();
        
        $orderDetail = Order_details::create($data);

        // Actualizar el total de la orden
        $this->updateOrderTotal($orderDetail->id_order);

        // Cargar relaciones si se solicita
        if ($request->has('with_promotion') && $request->with_promotion == '1') {
            $orderDetail->load('promotion:id,name,type');
        }

        return response()->json(new OrderDetailsResource($orderDetail), 201);
    }

    /**
     * Show a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Order_details $orderDetail
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Order_details $orderDetail) : JsonResponse
    {
        // Cargar relaciones si se solicita
        if ($request->has('with_promotion') && $request->with_promotion == '1') {
            $orderDetail->load('promotion:id,name,type');
        }

        return response()->json(new OrderDetailsResource($orderDetail));
    }

    /**
     * Update a resource.
     *
     * @param App\Http\Requests\OrderDetailsUpdateRequest $request
     * @param App\Order_details $detail
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function update(OrderDetailsUpdateRequest $request, Order_details $detail) : JsonResponse
    {
        $data = $request->validated();
        
        // Actualizar el detalle con los datos validados
        $detail->fill($data);
        $detail->update();

        // Actualizar el total de la orden
        $this->updateOrderTotal($detail->id_order);

        // Cargar relaciones si se solicita
        if ($request->has('with_promotion') && $request->with_promotion == '1') {
            $detail->load('promotion:id,name,type');
        }

        return response()->json(new OrderDetailsResource($detail));
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Order_details $detail
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Order_details $detail) : JsonResponse
    {
        try {
            $orderId = $detail->id_order;
            $detail->delete();

            // Actualizar el total de la orden después de eliminar el detalle
            $this->updateOrderTotal($orderId);
            
            return response()->json([
                'success' => true,
                'message' => 'Detalle del pedido eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el detalle del pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the paginated resource query.
     *
     * @param Illuminate\Http\Request $request
     *
     * @return Illuminate\Pagination\LengthAwarePaginator
     */
    protected function paginatedQuery(Request $request) : LengthAwarePaginator
    {
        $orderDetails = Order_details::orderBy(
            $request->input('sortBy') ?? 'created_at',
            $request->input('sortType') ?? 'DESC'
        )
        ->when($request->has('search'), function ($query) use ($request) {
            $search = $request->input('search');
            return $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%$search%");
            });
        })
        ->when($request->has('id_order'), function ($query) use ($request) {
            return $query->where('id_order', $request->id_order);
        })
        ->when($request->has('id_product'), function ($query) use ($request) {
            return $query->where('id_product', $request->id_product);
        })
        ->when($request->has('promotion_id'), function ($query) use ($request) {
            return $query->where('promotion_id', $request->promotion_id);
        })
        ->when($request->has('with_promotion') && $request->with_promotion == '1', function ($query) {
            return $query->with('promotion:id,name,type');
        })
        ->with('product:id,name,code_miyi');

        return $orderDetails->paginate($request->input('perPage') ?? 40);
    }

    /**
     * Calculate and update the order total based on its details.
     *
     * @param int $orderId
     * @return void
     */
    protected function updateOrderTotal(int $orderId): void
    {
        $order = Order::find($orderId);
        
        if (!$order) {
            return;
        }

        // Calcular el total bruto sumando todos los price_final de los detalles
        $totalBruto = $order->details()->sum('price_final');
        
        // Obtener el costo de entrega y el descuento de la orden
        $deliveryCost = $order->delivery_cost ?? 0;
        $discountPercentage = $order->discount ?? 0;
        
        // Calcular el descuento en monto
        $discountAmount = ($totalBruto * $discountPercentage) / 100;
        
        // Calcular el total final
        $total = $totalBruto + $deliveryCost - $discountAmount;
        
        // Actualizar la orden con los totales calculados
        $order->update([
            'total_bruto' => round($totalBruto, 2),
            'total' => round($total, 2)
        ]);
    }
}
