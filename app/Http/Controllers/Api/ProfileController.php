<?php

namespace App\Http\Controllers\Api;

use App\Mail\VerifyEmailMail;
use App\Support\ClientContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\ProfileUpdateRequest;

use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __construct(private ClientContext $clientContext) {}

    public function get(): JsonResponse {
        $clientId = $this->clientContext->get();
        app(PermissionRegistrar::class)->setPermissionsTeamId($clientId);

        $user = Auth::user();
        $user->load(['roles']);
        $user->role = $user->roles->first();
        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse {
        $user = User::find(Auth::user()->id);

        $this->authorize('update', $user);
        if (Auth::id() !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно прав.'
            ], 403);
        }

        $user->name = $request->get('name');
        $user->email = $request->get('email');
        $user->telegram = $request->get('telegram');
        $user->address = $request->get('address');
        $user->phone = $request->get('phone');

        if (!empty($request->get('avatar'))) {
            $avatar_path = 'avatars/avatar-'.$user->id.'-'.time().'.png';

            if ($user->avatar) {
                \Storage::disk('public')->delete($user->avatar);
            }

            $file = base64_decode(
                preg_replace('#^data:image/\w+;base64,#i', '', $request->get('avatar'))
            );

            if (\Storage::disk('public')->put($avatar_path, $file)) {
                $user->avatar = $avatar_path;
            }
        }
        
        $saved = $user->save();
        if($saved) {
            $user->load(['roles']);
            $user->role = $user->roles->first();
        }

        return response()->json([
            'success' => $saved,
            'data' => $user,
        ]);
    }

    public function sendVerificationEmail(Request $request)
    {
        try {
            $user = User::findOrFail(Auth::id());
            $token = $this->generateVerificationToken($user->email);
            
            Mail::to($user->email)->send(new VerifyEmailMail($user, $token));
            
            return response()->json([
                'success' => true,
                'message' => 'Письмо с подтверждением отправлено успешно',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не найден'
            ], 404);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отправке письма: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyByToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();
        
        if (!$user || ($user->id != Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не найден'
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email уже подтвержден',
                'verified' => true
            ]);
        }

        $expectedToken = $this->generateVerificationToken($user->email);
        if (!hash_equals($expectedToken, $request->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Недействительный или просроченный токен подтверждения'
            ], 400);
        }

        $user->markEmailAsVerified();
        return response()->json([
            'success' => true,
            'message' => 'Email успешно подтвержден!',
            'verified' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at
            ]
        ]);
    }

    private function generateVerificationToken(string $email): string
    {
        $salt = config('app.key');
        $timestamp = now()->format('Y-m-d-H');
        $data = $email . $salt . $timestamp;
        return hash('sha256', $data);
    }
}