<?php

namespace App\Http\Controllers\Api\V1;

use App\Stock;
use App\Stock_details;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class StockController extends Controller
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

        $values = [];
        $userid = \Auth::id();

        $values['id_user'] = $userid;
        $values['date'] =  Carbon::now()->timezone('America/Argentina/Buenos_Aires');;
        $values['type'] =  $request->input('type', '');
        $stock = Stock::create($values);
        $items = $request->input('items', '');

        $details = [];
        if ($stock) {
            foreach ($items as $item) {
                $details['id_stock'] = $stock->id;
                $details['id_product'] = $item['id_product'];
                $details['quantity'] = $request->input('type') == 'in' ? $item['quantity'] : -abs($item['quantity']);
                if ($request->input('type') == 'in') {
                    $details['id_provider'] = ($item['id_provider']) ? $item['id_provider'] : null;
                    $details['price_purchase'] = ($item['price_purchase']) ? $item['price_purchase'] : null;
                }
            }
            Stock_details::insert($details);
            $response = response()->json($stock, 201);
        } else {
            $response = response()->json(['data' => 'Resource can not be created'], 500);
        }

        return $response;
        
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
    public function update(Request $request, Order $order) : JsonResponse
    {

        $attributes = $request->all();
        
        $order->fill($attributes);
        $order->update();

        return response()->json($order);
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

        $stock = Stock::orderBy(
            $request->input('sortBy') ?? 'id',
            $request->input('sortType') ?? 'DESC'
       );

        return $stock->paginate($request->input('perPage') ?? 40);
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
