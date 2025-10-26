<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\WB\ProductPriceSyncService;
use App\Services\WB\InventoryLevelSyncService;
use App\Models\MarketplaceAccount;

class SyncWbData extends Command
{
    protected $signature = 'app:sync-wb-data';
    protected $description = 'Синхронизация цен и остатков по всем аккаунтам WB';

    public function handle()
    {
        Log::info('[WB Sync] Запуск синхронизации');

        $accounts = MarketplaceAccount::where('platform', 'WB')
            ->whereNotNull('api_token_enc')
            ->get();

        foreach ($accounts as $account) {
            try {
                Log::info("[WB Sync] Начало для аккаунта ID {$account->id}");

                $syncProductPrice = app(ProductPriceSyncService::class);
                $countPrice = $syncProductPrice->syncPrice($account->api_token_enc);

                $syncInventoryLevel = app(InventoryLevelSyncService::class);
                $resultSyncStock = $syncInventoryLevel->syncStock($account->id);
                $syncStockSuccess = $resultSyncStock->success;
                $syncStockMessage = $resultSyncStock->message;

                $statusText = $syncStockSuccess
                    ? 'успешно'
                    : "ошибка: {$syncStockMessage}";

                Log::info("[WB Sync] Аккаунт {$account->id}: Обновлено цен {$countPrice}, обновление остатков {$statusText}");
            } catch (\Throwable $e) {
                Log::error("[WB Sync] Ошибка для аккаунта {$account->id}: {$e->getMessage()}");
            }
        }

        Log::info('[WB Sync] Завершено');
        return Command::SUCCESS;
    }
}
