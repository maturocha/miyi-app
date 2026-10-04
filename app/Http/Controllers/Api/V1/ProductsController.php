<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;

use App\Http\Controllers\Api\V1\ImageController;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

use App\Exports\ProductsExport;
use App\Http\Requests\ProductFormRequest;
use App\Http\Resources\ProductResource;
use Maatwebsite\Excel\Facades\Excel;

class ProductsController extends Controller
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
        $paginator = $this->paginatedQuery($request);
        
        // Aplicar Resource de forma optimizada
        $paginator->setCollection(
            $paginator->getCollection()->map(function ($product) use ($request) {
                return new ProductResource($product);
            })
        );
    
    return response()->json($paginator);
    }

        /**
     * List all resource.
     *
     * @param Illuminate\Http\Request $request
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function export()
    {
        return Excel::download(new ProductsExport, 'products.xlsx');
    }

    public function store(ProductFormRequest $request)
    {
        $product = Product::create($request->validated());

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $name = \Str::slug($product->name) . '_' . time();
            $id = $product->id;
            $image = new ImageController();
            $image->updateImage($file, $name, $id);
        }

        return new ProductResource($product);
    }

    public function update(ProductFormRequest $request, Product $product)
    {
        $product->update($request->validated());

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $name = \Str::slug($product->name) . '_' . time();
            $id = $product->id;
            $image = new ImageController();
            $image->updateImage($file, $name, $id);
        }

        return new ProductResource($product);
    }

   

    /**
     * Show a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Product $product
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Product $product) : ProductResource
    {
        return new ProductResource($product);
    }

    /**
     * Paginated sales history for a product.
     */
    public function salesHistory(Request $request, $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        $page = max(1, (int) ($request->input('page') ?? 1));
        $perPage = (int) ($request->input('per_page') ?? 20);
        $result = $product->orderMovingPaginated($perPage, $page);
        return response()->json([
            'data' => $result['items'],
            'meta' => [
                'current_page' => $page,
                'last_page' => max((int) ceil($result['total'] / max($perPage, 1)), 1),
                'per_page' => $perPage,
                'total' => $result['total'],
            ],
        ]);
    }

    /**
     * Paginated stock movement history for a product.
     */
    public function stockHistory(Request $request, $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        $page = max(1, (int) ($request->input('page') ?? 1));
        $perPage = (int) ($request->input('per_page') ?? 20);
        $result = $product->stockMovingPaginated($perPage, $page);
        return response()->json([
            'data' => $result['items'],
            'meta' => [
                'current_page' => $page,
                'last_page' => max((int) ceil($result['total'] / max($perPage, 1)), 1),
                'per_page' => $perPage,
                'total' => $result['total'],
            ],
        ]);
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Meetup $meetup
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Product $product) : JsonResponse
    {
        
        $product->delete();

        return response()->json($product);

    }

    /**
     * Restore a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param string $id
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id) : JsonResponse
    {
        $product = Product::withTrashed()->where('id', $id)->first();
        $product->deleted_at = null;
        $product->update();

        $paginator = $this->paginatedQuery($request);
        
        // Aplicar ProductResource a cada item manteniendo la estructura de paginación
        $paginator->getCollection()->transform(function ($product) use ($request) {
            return (new ProductResource($product))->toArray($request);
        });
        
        return response()->json($paginator);
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
        $products = Product::orderBy(
            $this->sortColumn($request, [
                'name' => 'products.name',
                'code_miyi' => 'products.code_miyi',
                'barcode' => 'products.barcode',
                'price_unit' => 'products.price_unit',
                'price_purchase' => 'products.price_purchase',
                'price_min' => 'products.price_min',
                'percentage_may' => 'products.percentage_may',
                'percentage_min' => 'products.percentage_min',
                'stock' => 'products.stock',
                'id_category' => 'products.id_category',
                'own_product' => 'products.own_product',
                'id' => 'products.id',
                'created_at' => 'products.created_at',
                'updated_at' => 'products.updated_at',
            ], 'name'),
            $this->sortDirection($request, 'ASC')
        )
       ->with(['category:id,name', 'images', 'activePromotions'])
       ->when($request->has('search'), function ($query) use ($request) {
            $search = trim($request->input('search'));
            $searchTerms = preg_split('/\s+/', $search); // Divide en palabras individuales
            
            return $query->where(function($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $q->where(function($subQuery) use ($term) {
                        $subQuery->where('code_miyi', 'like', "%{$term}%")
                                ->orWhere('name', 'like', "%{$term}%");
                    });
                }
            });
        })
        ->when($request->has('in_stock'), function ($query) use ($request) {
            $in_stock = $request->input('in_stock') == '1';

            if ($in_stock) {
                return $query->where(function($stockQuery){
                    $stockQuery->where(function($q){
                        $q->where('stock','>',0)
                            ->where('own_product','=',1);
                    })
                    ->orWhere(function($q){
                        $q->where('stock','<>',0)
                            ->where('own_product','=', 0);
                    });
                });
            } else {    
                return $query->where('stock','=',0);
            }
        })
        ->when($request->has('id_category'), function ($query) use ($request) {
            $category = $request->input('id_category');
            $query->where(function ($query) use ($category) {
                $query->where('id_category', '=', "$category");
            });
        })
        ->when($request->has('id_provider'), function ($query) use ($request) {
            $provider = $request->input('id_provider');
            $query->join('stock_details','products.id','=','stock_details.id_product')
                ->where('id_provider', '=', "$provider");
            
        })
        ->select('products.*')
        ->groupBy('products.id')
        ->orderBy('products.name', 'asc');
        //->whereNull('products.deleted_at');

        return $products->paginate($this->perPage($request, 40, 5000));
    }

    /**
     * Filter a specific column property
     *
     * @param mixed $meetups
     * @param string $property
     * @param array $filters
     *
     * @return void
     */
    protected function filter($meetups, string $property, array $filters)
    {
        foreach ($filters as $keyword => $value) {
            // Needed since LIKE statements requires values to be wrapped by %
            if (in_array($keyword, ['like', 'nlike'])) {
                $meetups->where(
                    $property,
                    _to_sql_operator($keyword),
                    "%{$value}%"
                );

                return;
            }

            $meetups->where($property, _to_sql_operator($keyword), "{$value}");
        }
    }
}
