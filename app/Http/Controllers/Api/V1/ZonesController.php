<?php

namespace App\Http\Controllers\Api\V1;

use App\Zone;
use DB;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class ZonesController extends Controller
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
        ]);


        $zone = Zone::create([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
        ]);

        return response()->json($zone, 201);
    }

    /**
     * Show a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Zone $Zone
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Zone $zone) : JsonResponse
    {
        return response()->json($zone);
    }

    /**
     * Update a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Zone $zone
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Zone $zone) : JsonResponse
    {
                
        $attributes = $request->all();
        
        $zone->fill($attributes);
        $zone->update();

        return response()->json($zone);
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Meetup $zone
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Zone $zone) : JsonResponse
    {
        $zone->delete();

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
        $zone = Zone::withTrashed()->where('id', $id)->first();
        $zone->deleted_at = null;
        $zone->update();

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
        $zones = Zone::orderBy(
             $request->input('sortBy') ?? 'name',
             $request->input('sortType') ?? 'ASC'
        )
        ->when($request->has('search'), function ($query) use ($request) {
            $search = $request->input('search');
            return $query->where(function($q) use ($search) {
                        $q->where('name', 'like', "%$search%")
                        ->orWhere('code', 'like', "%$search%");
                   });
        })
        ->orderBy('code', 'ASC');

        return $zones->paginate($request->input('perPage') ?? 40);
    }

    /**
     * Filter a specific column property
     *
     * @param mixed $zones
     * @param string $property
     * @param array $filters
     *
     * @return void
     */
    protected function filter($zones, string $property, array $filters)
    {
        foreach ($filters as $keyword => $value) {
            // Needed since LIKE statements requires values to be wrapped by %
            if (in_array($keyword, ['like', 'nlike'])) {
                $zones->where(
                    $property,
                    _to_sql_operator($keyword),
                    "%{$value}%"
                );

                return;
            }

            $zones->where($property, _to_sql_operator($keyword), "{$value}");
        }
    }
}
