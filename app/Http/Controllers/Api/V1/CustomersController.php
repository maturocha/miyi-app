<?php

namespace App\Http\Controllers\Api\V1;

use App\Customer;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

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

        $userid = \Auth::id();
        $today = Carbon::now()->timezone('America/Argentina/Buenos_Aires');

        $values = [];
        $values['cuit'] = $request->input('cuit', '');
        $values['firstname'] = $request->input('firstname', '');
        $values['lastname'] = $request->input('lastname', '');
        $values['email'] = $request->input('email', '');
        $values['address'] = $request->input('address', '');
        $values['cellphone'] = $request->input('cellphone', '');
        $values['telephone'] = $request->input('telephone', '');
        $values['facebook'] = $request->input('facebook', '');
        $values['instagram'] = $request->input('instagram', '');
        $birthday = $request->input('birthday', '');
        if (empty($birthday)) {
            $values['birthday'] = null;
        } else {
            $values['birthday'] = Carbon::createFromFormat('d/m/Y', $birthday. '/2020');
        }

        $values['comments'] = $request->input('comments', '');

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
     * @param Illuminate\Http\Request $request
     * @param App\Order $order
     *
     * @return Illuminate\Http\JsonResponse
     */

     
    public function show(Request $request, Customer $customer) : JsonResponse

    {

        return response()->json($customer);

        // $order = Order::getByID($id);
        // if ($order) {
        //     $order['details'] = Order::getDetailsByID($id);
        //     $response['data'] = $order;
        //     $response = response()->json($response, 200);
        // } else {
        //     $response = response()->json(['data' => 'Resource not found'], 404);
        // }
        
        // return $response;

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
        
        if ($request->has('date')) {
           $attributes['date'] = Carbon::parse($request->input('date')); 
        }   
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
        $userid = \Auth::id();

        $customers = Customer::orderBy(
             $request->input('sortBy') ?? 'name',
             $request->input('sortType') ?? 'ASC'
            );

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
