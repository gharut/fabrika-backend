<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\ProfileUpdateRequest;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Support\ClientContext;

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


        if($request->get('change_password')) {
            $user->password = Hash::make($request->get('password'));
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
}
