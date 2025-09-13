<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\PasswordResetRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Client;
use App\Models\ClientUser;
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
        $user->assignRole('user');
        $token = $user->createToken('auth_token')->plainTextToken;
        $client = Client::create([
            'name' => 'Новая организация',
            'email' => $request->email,
            'phone' => '',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'owner_id' => $user->id,
        ]);

        $clientUser = ClientUser::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'role_id' => 3,
        ]);

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

    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь не аутентифицирован'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'current_password' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) use ($user) {
                        if (!Hash::check($value, $user->password)) {
                            $fail('Текущий пароль неверен.');
                        }
                    }
                ],
                'new_password' => [
                    'required',
                    'string',
                    'min:10',
                    'confirmed',
                    'different:current_password' // Проверка, что новый пароль не равен старому
                ],
                'new_password_confirmation' => 'required|string'
            ], [
                'current_password.required' => 'Текущий пароль обязателен',
                'new_password.required' => 'Новый пароль обязателен',
                'new_password.min' => 'Пароль должен содержать минимум 10 символов',
                'new_password.confirmed' => 'Подтверждение пароля не совпадает',
                'new_password.different' => 'Новый пароль должен отличаться от текущего',
                'new_password_confirmation.required' => 'Подтверждение пароля обязательно'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            $user->password = Hash::make($validated['new_password']);
            $user->save();

            $currentToken = $request->user()->currentAccessToken();
            if ($currentToken) {
                $user->tokens()->where('id', '!=', $currentToken->id)->delete();
            } else {
                $user->tokens()->delete();
                \Log::warning('Текущий токен не найден, удалены все токены', ['user_id' => $user->id]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Пароль успешно изменен'
            ]);
        } catch (\Exception $e) {
            \Log::error('Ошибка при смене пароля: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при смене пароля'
            ], 500);
        }
    }
}