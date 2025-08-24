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
use App\Http\Requests\StockFormRequest;

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
    public function store(StockFormRequest $request) : JsonResponse
    {
        $userid = \Auth::id();
        $data = $request->validated();
        $values = [
            'id_user' => $userid,
            'date' => Carbon::now()->timezone('America/Argentina/Buenos_Aires'),
            'type' => $data['type'],
            'notes' => $data['notes'] ?? '',
        ];
        $stock = Stock::create($values);
        $items = $data['items'] ?? [];

        if ($stock) {
            $details = $this->buildStockDetails($items, $stock->id, $values['type']);
            Stock_details::insert($details);
            $response = response()->json($stock, 201);
        } else {
            $response = response()->json(['data' => 'Resource can not be created'], 500);
        }
        return $response;
    }

    /**
     * Construye los detalles del stock para insertar en la base de datos
     *
     * @param array $items
     * @param int $stockId
     * @param string $type
     * @return array
     */
    private function buildStockDetails(array $items, int $stockId, string $type): array
    {
        $details = [];
        foreach ($items as $item) {
            $aux = [
                'id_stock' => $stockId,
                'id_product' => $item['id_product'],
                'quantity' => $type === 'in' ? $item['quantity'] : -abs($item['quantity']),
            ];
            if ($type === 'in') {
                $aux['id_provider'] = $item['id_provider'];
                $aux['price_purchase'] = $item['price_purchase'];
            }
            $details[] = $aux;
        }
        return $details;
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
        $order = Stock::getByID($id);
        if ($order) {
            $order['details'] = Stock::getDetailsByID($id);
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
        $query = Stock::getAll();
        $type = $request->input('type', null);

        if (!is_null($type)) {
            $this->filter($query, 'type', ['=' => $type]);
        }

        return $query->orderBy('id', 'DESC')->paginate($request->input('perPage') ?? 40);
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
