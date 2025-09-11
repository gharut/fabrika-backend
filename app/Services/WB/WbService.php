<?php

namespace App\Services\Wb;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Services\WbProductService;

use App\Models\WbProduct;
use App\Models\ProductSize;
use App\Models\Client;
use App\Models\Brand;
use App\Models\ProductImage;
use App\Enums\ProductCategory;

use Carbon\Carbon;

class WbService
{
    public function __construct(
        private WbProductService $wbProductService
    ) {}

    private string $base = 'https://content-api.wildberries.ru/content/v2/get/cards/list';

    public function importWbProducts(int $marketplaceAccountId, int $limit = 100): array
    {
         try {
           $marketplaceAccount = $this->getMarketplaceAccountWbToken($marketplaceAccountId);
            if (!$marketplaceAccount['success']) {
                return [
                    'success' => false,
                    'created' => 0,
                    'updated' => 0,
                    'total_processed' => 0,
                    'errors' => [$marketplaceAccount['value']],
                ];
            }

            $apiToken = $marketplaceAccount['api_token_enc'];
            $clientId = $marketplaceAccount['client_id'];

            $allCards = [];
            foreach ($this->fetchAll($apiToken, $limit) as $batch) {
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

    public function getMarketplaceAccountWbToken(int $marketplaceAccountId): array
    {
        $result = [
            'success' => false,
            'value' => '',
            'token' => null,
            'api_token_enc' => null,
            'client_id' => null
        ];

        try {
            $marketplaceAccount = MarketplaceAccount::find($marketplaceAccountId);
            
            if (!$marketplaceAccount) {
                $result['value'] = "Аккаунт маркетплейса {$marketplaceAccountId} не найден";
                return $result;
            }

            $token = $marketplaceAccount->wb_api_token;
            if (empty($token)) {
                $result['value'] = "WB API токен не настроен для аккаунта {$marketplaceAccount->name}";
                return $result;
            }

            $result['success'] = true;
            $result['value'] = "Токен успешно получен";
            $result['token'] = $token;
            $result['api_token_enc'] = $marketplaceAccount->api_token_enc;
            $result['client_id'] = $marketplaceAccount->client_id;
            
            return $result;

        } catch (\Exception $e) {
            $result['value'] = "Ошибка при получении токена. Обратитесь к администратору.";
            Log::error('Ошибка получения WB токена', [
                'marketplace_account_id' => $marketplaceAccountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $result;
        }
    }

    public function fetchAll(string $apiToken, int $limit = 100): \Generator
    {
        if (empty($apiToken)) {
            return;
        }

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
                ->withHeaders(['Authorization' => $apiToken])
                ->retry(5, 500, throw: false)
                ->post($this->base.'?locale=ru', $payload);
            
            if (!$resp->successful()) {
                Log::error('WB API request failed', [
                    'status' => $resp->status(),
                    'response' => $resp->body()
                ]);
                return;
            }

            $data = $resp->json();
            $data = $data['data'] ?? $data;
            $cards = $data['cards'] ?? [];
            $cur = $data['cursor'] ?? ($resp['cursor'] ?? []);
            $total = (int)($cur['total'] ?? count($cards));

            if (empty($cards)) {
                return;
            }

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
                
                $brandResult = $this->createBrand($card['brand'], $clientId);
                if (!empty($brandResult['errors'])) {
                    $result['statistics']['errors'][] = implode("; ", $sizesResult['errors']);
                }
                $brandId = $brandResult['brandId'];

                $productData = [
                    'client_id' => $clientId,
                    'name' => $card['title'] ?? 'Без названия',
                    'vendor_code' => $card['vendorCode'] ?? '',
                    'color' => $characteristics['color'] ?? '',
                    'article' => $nmID,
                    'composition' => $characteristics['composition'] ?? '',
                    'has_chestny_znak' =>  false,
                    'category' => ProductCategory::CLOTHES,
                    'brand_id' => $brandId,
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

                if (!empty($card['photos']) && is_array($card['photos'])) {
                    $firstPhoto = $card['photos'][0]['big'] ?? null;
                    if ($firstPhoto) {
                        $existingImage = ProductImage::where('product_id', $product->id)->first();

                        if (!$existingImage) {
                            ProductImage::create([
                                'product_id' => $product->id,
                                'url'        => $firstPhoto,
                                'path'       => null,
                                'position'   => 0,
                                'type'       => 'main',
                                'alt'        => $product->name,
                                'width'      => null,
                                'height'     => null,
                                'storage'    => 'url',
                            ]);
                        }
                    }
                }

                $sizesResult = $this->processSizes($product->id, $product->article, $card['sizes'] ?? []);
                if (!empty($sizesResult['errors'])) {
                    $result['statistics']['errors'][] = implode("; ", $sizesResult['errors']);
                }

            } catch (\Exception $e) {
                $result['success'] = false;
                $result['statistics']['errors'][] = 'Ошибка при сохранении товара ' . $nmID . $e;
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
                    'tech_size' => $size['techSize'] ?? '',
                    'value' => $size['wbSize'] ?? '',
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

    public function createBrand(string $brandName, int $clientId): array
    {
        $result = [
            'processed' => 0,
            'errors' => [],
            'brandId' => null
        ];

        if (empty($clientId)) {
            $result['errors'][] = "Не указан ID клиента";
            return $result;
        }

        if (!$brandName) {
            return $result;
        }

        try {
            $brandData = [
                'name' => $brandName,
                'client_id' => $clientId,
            ];

            $existingBrand = Brand::where('name', $brandName)
                ->where('client_id', $clientId)
                ->first();

            if ($existingBrand) {
                $result['brandId'] = $existingBrand->id;
            } else {
                $createdBrand = Brand::create($brandData);
                $result['brandId'] = $createdBrand->id;
                $result['processed'] = 1;
            }

        } catch (\Exception $e) {
            $result['errors'][] = "Ошибка при создании бренда '{$brandName}': " . $e->getMessage();
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