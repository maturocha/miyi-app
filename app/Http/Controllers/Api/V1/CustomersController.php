<?php

namespace App\Http\Controllers\Api\V1;

use App\Customer;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use App\Http\Resources\CustomerResource;

class CustomersController extends Controller
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

        $values = $request->all();

        $customer = Customer::create($values);

        if ($customer) {
            $response = response()->json($customer, 201);
        } else {
            $response = response()->json(['data' => 'Resource can not be created'], 500);
        }

        return $response;
        
    }

    /**
     * Show a resource.
     *
     * @param App\Customer $customer
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Customer $customer) : JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CustomerResource($customer)
        ]);
    }

    /**
     * Update a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Order $order
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Customer $customer) : JsonResponse
    {

        $attributes = $request->all();
        
        $customer->fill($attributes);
        $customer->update();

        return response()->json($customer);
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Order $order
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Customer $customer) : JsonResponse
    {
        $customer->delete();

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
        $customer = Order::withTrashed()->where('id', $id)->first();
        $customer->deleted_at = null;
        $customer->update();

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

        $customers = Customer::orderBy(
             $request->input('sortBy') ?? 'name',
             $request->input('sortType') ?? 'ASC'
        )

        ->when($request->has('search'), function ($query) use ($request) {
            $search = $request->input('search');
            return $query->where(function($q) use ($search) {
                $q->where('fullname', 'like', "%$search%")
                    ->orWhere('name', 'like', "%$search%");
                   });
        })
        ->when($request->has('id_neighborhood'), function ($query) use ($request) {        
            $neighborhood = $request->input('id_neighborhood');
            $query->join('neighborhoods','neighborhoods.id','=','customers.id_neighborhood')
            ->where('neighborhoods.id', '=', "$neighborhood");   
        })
        ->when($request->has('id_zone'), function ($query) use ($request) {        
            $zone = $request->input('id_zone');
            $query->join('neighborhoods','neighborhoods.id','=','customers.id_neighborhood')
            ->join('zones','zones.id','=','neighborhoods.id_zone')
            ->where('zones.id', '=', "$zone");   
        })
        ->select('customers.*')
        ->whereNull('customers.deleted_at');

        return $customers->paginate($request->input('perPage') ?? 40);
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
