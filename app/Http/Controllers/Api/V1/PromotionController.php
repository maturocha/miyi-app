<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PromotionController extends Controller
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
     * @param App\Http\Requests\StorePromotionRequest $request
     * @return App\Http\Resources\PromotionResource
     */
    public function store(StorePromotionRequest $request) : PromotionResource
    {
        $data = $request->validated();
        $productIds = $data['product_ids'] ?? null;
        
        // Remover product_ids del array para crear la promoción
        unset($data['product_ids']);
        
        $promotion = Promotion::create($data);

        // Sincronizar productos si se proporcionaron
        if ($productIds) {
            $promotion->products()->sync($productIds);
        }

        return new PromotionResource($promotion->load('products'));
    }

    /**
     * Show a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Promotion $promotion
     *
     * @return App\Http\Resources\PromotionResource
     */
    public function show(Request $request, Promotion $promotion) : PromotionResource
    {
        return new PromotionResource($promotion->load('products'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param App\Http\Requests\StorePromotionRequest $request
     * @param App\Promotion $promotion
     * @return App\Http\Resources\PromotionResource
     */
    public function update(StorePromotionRequest $request, Promotion $promotion) : PromotionResource
    {
        $data = $request->validated();
        $productIds = $data['product_ids'] ?? null;
        
        // Remover product_ids del array para actualizar la promoción
        unset($data['product_ids']);
        
        $promotion->update($data);

        // Sincronizar productos si se proporcionaron
        if ($productIds !== null) {
            $promotion->products()->sync($productIds);
        }

        return new PromotionResource($promotion->load('products'));
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Promotion $promotion
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Promotion $promotion) : JsonResponse
    {
        $promotion->delete();
        
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
        $promotions = Promotion::orderBy(
            $request->input('sortBy') ?? 'priority',
            $request->input('sortType') ?? 'ASC'
        )
        ->when($request->has('search'), function ($query) use ($request) {
            $search = $request->input('search');
            return $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('type', 'like', "%$search%");
            });
        })
        ->when($request->has('active'), function ($query) use ($request) {
            if ($request->active == '1') {
                return $query->active();
            } elseif ($request->active == '0') {
                return $query->where('is_active', false);
            }
        })
        ->when($request->has('type'), function ($query) use ($request) {
            return $query->where('type', $request->type);
        })
        ->when($request->has('product_id'), function ($query) use ($request) {
            return $query->whereHas('products', function ($q) use ($request) {
                $q->where('products.id', $request->product_id);
            });
        })
        ->when($request->has('with_products') && $request->with_products == '1', function ($query) {
            return $query->with('products');
        })
        ->orderBy('priority', 'asc')
        ->orderBy('starts_at', 'desc');

        return $promotions->paginate($request->input('perPage') ?? 40);
    }
}
