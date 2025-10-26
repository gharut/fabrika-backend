<?php

namespace App\Services\WB;

use App\Models\ProductPrice;
use App\Models\WbProduct;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ProductPriceSyncService
{
    public function __construct(private Client $http) {}

    /**
     * Синхронизировать цены и скидки из WB API
     */
    public function syncPrice(string $wbToken, int $limit = 1000): int
    {
        $endpoint = 'https://discounts-prices-api.wildberries.ru/api/v2/list/goods/filter';

        $offset = 0;
        $updatedCount = 0;

        while (true) {
            $response = $this->http->get($endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$wbToken}",
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'query' => [
                    'limit'  => $limit,
                    'offset' => $offset,
                ],
                'http_errors' => false,
                'timeout' => 60,
                'verify' => false,
            ]);

            $code = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true);

            if ($code >= 400 || ($body['error'] ?? false)) {
                Log::error('[WB] Ошибка при получении данных о ценах', [
                    'code' => $code,
                    'errorText' => $body['errorText'] ?? null,
                ]);
                break;
            }

            $goods = $body['data']['listGoods'] ?? [];
            if (empty($goods)) {
                break;
            }

            foreach ($goods as $g) {
                $this->updateProductPrices($g);
                $updatedCount++;
            }

            if (count($goods) < $limit) {
                break;
            }

            $offset += $limit;
        }

        return $updatedCount;
    }

    /**
     * Обновление или создание записей цен/скидок в product_prices
     */
    private function updateProductPrices(array $data): void
    {
        $product = WbProduct::where('article', $data['nmID'])->first();
        if (! $product) {
            return;
        }

        $basePrice = $data['sizes'][0]['price'] ?? 0;
        $salePrice = $data['sizes'][0]['discountedPrice'] ?? 0;
        $discount  = $data['discount'] ?? 0;

        // обновляем или создаём записи
        $this->updateOrCreatePrice($product->id, 'base_price',  (float) ($basePrice));
        $this->updateOrCreatePrice($product->id, 'sale_price', (float) ($salePrice));
        $this->updateOrCreatePrice($product->id, 'discount', (float) ($discount));
    }

    private function updateOrCreatePrice(int $productId, string $type, float $value): void
    {
        ProductPrice::updateOrCreate(
            ['product_id' => $productId, 'type' => $type],
            ['value' => $value]
        );
    }
}
