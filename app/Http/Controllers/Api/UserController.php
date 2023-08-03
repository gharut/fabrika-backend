<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Users\UserCreateRequest;
use App\Http\Requests\Api\Users\UserUpdateRequest;
use App\Mail\UserActivationMail;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;


class UserController extends Controller
{

    public function list(): JsonResponse
    {
        $list = User::all(['id','name','email','phone','avatar'])->load('roles:id,visible_name')->loadCount('roles');
        foreach ($list as $k => $data) {
            $list[$k]->role = $data->roles_count ? $data['roles'][0] : ['id'=>0, 'visible_name'=>""];
            unset($list[$k]->roles);
        }

        return response()->json($list);
    }

    public function store(UserCreateRequest $request)
    {
        $user = new User();
        $user->fill($request->only(['name', 'email', 'phone', 'address']));
        $user->password = Hash::make("changable");
        $saved = $user->save();

        if($saved) {
            $user->roles()->attach($request->get('role'));
            Password::createToken($user);
        }
        return response()->json([
            'success' => $saved,
            'data' => $saved ? $user : [],
        ]);
    }

    public function update(UserUpdateRequest $request, User $user) {
        $user->fill($request->only(['name', 'email', 'phone', 'address']));
        $saved = $user->save();
        if($saved) {
            $user->roles()->detach();
            $user->roles()->attach($request->get('role'));

            $user->load('roles:id,visible_name');
            $user->role = $user->roles[0];
            unset($user->roles);
        }
        return response()->json([
            'success' => $saved,
            'data' => $saved ? $user : [],
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        sleep(5);
        $user->roles()->detach();

        return response()->json([
            'success' => $user->delete()
        ]);
    }


}
