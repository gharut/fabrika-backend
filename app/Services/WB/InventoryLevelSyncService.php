<?php

namespace App\Services\WB;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

use App\Models\MarketplaceAccount;
use App\Models\InventoryLevel;
use App\Models\ProductSize;
use App\Models\WbProduct;

class InventoryLevelSyncService
{
    protected string $base;
    protected string $token;

    public function __construct()
    {
        $this->logUid = uniqid('wb_', true);
        $this->base = 'https://statistics-api.wildberries.ru/api/v1';
    }

    protected function headers(): array
    {
        return $this->token ? [
            'Authorization' => $this->token,
            'Accept'        => 'application/json',
        ] : [];
    }

    public function runForAllAccounts(): void
    {   
        $this->log("Запуск процесса обновления остатков");
        $accounts = MarketplaceAccount::where('platform', 'WB')
            ->whereNotNull('api_token_enc')
            ->get();

        foreach ($accounts as $account) {
            $this->token = $account->api_token_enc;
            $this->logUid = uniqid("wb_{$account->id}_", true);
            $this->log("Начало импорта для магазина ID {$account->id}, client_id {$account->client_id}");

            try {
                $hasProducts = WbProduct::where('client_id', $account->client_id)
                    ->where('is_wb_import', true)
                    ->exists();

                if (!$hasProducts) {
                    $this->log('Нет товаров для импорта, пропускаем аккаунт');
                    continue;
                }

                $this->run();
            } catch (\Throwable $e) {
                $this->log('Ошибка при импорте', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            $this->log("Завершение импорта для магазина ID {$account->id}");
        }
        $this->log("Завершение процесса обновления остатков");
    }

    public function syncStock(int $id)
    {
        $result = (object)[
            'success'  => false,
            'message'  => ''
        ];

        $account = MarketplaceAccount::where('platform', 'WB')->find($id);

        if (!$account) {
            $this->log("Аккаунт не найден по токену");
            $result->message = "Аккаунт не найден по токену";
            return $result;
        }

        $this->token  = $account->api_token_enc;
        $this->logUid = uniqid("wb_{$account->id}_", true);
        $this->log("Начало импорта для магазина ID {$account->id}, client_id {$account->client_id}");

        try {
            $hasProducts = WbProduct::where('client_id', $account->client_id)
                ->where('is_wb_import', true)
                ->exists();

            if (! $hasProducts) {
                $this->log('Нет товаров для импорта, пропускаем аккаунт');
                $result->message = "Нет товаров для импорта, пропускаем аккаунт";
                return $result;
            }

            $this->run();
            $result->success = true;
            return $result;
        } catch (\Throwable $e) {
            $this->log('Ошибка при импорте', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $result->message = $e->getMessage();
            return $result;
        }

        $this->log("Завершение импорта для магазина ID {$account->id}");
        $result->success = true;
        return $result;
    }

    public function run(): void
    {
        $dateFrom = '2019-06-20T00:00:00';
        $allRows = [];
        while (true) {
            $rows = $this->fetchStocks($dateFrom);

            if (empty($rows)) {
                $this->log('Reached end of data');
                break;
            }

            $allRows = array_merge($allRows, $rows);
            $last = end($rows);
            $dateFrom = $last['lastChangeDate'] ?? $dateFrom;

            if (count($rows) < 60000) break;
        }

        if (empty($allRows)) {
            $this->log('Empty response');
            return;
        }
        $this->storeInventoryLevels($allRows);
    }

    protected function fetchStocks(string $dateFrom): array
    {
        $resp = Http::withOptions(['verify' => false])
            ->withHeaders($this->headers())
            ->get($this->base . '/supplier/stocks', ['dateFrom' => $dateFrom]);

        if ($resp->failed()) {
            $this->log('WB stocks request failed', [
                'status' => $resp->status(),
                'body'   => $resp->body(),
            ]);
            return [];
        }

        $data = $resp->json();
        return is_array($data) ? $data : [];
    }

    protected function storeInventoryLevels(array $rows): void
    {
        if (empty($rows)) return;

        $nmIds = collect($rows)->pluck('nmId')->filter()->unique()->map(fn($id) => (string)$id);
        $productMap = WbProduct::whereIn('article', $nmIds)
            ->pluck('id', 'article')
            ->all();

        $barcodes = collect($rows)->pluck('barcode')->filter()->unique();
        $sizeMap = ProductSize::whereIn('barcode', $barcodes)
            ->pluck('id', 'barcode')
            ->all();

        DB::transaction(function () use ($rows, $productMap, $sizeMap) {
            $grouped = collect($rows)->groupBy(fn($row) => ($row['nmId'] ?? 0) . '|' . ($row['barcode'] ?? ''));

            foreach ($grouped as $key => $items) {
                [$nmId, $barcode] = explode('|', $key);
                $productId = $productMap[$nmId] ?? null;
                $sizeId = $sizeMap[$barcode] ?? null;

                if (!$productId || !$sizeId) {
                    $this->log('Product or size not found', [
                        'nmId' => $nmId,
                        'barcode' => $barcode,
                    ]);
                    continue;
                }

                $totalQty = collect($items)->sum('quantity');

                InventoryLevel::updateOrCreate(
                    [
                        'product_id' => $productId,
                        'size_id'    => $sizeId,
                        'source'     => 'wb',
                    ],
                    [
                        'qty'       => max(0, (int)$totalQty),
                        'synced_at' => now(),
                    ]
                );
            }
        });
    }

    protected function log(string $message, array $data = []): void
    {
        Log::channel('wb-api')->info("Import stocks {$this->logUid}: {$message}", $data);
    }
}