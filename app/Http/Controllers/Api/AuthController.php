<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\PasswordResetRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Requests\Api\Profile\ProfileUpdateRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(ProfileUpdateRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function login(Request $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login details',
                'data' => []
            ], 401);
        }

        $user = User::with([
            'roles:name,visible_name',
            'permissions:name'
        ])->where('email', $request['email'])->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->role = $user->roles->first();

        return response()->json([
            'success' => true,
            'data' => array_merge([
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], $user->toArray())
        ]);
    }

    public function passwordReset(PasswordResetRequest $request): JsonResponse
    {

//        $user = User::where("email", $request->get('email'))->first();
//        $token = Password::createToken($user);
//        echo $token;exit;
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {

                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );


        return response()->json([
            "success" => $status === Password::PASSWORD_RESET
        ]);
    }
}
