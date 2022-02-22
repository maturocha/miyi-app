<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\User;
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
       
    
        $request->validate([
            'password' => 'required|string|confirmed|min:6'
        ]);

        $user = User::findOrfail($id)->first();
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
