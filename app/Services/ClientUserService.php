<?php

namespace App\Services;

use App\Models\ClientUser;

class ClientUserService
{
    public function create(array $data): ClientUser
    {
        return ClientUser::create($data);
    }

    public function update(ClientUser $clientUser, array $data): ClientUser
    {
        $clientUser->update($data);
        return $clientUser;
    }

    public function delete(ClientUser $clientUser): void
    {
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
        return ClientUser::where('client_id', $clientId)
            ->with(['user:id,name,email,phone,address,avatar,created_at', 'role:id,name,visible_name'])
            ->get()
            ->map(function ($clientUser) {
                return [
                    'client_user_id' => $clientUser->id,
                    'id' => $clientUser->user->id,
                    'name' => $clientUser->user->name,
                    'email' => $clientUser->user->email,
                    'phone' => $clientUser->user->phone,
                    'address' => $clientUser->user->address,
                    'avatar' => $clientUser->user->avatar,
                    'created_at' => $clientUser->user->created_at,
                    'role' => $clientUser->role ? [
                        'id' => $clientUser->role->id,
                        'visible_name' => $clientUser->role->visible_name
                    ] : null
                ];
            });
    }
}
