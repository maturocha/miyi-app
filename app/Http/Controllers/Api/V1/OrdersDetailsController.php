<?php

namespace App\Http\Controllers\Api\V1;

use App\Order_details;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use App\Http\Requests\OrderDetailsStoreRequest;
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
     * @param Illuminate\Http\Request $request
     * @param App\Order_details $orderDetail
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Order_details $orderDetail) : JsonResponse
    {
        $attributes = [
            'price_final' => $request->input('price_final'),
            'weight' => $request->input('weight'),
            'promotion_id' => $request->input('promotion_id'),
        ];
         
        $orderDetail->fill($attributes);
        $orderDetail->update();

        // Cargar relaciones si se solicita
        if ($request->has('with_promotion') && $request->with_promotion == '1') {
            $orderDetail->load('promotion:id,name,type');
        }

        return response()->json(new OrderDetailsResource($orderDetail));
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Order_details $orderDetail
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Order_details $orderDetail) : JsonResponse
    {
        $orderDetail->delete();
        
        return response()->json($this->paginatedQuery($request));
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
}
