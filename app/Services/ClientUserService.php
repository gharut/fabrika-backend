<?php

namespace App\Services;

use App\Models\ClientUser;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ClientUserService
{
    public function create(array $data): ClientUser
    {
        $clientUser = ClientUser::create([
            'client_id' => $data['client_id'],
            'user_id'   => $data['user_id'],
        ]);

        if (!empty($data['role'])) {
            $role = Role::findOrFail($data['role']);
            app(PermissionRegistrar::class)->setPermissionsTeamId($data['client_id']);
            $clientUser->user->assignRole($role->name);
        }

        return $clientUser->load('user', 'client');
    }

    public function update(ClientUser $clientUser, array $data): ClientUser
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($clientUser->client_id);
        if (!empty($data['role_id'])) {
            $role = Role::findOrFail($data['role_id']);
            $clientUser->user->syncRoles([$role->name]);
        }

        $clientUser->load('user', 'client');
        $clientUser->user->load('roles');
        $role = $clientUser->user->roles->first();
        $clientUser->role_id = $role->id;
        $clientUser->role = $role;

        return $clientUser;
    }

    public function delete(ClientUser $clientUser): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($clientUser->client_id);
        $clientUser->user->syncRoles([]);
        $clientUser->delete();
    }

    public function getClientsByUser($userId)
    {
        return ClientUser::where('user_id', $userId)
            ->join('clients', 'client_users.client_id', '=', 'clients.id')
            ->select('clients.id', 'clients.name')
            ->get();
    }

    public function getUsersByClient($clientId)
    {
        $items = ClientUser::where('client_id', $clientId)
            ->with('user:id,name,email,phone,address,avatar,created_at')
            ->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId($clientId);
        return $items->map(function ($clientUser) use ($clientId) {
            $user = $clientUser->user;
            $role = $user->roles->first();
            
            return [
                'id'         => $clientUser->id,
                'user_id'    => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'phone'      => $user->phone,
                'address'    => $user->address,
                'avatar'     => $user->avatar,
                'created_at' => $user->created_at,
                'role'       => $role ? [
                    'id'           => $role->id,
                    'name'         => $role->name,
                    'visible_name' => $role->visible_name ?? $role->name,
                ] : null,
            ];
        });
    }
}
