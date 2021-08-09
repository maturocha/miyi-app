<?php

namespace App\Http\Controllers\Api\V1;

use App\Order_details;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

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
     * Store a new resource.
     *
     * @param Illuminate\Http\Request $request
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) : JsonResponse
    {

        $order = Order_details::create([
            'id_order' => $request->input('id_order'),
            'id_product' => $request->input('id_product'), 
            'quantity' => $request->input('quantity'), 
            'discount' => ($request->input('discount', '')) ? $request->input('discount', '') : 0, 
            'price_unit' => $request->input('price_unit'), 
            'price_final' => $request->input('price_final'),
        ]);

        return response()->json($order, 201);
        
    }

    /**
     * Show a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Order $order
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) : JsonResponse

    {

        $order = Order::getByID($id);
        if ($order) {
            $order['details'] = Order::getDetailsByID($id);
            $response['data'] = $order;
            $response = response()->json($response, 200);
        } else {
            $response = response()->json(['data' => 'Resource not found'], 404);
        }
        
        return $response;

    }

    /**
     * Update a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Order $order
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Order_Details $detail) : JsonResponse
    {

        $attributes = [
            'price_final' => $request->input('price_final'),
            'weight' => $request->input('weight'),
        ];
         
        $detail->fill($attributes);
        $detail->update();

        return response()->json($detail);
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Order $order
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id) : JsonResponse
    {
        
        $detail = Order_details::destroy($id);
        return response()->json($detail);
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
        $userid = \Auth::id();

        $orders = Order::leftjoin('customers','customers.id','=','orders.id_customer')
            ->where('id_user', '=', $userid)
            ->orderBy(
             $request->input('sortBy') ?? 'created_at',
             $request->input('sortType') ?? 'DESC'
        )->select('orders.*', 'customers.name as customer');

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
}
