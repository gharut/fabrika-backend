<?php

namespace App\Http\Middleware;

use Closure;
use Spatie\Permission\PermissionRegistrar;
use App\Models\Permission;

class CanClient
{
    public function handle($request, Closure $next, string $ability)
    {
        $clientId = app(\App\Support\ClientContext::class)->id();
        app(PermissionRegistrar::class)->setPermissionsTeamId($clientId);

        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $user->load('roles.permissions');

        if (!$user || !$user->can($ability)) {
            $permission = Permission::where('name', $ability)->first();

            return response()->json([
                'message'       => 'Forbidden',
                'permission'    => ['code' => $ability,  'visible_name' => $permission?->visible_name ? $permission?->visible_name : ''],
                'client_id'     => $clientId,
            ], 403);
        }

        return $next($request);
    }
}

