<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
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
        // Incluye email/celular de todos. Lo usan el filtro "Encargado" de Repartos
        // (admin/administración) y el de Analytics (también ventas).
        abort_unless(in_array((int) optional($request->user())->role_id, [1, 2, 4], true), 403);

        return response()->json($this->paginatedQuery($request));
    }

    /**
     * Store a new resource.
     *
     * @param App\Http\Requests\UserStoreRequest $request
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function store(UserStoreRequest $request) : JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json($user, 201);
    }

    /**
     * Show a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Models\User $user
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, User $user) : JsonResponse
    {
        // Incluye email/celular de todos: solo admin y administración (filtro "Encargado" de Repartos).
        abort_unless(in_array((int) optional($request->user())->role_id, [1, 4], true), 403);

        return response()->json($user);
    }

    /**
     * Update a resource.
     *
     * @param App\Http\Requests\UserUpdateRequest $request
     * @param App\Models\User $user
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function update(UserUpdateRequest $request, User $user) : JsonResponse
    {
        $validatedData = $request->validated();
        
        // Solo actualizar password si se proporciona
        if (empty($validatedData['password'])) {
            unset($validatedData['password']);
        }

        $user->update($validatedData);

        return response()->json($user);
    }

    /**
     * Destroy a resource.
     *
     * @param Illuminate\Http\Request $request
     * @param App\Models\User $user
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, User $user) : JsonResponse
    {
        abort_unless((int) optional($request->user())->role_id === 1, 403);

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
        abort_unless((int) optional($request->user())->role_id === 1, 403);

        $user = User::withTrashed()->where('id', $id)->first();
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
        
        $user->restore();

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
        $users = User::leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->select('users.*', 'roles.name as rol')
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->input('search');
                return $query->where(function($q) use ($search) {
                    $q->where('users.email', 'like', "%{$search}%")
                      ->orWhere('users.cel', 'like', "%{$search}%")
                      ->orWhere('roles.name', 'like', "%{$search}%");
                });
            })
            ->when($request->has('role_id') && $request->input('role_id') !== null, function ($query) use ($request) {
                return $query->where('users.role_id', $request->input('role_id'));
            })
            ->orderBy(
                $this->sortColumn($request, [
                    'id' => 'users.id',
                    'name' => 'users.name',
                    'email' => 'users.email',
                    'cel' => 'users.cel',
                    'role_id' => 'users.role_id',
                    'last_signin' => 'users.last_signin',
                    'created_at' => 'users.created_at',
                    'rol' => 'roles.name',
                ], 'users.id'),
                $this->sortDirection($request, 'ASC')
            );

        return $users->paginate($this->perPage($request, 10, 1000));
    }
}
