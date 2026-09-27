<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPasswordResetRequest;
use Illuminate\Support\Str;

class ChangePasswordController extends Controller
{

    /**
     * Change password.
     *
     * @param Request $request
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function changePassword($id, Request $request) : JsonResponse
    {
       
    
        // Solo admin o el propio usuario.
        $authUser = $request->user();
        abort_unless($authUser && ((int) $authUser->role_id === 1 || (int) $authUser->id === (int) $id), 403);

        $request->validate([
            'password' => 'required|string|confirmed|min:6'
        ]);

        // Antes: findOrfail($id)->first() devolvía el PRIMER usuario de la tabla,
        // no el pedido, y le cambiaba la contraseña.
        $user = User::findOrFail($id);
        $user->password = $request->get('password');
        $user->save();

        return response()->json(['response' => 'ok', 'data' => $user], 201);
       
    }

    /**
     * Get a validator for an incoming change password request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);
    }
}
