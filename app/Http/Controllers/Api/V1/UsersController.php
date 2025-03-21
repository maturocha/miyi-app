<?php

namespace App\Http\Controllers\Api\V1;

use App\User;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class UsersController extends Controller
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
            'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
            'role_id' => 'required',
            'cel' => 'required',
            'password' => 'required'
        ]);


        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'role_id' => $request->input('role_id'),
            'cel' => $request->input('cel'),
            'password' => $request->input('password'),
            //'username' => $request->input('username'),
        ]);

        return response()->json($user, 201);
    }

    /**
     * Show a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\User $user
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, User $user) : JsonResponse
    {
        return response()->json($user);
    }

    /**
     * Update a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\User $user
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, User $user) : JsonResponse
    {
        $request->validate([
            'firstname' => 'required_if:step,0|string|max:255',
            'lastname' => 'required_if:step,0|string|max:255',

            'gender' => 'nullable|in:female,male',
            'birthdate' =>
                'nullable|date:Y-m-d|before:'.now()->subYear(10)->format('Y-m-d'),
            'address' => 'nullable|string|max:510',

            'type' => 'required_if:step,1|in:superuser,user',
            'email' =>
                "required_if:step,1|email|unique:users,email,{$user->id},id,deleted_at,NULL",
            'username' =>
                "nullable|unique:users,username,{$user->id},id,deleted_at,NULL"
        ]);

        $attributes = $request->all();
        unset($attributes['step']);

        $user->fill($attributes);
        $user->update();

        return response()->json($user);
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\User $user
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, User $user) : JsonResponse
    {
        $user->delete();

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
        $user = User::withTrashed()->where('id', $id)->first();
        $user->deleted_at = null;
        $user->update();

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
        $users = User::leftjoin('roles','roles.id','=','users.role_id')
            ->select('users.*', 'roles.name as rol')
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->input('search');
                return $query->where(function($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%")
                      ->orWhere('roles.name', 'like', "%{$search}%");
                });
            })
            ->when($request->has('role_id'), function ($query) use ($request) {
                return $query->where('users.role_id', $request->input('role_id'));
            })
            ->when($request->has('type'), function ($query) use ($request) {
                return $query->where('users.type', $request->input('type'));
            })
            ->orderBy(
                $request->input('sortBy') ?? 'users.id',
                $request->input('sortType') ?? 'ASC'
            );

        return $users->paginate($request->input('perPage') ?? 10);
    }

    /**
     * Filter a specific column property
     *
     * @param mixed $users
     * @param string $property
     * @param array $filters
     *
     * @return void
     */
    protected function filter($users, string $property, array $filters)
    {
        foreach ($filters as $keyword => $value) {
            // Needed since LIKE statements requires values to be wrapped by %
            if (in_array($keyword, ['like', 'nlike'])) {
                $users->where(
                    $property,
                    _to_sql_operator($keyword),
                    "%{$value}%"
                );

                return;
            }

            $users->where($property, _to_sql_operator($keyword), "{$value}");
        }
    }
}
