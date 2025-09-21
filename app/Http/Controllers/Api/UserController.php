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
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

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

    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'permission' => 'required|string',
        ]);

        $user = $request->user(); // текущий аутентифицированный пользователь
        $permission = $request->input('permission');

        // Проверка права пользователя
        $hasPermission = $user->can($permission);

        // Получаем все роли пользователя
        $roles = $user->roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                // Права этой роли
                'permissions' => $role->permissions->pluck('name')
            ];
        });

        // Все права пользователя (через роли и напрямую)
        $userPermissions = $user->getAllPermissions()->pluck('name');

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'roles' => $roles,
            'user_permissions' => $userPermissions,
            'permission_checked' => $permission,
            'has_permission' => $hasPermission,
        ]);
    }

    public function store(UserCreateRequest $request)
    {
        $user = new User();
        $user->fill($request->only(['name', 'email', 'phone', 'address']));
        $user->password = Hash::make("changable");
        $saved = $user->save();

        if($saved) {
            $user->roles()->attach($request->get('role'));
            $user->load('roles:id,visible_name')->loadCount('roles');
            $user->role = $user->roles_count ? $user->roles[0] : ['id'=>0, 'visible_name'=>""];
            Password::createToken($user);
        }
        return response()->json([
            'success' => $saved,
            'data' => $saved ? $user : [],
        ]);
    }

    public function update(UserUpdateRequest $request, User $user) {
        $this->authorize('update', $user);
        if (Auth::id() !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно прав.'
            ], 403);
        }
        
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

    public function assignRoles(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $user = User::findOrFail($request->user_id);
        $roles = $request->roles;

        $rolesToAssign = array_filter($roles, fn($role) => !$user->hasRole($role));

        if (!empty($rolesToAssign)) {
            $user->assignRole($rolesToAssign);
        }

        return response()->json([
            'success' => true,
            'message' => 'Роли успешно назначены',
            'assigned_roles' => $user->roles->pluck('name'),
        ]);
    }
}
