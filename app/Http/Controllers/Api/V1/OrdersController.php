<?php

namespace App\Http\Controllers\Api\V1;

use App\Order;
use App\Order_details;
use App\OrderStatus;
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
            'details.promotion:id,name,type'
        ])->find($id);
        
        if (!$order) {
            return response()->json(['data' => 'Resource not found'], 404);
        }
        
        return (new OrderResource($order))->response()->setStatusCode(200);
    }

    public function update(OrderUpdateRequest $request, Order $order) : JsonResponse
    {
        $validatedData = $request->validated();
        
        $order->update($validatedData);

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

        $order->update([
            'status' => $data['status'],
        ]);

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
        $ids = Order::getDetailsToDelete($order->id);

        Order_details::destroy($ids);

        $order->delete();

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
        $data = $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'integer|exists:orders,id',
            'status' => 'required|in:' . implode(',', OrderStatus::all()),
        ]);

        DB::transaction(function () use ($data) {
            Order::whereIn('id', $data['order_ids'])
                ->update(['status' => $data['status']]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Estados de pedidos actualizados correctamente',
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

        $orders = Order::leftjoin('customers','customers.id','=','orders.id_customer')
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
            ->when($request->has('id_zone'), function ($query) use ($request) {        
                $zone = $request->input('id_zone');
                $query->join('neighborhoods','neighborhoods.id','=','customers.id_neighborhood')
                ->join('zones','zones.id','=','neighborhoods.id_zone')
                ->where('zones.id', '=', "$zone");   
            })
            ->when($request->has('status'), function ($query) use ($request) {
                $status = $request->input('status');
                if (is_array($status)) {
                    $query->whereIn('orders.status', $status);
                } else {
                    $query->where('orders.status', $status);
                }
            })
             ->orderBy(
                'orders.date',
                $request->input('sortType') ?? 'DESC')
                ->orderBy(
                    'orders.id',
                    $request->input('sortType') ?? 'DESC')
            ->select('orders.*', 'customers.name as customer');

        return $orders->paginate($request->input('perPage') ?? 40);
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

    public function print($id) {
        $data = [];

        $order = Order::getByID($id); 
        $order['date'] = Carbon::parse($order['date'])->format('d/m/Y');
        $data['order'] = $order;
        $data['details'] = Order::getDetailsByID($id);
        $pdf = PDF::loadView('templates.factura', $data);
        $filename = 'pedido_'.$order['customer'].'_'.$order['date'].'.pdf';
        
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }
}
