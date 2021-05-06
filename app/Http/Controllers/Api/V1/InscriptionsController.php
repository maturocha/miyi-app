<?php

namespace App\Http\Controllers\Api\V1;

use App\Inscription;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class InscriptionsController extends Controller
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
        return response()->json(Inscription::getQuantityByMeetup());
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

        $inscription = Inscription::create([
            'id_meetup' => $request->input('id_meetup'),
            'id_user' => $userid,
            'date' => Carbon::now(),
            'temperature' => $request->input('temperature'),
        ]);

        return response()->json($inscription, 201);
    }

    /**
     * Show a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Meetup $inscription
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Meetup $inscription) : JsonResponse
    {
        return response()->json($inscription);
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Meetup $meetup
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Inscription $inscription) : JsonResponse
    {
        $inscription->delete();

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
        return $inscriptions = Inscription::getQuantityByMeetup();

    }

    /**
     * Filter a specific column property
     *
     * @param mixed $inscriptions
     * @param string $property
     * @param array $filters
     *
     * @return void
     */
    protected function filter($inscriptions, string $property, array $filters)
    {
        foreach ($filters as $keyword => $value) {
            // Needed since LIKE statements requires values to be wrapped by %
            if (in_array($keyword, ['like', 'nlike'])) {
                $inscriptions->where(
                    $property,
                    _to_sql_operator($keyword),
                    "%{$value}%"
                );

                return;
            }

            $inscriptions->where($property, _to_sql_operator($keyword), "{$value}");
        }
    }
}
