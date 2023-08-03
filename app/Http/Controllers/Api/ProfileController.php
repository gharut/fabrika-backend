<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{

    public function __construct()
    {

    }

    public function get(): JsonResponse {

        /**
         * @var $user User
         */
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
        $user->address = $request->get('address');
        $user->phone = $request->get('phone');
        if (!empty($request->get('avatar'))) {
            $avatar_path = 'avatars/avatar-'.$user->id.'-'.time().'.png';
            if($user->avatar != "") {
                //unlink($user->avatar);
                Storage::disk("local")->delete("public/".$user->avatar);
            }
            $file = base64_decode(
                preg_replace('#^data:image/\w+;base64,#i', '', $request->get('avatar')
            ));
            if(Storage::disk("local")->put("public/".$avatar_path, $file)){
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
