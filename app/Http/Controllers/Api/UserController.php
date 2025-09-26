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

use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{

    public function list(): JsonResponse
    {
        $users = \DB::table('users')
            ->select('id', 'name', 'email', 'phone', 'avatar', 'created_at')
            ->get();
        
        if ($users->isEmpty()) {
            return response()->json([]);
        }
        
        $userRoles = \DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereIn('model_id', $users->pluck('id'))
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->leftJoin('clients', 'model_has_roles.client_id', '=', 'clients.id')
            ->select(
                'model_has_roles.model_id',
                'roles.id as role_id',
                'roles.visible_name as role_visible_name',
                'model_has_roles.client_id',
                'clients.name as client_name'
            )
            ->get()
            ->groupBy('model_id');
        
        $result = $users->map(function ($user) use ($userRoles) {
            $roles = $userRoles->get($user->id, collect())->map(function ($role) {
                return [
                    'role' => ['id' => $role->role_id, 'visible_name' => $role->role_visible_name],
                    'client' => $role->client_name,
                ];
            })->toArray();
            
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'created_at' => $user->created_at,
                'roles' => $roles
            ];
        });
        
        return response()->json($result);
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
        $requestedRole = $request->get('role');
        if ($this->isSuperAdminRole($requestedRole)) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя назначить данную роль при создании пользователя'
            ], 403);
        }

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
        
        $requestedRole = $request->get('role');
        if ($this->isSuperAdminRole($requestedRole)) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя назначить роль супер-администратора'
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

        if ($this->containsSuperAdminRole($roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя назначить роль супер-администратора'
            ], 403);
        }

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

    private function isSuperAdminRole($roleId): bool
    {
        $superAdminRole = Role::where('name', 'super-admin')->first();
        return $superAdminRole && $roleId == $superAdminRole->id;
    }

    private function containsSuperAdminRole(array $roleNames): bool
    {
        return in_array('super-admin', $roleNames);
    }
}
