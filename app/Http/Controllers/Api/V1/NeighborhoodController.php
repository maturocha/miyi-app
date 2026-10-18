<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Neighborhood;
use App\Http\Controllers\Concerns\RespondsWithOptions;
use DB;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class NeighborhoodController extends Controller
{
    use RespondsWithOptions;

    /**
     * Opciones livianas para selects/filtros: {"data":[{id,name,id_zone}]}.
     * Caché ≤ 60 s en servidor (se invalida en store/update/destroy/restore).
     */
    public function options(Request $request)
    {
        $rows = $this->rememberOptions('neighborhoods', function () {
            return Neighborhood::orderBy('name')->orderBy('id')->get(['id', 'name', 'id_zone'])->toArray();
        });

        return $this->optionsResponse($request, $rows);
    }

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
            'id_zone' => 'required',
        ]);

        $neighborhood = Neighborhood::create([
            'name' => $request->input('name'),
            'id_zone' => $request->input('id_zone', '')
        ]);

        self::forgetOptions('neighborhoods');

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

        $attributes = $request->all();
        
        $neighborhood->fill($attributes);
        $neighborhood->update();
        self::forgetOptions('neighborhoods');

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
        self::forgetOptions('neighborhoods');

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
        self::forgetOptions('neighborhoods');

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
        $neighborhoods = Neighborhood::join('zones','neighborhoods.id_zone','=','zones.id')
        ->orderBy(
            $this->sortColumn($request, [
                'id' => 'neighborhoods.id',
                'name' => 'neighborhoods.name',
                'id_zone' => 'neighborhoods.id_zone',
                'created_at' => 'neighborhoods.created_at',
                'zone' => 'zones.name',
                'zone_name' => 'zones.name',
            ], 'name'),
            $this->sortDirection($request, 'ASC')
        )
        ->when($request->has('search'), function ($query) use ($request) {
            $search = $request->input('search');
            return $query->where(function($q) use ($search) {
                        $q->where('neighborhoods.name', 'like', "%$search%");
                   });
        })
        ->select('neighborhoods.*', 'zones.name as zone');

        return $neighborhoods->paginate($this->perPage($request, 40, 1000));
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
