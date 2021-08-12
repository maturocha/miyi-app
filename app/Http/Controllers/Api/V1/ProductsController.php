<?php

namespace App\Http\Controllers\Api\V1;

use App\Product;
use DB;

use App\Http\Controllers\Api\V1\ImageController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

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
        return response()->json($this->paginatedQuery($request));
    }

    private function getValues(Request $request)
    {

        $values = $request->all();
        
        $price_unit = $values['price_purchase'] + (($values['price_purchase']*$values['percentage_may'])/100);
        $price_min = $values['price_purchase'] + (($values['price_purchase']*$values['percentage_min'])/100);

        $values['price_unit'] = number_format((float)$price_unit, 2,'.', '');
        $values['price_min'] = number_format((float)$price_min, 2,'.', '');

        return $values;
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
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $values = $this->getValues($request);

        $product = Product::create($values);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $name = Str::slug($record->name).'_'.time();
            $id = $product->id;
            $image = new ImageController();
            $image->updateImage($file, $name, $id);
            
       }

        return response()->json($product, 201);
    }

    /**
     * Show a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Product $product
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Product $product) : JsonResponse
    {
        $response['data'] = $product;
        $response['data']['image'] = $product->getImages();
        $response['data']['history_prices'] = $product->historyPrices();
        $response['data']['history_stock'] = $product->stockMoving();
        $response['data']['history_sales'] = $product->orderMoving();
        return response()->json($response, 200);
    }

    /**
     * Update a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Product $meetup
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Product $product) : JsonResponse
    {

        $attributes = $this->getValues($request);
        
        $product->fill($attributes);
        $product->update();

        return response()->json($product);
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Meetup $meetup
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Product $meetup) : JsonResponse
    {
        $meetup->delete();

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
        $meetup = Meetup::withTrashed()->where('id', $id)->first();
        $meetup->deleted_at = null;
        $meetup->update();

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
        $products = Product::orderBy(
             $request->input('sortBy') ?? 'name',
             $request->input('sortType') ?? 'ASC'
        )
        ->when($request->has('search'), function ($query) use ($request) {
            $search = $request->input('search');
            return $query->where(function($q) use ($search) {
                        $q->where('code_miyi', 'like', "%$search%")
                        ->orWhere('name', 'like', "%$search%");
                   });
        })
        ->when($request->has('stock'), function ($query) use ($request) {
            
            return $query->where(function($queryContainer){
                $queryContainer->where(function($q){
                    $q->where('stock','>',0)
                        ->where('own_product','=',1);
                    })
                    ->orwhere(function($q){
                        $q->where('stock','<>',0)
                        ->where('own_product','=', 0);
                        });    
                });
        })
        ->when($request->has('id_category'), function ($query) use ($request) {
            $category = $request->input('id_category');
            $query->where(function ($query) use ($category) {
                $query->where('id_category', '=', "$category");
            });
        })
        ->when($request->has('provider'), function ($query) use ($request) {
            $provider = $request->input('provider');
            $query->join('stock_details','products.id','=','stock_details.id_product')
                ->where('id_provider', '=', "$provider");
            
        })
        
        ->groupBy('products.id')
        ->orderBy('name', 'ASC')
        ->whereNull('products.deleted_at');

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
