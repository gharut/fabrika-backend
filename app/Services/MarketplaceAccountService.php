<?php

namespace App\Services;

use App\Models\MarketplaceAccount;
use App\Support\ClientContext;

use Illuminate\Validation\ValidationException;

class MarketplaceAccountService
{
    public function __construct(
        private ClientContext $clientContext
    ) {}

    public function create(array $data): MarketplaceAccount
    {
        $this->validateUniqueAccount($data);
        return MarketplaceAccount::create($data);
    }

    public function update(MarketplaceAccount $account, array $data): MarketplaceAccount
    {
        $this->validateUniqueAccount($data, $account->id);
        $account->update($data);
        return $account;
    }

    public function delete(MarketplaceAccount $account): void
    {
        $account->delete();
    }

    private function validateUniqueAccount(array $data, ?int $exceptId = null): void
    {
        $clientId = $this->clientContext->get();
        if (!isset($clientId) || !is_numeric($clientId)) {
            throw ValidationException::withMessages([
                'error' => 'Id организации обязателен и должен быть числом'
            ]);
        }

        if (!isset($data['platform']) || empty($data['platform'])) {
            throw ValidationException::withMessages([
                'error' => 'Платофарма обязателена'
            ]);
        }

        $platform = strtolower($data['platform']);
        $allowedPlatforms = ['wb'];

        if (!in_array($platform, $allowedPlatforms)) {
            throw ValidationException::withMessages([
                'error' => 'Платформа должна быть WB'
            ]);
        }

        $query = MarketplaceAccount::where('client_id', $clientId)
            ->whereRaw('LOWER(platform) = ?', [$platform]);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'error' => "Магазин {$data['platform']} уже добавлен. Выберите другую платформу"
            ]);
        }
    }
}
