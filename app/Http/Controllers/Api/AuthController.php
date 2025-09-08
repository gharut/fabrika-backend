<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\PasswordResetRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Requests\Api\Profile\ProfileUpdateRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Mail\PasswordResetMail;

class AuthController extends Controller
{
    // public function register(ProfileUpdateRequest $request)
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);
        $user->assignRole('admin');
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
        $record = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json([
                "success" => false,
                "message" => "Invalid or expired token"
            ], 400);
        }

        if (now()->subHours(24)->gt($record->created_at)) {
            return response()->json([
                "success" => false,
                "message" => "Token expired"
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->password = Hash::make($request->password);
            $user->save();

            \DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return response()->json([
                "success" => true,
                "message" => "Password has been reset successfully"
            ]);
        }

        return response()->json([
            "success" => false,
            "message" => "User not found"
        ], 400);
    }

    public function requestPasswordReset(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email'
            ], [
                'email.required' => 'Поле email обязательно для заполнения',
                'email.email' => 'Укажите корректный email адрес',
                'email.exists' => 'Пользователь с таким email не найден'
            ]);

            if ($validator->fails()) {
                $errorMessage = implode(' ', $validator->errors()->all());
                
                return response()->json([
                    "success" => false,
                    "message" => $errorMessage,
                    "mail_sent" => false
                ], 422);
            }

            $email = $request->email;
            \DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            $token = Str::random(64);
            \DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now()
            ]);

            $resetUrl = env('FRONTEND_URL', 'http://localhost:3000')."/reset-password?token={$token}&email={$email}";
            $emailHtml = view('mail.password-reset', [
                'resetUrl' => $resetUrl,
                'email' => $email
            ])->render();


            Mail::to($email)->send(new PasswordResetMail($resetUrl, $email));
            return response()->json([
                "success" => true,
                "message" => 'Письмо для восстановления пароля отправлено',
                "mail_sent" => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => 'Ошибка отправки письма: ' . $e->getMessage(),
                "mail_sent" => false
            ], 500);
        }
    }
}
