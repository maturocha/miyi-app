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
        $query = Customer::query();

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('customers.fullname', 'like', "%{$search}%")
                  ->orWhere('customers.name', 'like', "%{$search}%");
            });
        }

        // Apply neighborhood filter
        if ($request->has('id_neighborhood')) {
            $neighborhood = $request->input('id_neighborhood');
            $query->join('neighborhoods', 'neighborhoods.id', '=', 'customers.id_neighborhood')
                  ->where('neighborhoods.id', '=', $neighborhood);
        }

        // Apply zone filter
        if ($request->has('id_zone')) {
            $zone = $request->input('id_zone');
            // Only join neighborhoods if not already joined
            if (!$request->has('id_neighborhood')) {
                $query->join('neighborhoods', 'neighborhoods.id', '=', 'customers.id_neighborhood');
            }
            $query->join('zones', 'zones.id', '=', 'neighborhoods.id_zone')
                  ->where('zones.id', '=', $zone);
        }

        // Apply sorting with explicit table prefix
        $sortBy = $request->input('sortBy') ?? 'name';
        $sortType = $request->input('sortType') ?? 'ASC';
        
        // Ensure sortBy column is prefixed with table name if it's a customers column
        if (!str_contains($sortBy, '.')) {
            $sortBy = "customers.{$sortBy}";
        }
        
        $query->orderBy($sortBy, $sortType);

        // Select only customers columns and apply soft delete filter
        $query->select('customers.*')
              ->whereNull('customers.deleted_at');

        return $query->paginate($request->input('perPage') ?? 40);
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
