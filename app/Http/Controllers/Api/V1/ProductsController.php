<?php

namespace App\Http\Controllers\Api\V1;

use App\Product;

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
            $request->input('sortBy') ?? 'name',
            $request->input('sortType') ?? 'ASC'
       )
       ->with('activePromotions')
       ->when($request->has('search'), function ($query) use ($request) {
            $search = $request->input('search');
            return $query->where(function($q) use ($search) {
                        $q->where('code_miyi', 'like', "%$search%")
                        ->orWhere('name', 'like', "%$search%");
                   });
        })
        ->when($request->has('in_stock'), function ($query) use ($request) {
            $in_stock = $request->input('in_stock') == '1';

            if ($in_stock) {
                return $query->where(function($q){
                    $q->where('stock','>',0)
                        ->where('own_product','=',1);
                    })
                    ->orwhere(function($q){
                        $q->where('stock','<>',0)
                        ->where('own_product','=', 0);
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

        return $products->paginate($request->input('perPage') ?? 40);
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
