<?php

namespace App\Http\Controllers\Api\V1;

use App\Neighborhood;
use DB;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class NeighborhoodController extends Controller
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


        $neighborhood = Neighborhood::create([
            'name' => $request->input('name'),
        ]);

        return response()->json($neighborhood, 201);
    }

    /**
     * Show a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Neighborhood $neighborhood
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Neighborhood $neighborhood) : JsonResponse
    {
        return response()->json($neighborhood);
    }

    /**
     * Update a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Neighborhood $meetup
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Neighborhood $neighborhood) : JsonResponse
    {
                
        $neighborhood->fill([
            'name' => $request->input('name'),
            'slug' => _clean_string($request->input('name')),
        ]);
        $neighborhood->update();

        return response()->json($neighborhood);
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Meetup $meetup
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Neighborhood $neighborhood) : JsonResponse
    {
        $neighborhood->delete();

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
        $neighborhood = Neighborhood::withTrashed()->where('id', $id)->first();
        $neighborhood->deleted_at = null;
        $neighborhood->update();

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
        $categories = Neighborhood::orderBy(
             $request->input('sortBy') ?? 'name',
             $request->input('sortType') ?? 'ASC'
        )
        ->when($request->has('search'), function ($query) use ($request) {
            $search = $request->input('search');
            return $query->where(function($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                   });
        })
        ->orderBy('name', 'ASC');

        return $categories->paginate($request->input('perPage') ?? 40);
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
