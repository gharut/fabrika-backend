<?php

namespace App\Services\Wb;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Services\WbProductService;

use App\Models\WbProduct;
use App\Models\ProductSize;
use App\Models\Client;
use App\Enums\ProductCategory;

use Carbon\Carbon;

class WbService
{
    public function __construct(
        private WbProductService $wbProductService
    ) {}

    private string $base = 'https://content-api.wildberries.ru/content/v2/get/cards/list';

    public function importWbProducts(int $clientId, int $limit = 100): array
    {
        

         try {
            $tokenResult = $this->getClientWbToken($clientId);
            if (!$tokenResult['success']) {
                return [
                    'success' => false,
                    'created' => 0,
                    'updated' => 0,
                    'total_processed' => 0,
                    'errors' => [$tokenResult['value']],
                ];
            }

            $allCards = [];
            foreach ($this->fetchAll($clientId, $limit) as $batch) { // передаем $limit
                $allCards = array_merge($allCards, $batch);
                Log::info('Processed batch', ['count' => count($batch)]);
            }

            $stats = $this->insertProducts(
                wbCards: $allCards,
                clientId: $clientId
            );

            return [
                'success'          => $stats['success'],
                'created'          => $stats['statistics']['products_created'] ?? 0,
                'updated'          => $stats['statistics']['products_updated'] ?? 0,
                'total_processed'  => $stats['statistics']['total_processed'] ?? 0,
                'errors'           => $stats['statistics']['errors'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('WB API Error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'created' => 0,
                'updated' => 0,
                'total_processed' => 0,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    public function getClientWbToken(int $clientId): array
    {
        $result = [
            'success' => false,
            'value' => ''
        ];

        try {
            $client = Client::find($clientId);
            
            if (!$client) {
                $result['value'] = "Клиент {$clientId} не найден";
                return $result;
            }

            $token = $client->wb_api_token;
            if (empty($token)) {
                $result['value'] = "WB API токен не настроен для клиента {$client->name}";
                return $result;
            }

            $result['success'] = true;
            $result['value'] = $token;
            
            return $result;

        } catch (\Exception $e) {
            $result['value'] = "Ошибка при получении токена обратитесь к администратору.";
            Log::error('Ошибка получения WB токена', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $result;
        }
    }

    public function fetchAll(int $clientId, int $limit = 100): \Generator
    {
        $tokenResult = $this->getClientWbToken($clientId);
        if (!$tokenResult['success']) {
            return;
        }

        $totalProcessed = 0;
        $cursor = ['limit' => $limit];
        $sort = ['ascending' => false];
        $filter = [
            'withPhoto'             => -1,
            'textSearch'            => '',
            'tagIDs'                => [],
            'allowedCategoriesOnly' => true,
            'objectIDs'             => [],
            'brands'                => [],
            'imtID'                 => null,
        ];

        while (true) {
            $payload = ['settings' => compact('cursor','filter','sort')];

            $resp = Http::withOptions(['verify' => false])
                ->withHeaders(['Authorization' => $tokenResult['value']])
                ->retry(5, 500, throw: false)
                ->post($this->base.'?locale=ru', $payload)
                ->throw()
                ->json();
            
            $data   = $resp['data']   ?? $resp;
            $cards  = $data['cards']  ?? [];
            $cur    = $data['cursor'] ?? ($resp['cursor'] ?? []);
            $total  = (int)($cur['total'] ?? count($cards));

            if (empty($cards)) {
                return;
            }

            $totalProcessed += count($cards);
            yield $cards;

            $last = end($cards);
            if (!isset($last['updatedAt'], $last['nmID'])) {
                return;
            }

            $cursor = [
                'limit'     => $limit,
                'updatedAt' => $last['updatedAt'],
                'nmID'      => $last['nmID'],
            ];

            if ($total < $limit) {
                return;
            }

            usleep(150_000);
        }
    }

    public function insertProducts(array $wbCards, int $clientId): array
    {
        $result = [
            'success' => true,
            'statistics' => [
                'total_processed' => 0,
                'products_created' => 0,
                'products_updated' => 0,
                'errors' => []
            ]
        ];

        foreach ($wbCards as $card) {
            $result['statistics']['total_processed']++;
            $nmID = $card['nmID'] ?? null;
            if (!$nmID) {
                $result['statistics']['errors'][] = 'Пропущен товар без артикула';
                continue;
            }

            try {
                $characteristics = $this->extractCharacteristics($card['characteristics'] ?? []);
                
                $productData = [
                    'client_id' => $clientId,
                    'name' => $card['title'] ?? 'Без названия',
                    'color' => $characteristics['color'] ?? '',
                    'article' => $nmID,
                    'composition' => $characteristics['composition'] ?? '',
                    'has_chestny_znak' =>  false,
                    'category' => ProductCategory::CLOTHES,
                ];

                $existingProduct = WbProduct::where('article', $nmID)
                    ->where('client_id', $clientId)
                    ->first();

                if ($existingProduct) {
                    $this->wbProductService->update($existingProduct->id, $productData);
                    $result['statistics']['products_updated']++;
                    $product = $existingProduct;
                } else {
                    $product = $this->wbProductService->create($productData);
                    $result['statistics']['products_created']++;
                }

                $sizesResult = $this->processSizes($product->id, $product->article, $card['sizes'] ?? []);
                if (!empty($sizesResult['errors'])) {
                    $result['statistics']['errors'][] = implode("; ", $sizesResult['errors']);
                }

            } catch (\Exception $e) {
                $result['success'] = false;
                $result['statistics']['errors'][] = 'Ошибка при сохранении товара ' . $nmID;
                continue;
            }
        }

        return $result;
    }

    protected function processSizes(int $productId, string $article, array $sizes): array
    {
        $result = [
            'processed' => 0,
            'errors' => []
        ];

        if (empty($productId)) {
            $result['errors'][] = "Не указан Id продукта";
            return $result;
        }

        foreach ($sizes as $size) {
            try {
                $barcode = $size['skus'][0] ?? null;
                if (!$barcode) {
                    $result['errors'][] = "Отсутствует баркод для размера";
                    return $result;
                }

                $sizeData = [
                    'product_id' => $productId,
                    'value' => $size['techSize'] ?? '',
                    'barcode' => $barcode,
                ];

                $existingSize = ProductSize::where('barcode', $barcode)
                    ->where('product_id', $productId)
                    ->first();

                if (!$existingSize) {
                    ProductSize::create($sizeData);
                }
                $result['processed']++;

            } catch (\Exception $e) {
                $result['errors'][] = $result['processed'] . " размеров создано. Ошибка при создании размера товара " . $article;
            }
        }

        return $result;
    }

    protected function extractCharacteristics(array $characteristics): array
    {
        $result = [];
        
        foreach ($characteristics as $char) {
            if ($char['name'] === 'Цвет') {
                $result['color'] = $char['value'][0] ?? null;
            } elseif ($char['name'] === 'Состав') {
                $result['composition'] = $char['value'][0] ?? null;
            }
        }
        
        return $result;
    }
}