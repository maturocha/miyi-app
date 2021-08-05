<?php

namespace App\Http\Controllers\Api\V1;

use App\Product;
use DB;

use Illuminate\Support\Facades\Auth;
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
            'description' => 'nullable|string|max:255',
            'date' => 'required|date|after_or_equal:today',
            'temperature' => 'nullable|numeric|between:0,99.99',
        ]);

        $userid = \Auth::id();

        $meetup = Meetup::create([
            'name' => $request->input('name'),
            'id_owner' => $userid,
            'description' => $request->input('description'),
            'date' => Carbon::parse($request->input('date')),
            'temperature' => $request->input('temperature'),
        ]);

        return response()->json($meetup, 201);
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
    public function update(Request $request, Product $meetup) : JsonResponse
    {

        $attributes = $request->all();
        
        if ($request->has('date')) {
           $attributes['date'] = Carbon::parse($request->input('date')); 
        }   
        $meetup->fill($attributes);
        $meetup->update();

        return response()->json($meetup);
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
        ->when($request->has('category'), function ($query) use ($request) {
            $category = $request->input('category');
            $query->where(function ($query) use ($category) {
                $query->where('id_category', '=', "$category");
            });
        })
        ->when($request->has('provider'), function ($query) use ($request) {
            $provider = $request->input('provider');
            $query->join('stock_details','products.id','=','stock_details.id_product')
                ->where('id_provider', '=', "$provider");
            
        })
        ->where(function($queryContainer){
            $queryContainer->where(function($q){
                $q->where('stock','>',0)
                    ->where('own_product','=',1);
                })
                ->orwhere(function($q){
                    $q->where('stock','<>',0)
                    ->where('own_product','=', 0);
                    });    
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
