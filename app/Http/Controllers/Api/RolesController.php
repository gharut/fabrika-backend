<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Roles\RoleCreateRequest;
use App\Http\Requests\Api\Roles\RoleUpdateRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolesController extends Controller
{
    public function list(): JsonResponse {
        $roles = Role::all()->toArray();
        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    public function get($id, Request $request): JsonResponse 
    {
        $role = Role::find($id);
    
        if (!$role) {
            return response()->json([], 204);
        }

        if ($request->boolean('permissions')) {
            $role->load('permissions');
            
            return response()->json([
                'role' => $role->only(['id', 'name', 'visible_name']),
                'permissions' => $role->permissions->map->only(['id', 'name', 'visible_name', 'description'])
            ]);
        }
        
        return response()->json([
            'role' => $role->only(['id', 'name', 'visible_name']),
        ]);
    }

    public function getPermissions(): JsonResponse {
        $permissions = Permission::all()->toArray();
        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    public function destroy(Role $role): JsonResponse {
        if($role->name == "super-admin") {
            return response()->json([
                'success' => false,
                'message' => "You cannot delete admin role",
            ], 403);
        }
        $role->permissions()->detach();
        $role->users()->detach();
        return response()->json([
            'success' => $role->delete(),
        ]);
    }

    public function store(RoleCreateRequest $request): JsonResponse {
        $role = new Role();
        $role->fill($request->only("name", "visible_name"));
        $role->guard_name = "api";

        $saved = $role->save();

        if($saved && $request->get('permissions')) {
            $role->permissions()->attach($request->get('permissions'));
        }

        return response()->json([
            'success' => $saved,
            'data' => $saved ? $role : [],
        ]);
    }

    public function update(RoleUpdateRequest $request, Role $role): JsonResponse {
        $role->fill($request->only('name', 'visible_name'));
        $role->permissions()->detach();
        if(!empty($request->get('permissions'))) {
            $role->permissions()->attach($request->get('permissions'));
        }

        $saved = $role->save();

        return response()->json([
            'success' => $saved,
            'data' => $saved ? $role : "",
        ]);
    }
}
