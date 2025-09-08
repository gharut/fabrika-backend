<?php

namespace App\Services;

use App\Models\MarketplaceAccount;

class MarketplaceAccountService
{
    public function create(array $data): MarketplaceAccount
    {
        return MarketplaceAccount::create($data);
    }

    public function update(MarketplaceAccount $account, array $data): MarketplaceAccount
    {
        $account->update($data);
        return $account;
    }

    public function delete(MarketplaceAccount $account): void
    {
        $account->delete();
    }
}
