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
}
