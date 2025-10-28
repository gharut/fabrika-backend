<?php

namespace App\Services;

use App\Models\PricingStrategy;
use App\Models\MarketplaceAccount;
use App\Models\StrategyItem;
use App\Models\ProductPrice;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

use InvalidArgumentException;
use GuzzleHttp\Client;

class PricingStrategyService
{
    public function __construct(private Client $http) {}

    public function create(array $data): PricingStrategy
    {
        return DB::transaction(function () use ($data) {
            $attrs = Arr::only($data, [
                'name',
                'type',
                'status',
                'order_by_field',
                'order_direction',
                'account_id',
                'created_by',
                'updated_by',
            ]);

            $attrs['type']   = $attrs['type']   ?? PricingStrategy::TYPE_TIME_DISCOUNT;
            $attrs['status'] = $attrs['status'] ?? PricingStrategy::STATUS_DRAFT;

            return PricingStrategy::create($attrs);
        });
    }

    public function update(int $id, array $data): ?PricingStrategy
    {
        $strategy = PricingStrategy::find($id);

        if (! $strategy) {
            return null;
        }

        $strategy->fill(Arr::only($data, [
            'name',
            'type',
            'status',
            'order_by_field',
            'order_direction',
            'updated_by',
            'account_id',
        ]));

        $strategy->save();
        return $strategy->fresh(['items']);
    }

    private function getSkipResult(PricingStrategy $strategy, string $msg)
    {
        return [
            'strategyId' => $strategy->id,
            'status'     => $strategy->status,
            'applied'    => 0,
            'skipped'    => 0,
            'message'    => $msg,
        ];
    }

    public function upsertTempPrice(int $productId, int $strategyItemId, float $value): ProductPrice
    {
        $discount = (float) round($value);
        $discount = max(0, min(100, $discount));

        return ProductPrice::updateOrCreate(
            [
                'product_id'       => $productId,
                'strategy_item_id' => $strategyItemId,
                'type'             => 'temp_discount',
            ],
            [ 'value' => $discount, ]
        );
    }

    public function getTempPrice(int $productId, int $strategyItemId): float
    {
        return (float) (
            ProductPrice::where([
                    'product_id'       => $productId,
                    'strategy_item_id' => $strategyItemId,
                    'type'             => 'temp_discount',
                ])
                ->value('value') ?? 0.0
        );
    }

    public function run(int $strategyId): array
    {
        $strategy = PricingStrategy::findOrFail($strategyId);

        if ($strategy->status !== PricingStrategy::STATUS_ACTIVE) {
            return $this->getSkipResult($strategy, 'Пропущена, так как стратегия неактивна');
        }

        $accountId = $strategy->account_id;
        if (empty($accountId)) {
            return $this->getSkipResult($strategy, 'Пропущена, так как у стратегии не указан кабинет маркетплейса');
        }

        $wbToken = MarketplaceAccount::where('id', $accountId)->value('api_token_enc');
        if (empty($wbToken)) {
            return $this->getSkipResult($strategy, 'Пропущена, так как не указан API ключ');
        }

        if ($strategy->type !== PricingStrategy::TYPE_TIME_DISCOUNT) {
            return $this->getSkipResult($strategy, 'Неподдерживаемый тип у стратегии');
        }

        $now = now('Europe/Moscow')->format('H:i:s');
        $itemsQuery = StrategyItem::query()
            ->where('strategy_id', $strategy->id)
            ->where('model_type', \App\Models\WbProduct::class)
            ->whereHas('wbProduct', function ($q) use ($accountId) {
                $q->where('account_id', $accountId);
            })
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where(function ($q) use ($now) {
                $q->where(function ($q1) use ($now) {
                    $q1->where('status', StrategyItem::STATUS_ACTIVE)
                    ->whereTime('starts_at', '<=', $now)
                    ->whereTime('ends_at', '>=', $now);
                })
                ->orWhere(function ($q2) use ($now) {
                    $q2->where('status', StrategyItem::STATUS_APPLIED)
                    ->whereTime('ends_at', '<=', $now);
                });
            });

        // Сортировка по указанному фильтру
        if ($strategy->order_by_field && $strategy->order_direction) {
            $dir = strtolower($strategy->order_direction) === 'desc' ? 'desc' : 'asc';
            $itemsQuery->orderBy($strategy->order_by_field, $dir);
        }

        $items = $itemsQuery->get();
        $payload = [];
        $mapper = [];
        $skipped = 0;

        foreach ($items as $item) {
            $id        = $item->id;
            $product   = $item->target;
            $productId = $product->id;
            $nmId     = (int) ($product->article ?? 0);

            $applyTemp = (
                $item->status === StrategyItem::STATUS_ACTIVE
                && $item->starts_at !== null
                && $item->ends_at !== null
                && $item->starts_at <= $now
                && $item->ends_at >= $now
            );

            $discount = (int) round((float) $item->temp_discount);
            if ($applyTemp) {
                $this->upsertTempPrice($productId, $id, $item->discount);
            } else {
                $discount = $this->getTempPrice($productId, $id);
            }

            if ($nmId <= 0 || $discount <= 0 || $discount >= 100) {
                $skipped++;
                continue;
            }
            
            $payload[] = [
                'id' => $id,
                'nmId' => $nmId,
                'discount' => $discount,
                'productId' => $productId
            ];

            if ($nmId > 0) {
                $status = StrategyItem::STATUS_ACTIVE;
                if ($applyTemp) {
                    $status = StrategyItem::STATUS_APPLIED;
                }
                $mapper[$nmId] = [
                    'id' => $id,
                    'status' => $status,
                ];
            }
        }

        if (empty($payload)) {
            return [
                'strategyId' => $strategy->id,
                'status'     => $strategy->status,
                'applied'    => 0,
                'skipped'    => $items->count(),
                'message'    => 'No eligible items in time window',
            ];
        }

        $res = $this->setDiscounts($wbToken, $payload);
        $uploadId = $res['uploadId'];
        if ($uploadId) {
            $uploadStatus = $this->getUploadStatus($wbToken, $uploadId);

            $hasErrorHistory = $uploadStatus['error'];
            if ($hasErrorHistory) {
                Log::error('[WB] updateDetails', [
                    'error'   => true,
                    'message' => $uploadStatus['errorText'],
                ]);

                $details = $this->getUploadDetails($wbToken, $uploadId);
                $items   = collect($details['items']);

                $toApply = $items->where('status', 2)->pluck('nmId')->all();
                $toLog   = $items->where('status', 3);

                $idsToUpdate = array_values(array_intersect_key($mapper, array_flip($toApply)));
                $this->updateStrategyItemsStatus($idsToUpdate);
                $this->logDiscountErrors($toLog);

                return [
                    'strategyId' => $strategy->id,
                    'status'     => $strategy->status,
                    'applied'    => 0,
                    'skipped'    => $items->count(),
                    'uploadId'   => $uploadId,
                    'message'    => $uploadStatus['errorText'] ?? 'Ошибка при получении данных о детализации загрузки',

                    'appliedNmIds' => $toApply,
                    'errorItems'   => $toLog->map(fn($i) => [
                        'nmId'      => $i['nmId'],
                        'errorText' => $i['errorText'],
                    ])->values()->all(),

                    'messageDetails' => $toLog->isEmpty()
                        ? 'Детализация обработана успешно'
                        : 'Некоторые товары не обновлены, см. errorItems',
                ]; 
            }

            $updateStatusResult = $this->updateStrategyItemsStatus(array_values($mapper));
            Log::info('Результат обновления статусов', [
                'updateStatusResult' => $updateStatusResult,
            ]);
        }
        
        return [
            'strategyId' => $strategy->id,
            'status'     => $strategy->status,
            'applied'    => count($payload),
            'skipped'    => $skipped,
            'error'      => $res['error'] ?? false,
            'errorText'  => $res['errorText'] ?? null,
        ];
    }
    
    public function setDiscounts(string $wbToken, array $items): array
    {
        $endpoint = 'https://discounts-prices-api.wildberries.ru/api/v2/upload/task';
        $payload = array_map(
            fn($i) => ['nmID' => $i['nmId'], 'discount' => $i['discount']],
            $items
        );

        Log::info('[WB] setDiscounts payload', [
            'payload' => $payload,
        ]);

        try {
            $resp = $this->http->post($endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$wbToken}",
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'data' => $payload,
                ],
                'http_errors' => false,
                'timeout' => 60,
                'verify' => false,
            ]);

            $code = $resp->getStatusCode();
            $body = (string)$resp->getBody();
            $json = json_decode($body, true);

            if ($code >= 400) {
                Log::error('[WB] setDiscounts error', [
                    'code' => $code,
                    'body' => mb_substr($body, 0, 2000),
                ]);
                return [
                    'uploadId' => null,
                    'alreadyExists' => false,
                    'error' => true,
                    'errorText' => "HTTP {$code}",
                ];
            }

            if ($json['error']) {
                $errorText = $json['errorText'];
               Log::error('[WB] setDiscounts error', [
                    'body' => $errorText,
                ]);
                return [
                    'uploadId' => null,
                    'alreadyExists' => false,
                    'error' => true,
                    'errorText' => $errorText,
                ]; 
            }

            Log::info('[WB] setDiscounts result', [
                'result' => $json,
            ]);

            return [
                'uploadId' => $json['data']['id'] ?? null,
                'alreadyExists' => (bool)($json['data']['alreadyExists'] ?? false),
                'error' => (bool)($json['error'] ?? false),
                'errorText' => $json['errorText'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('[WB] setDiscounts exception', [
                'message' => $e->getMessage(),
            ]);
            return [
                'uploadId' => null,
                'alreadyExists' => false,
                'error' => true,
                'errorText' => $e->getMessage(),
            ];
        }
    }

    public function getUploadStatus(string $wbToken, int $uploadId): array
    {
        sleep(2);
        $endpoint = 'https://discounts-prices-api.wildberries.ru/api/v2/history/tasks';
        try {
            $resp = $this->http->get($endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$wbToken}",
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'query' => [
                    'uploadID' => $uploadId
                ],
                'http_errors' => false,
                'timeout' => 60,
                'verify' => false,
            ]);

            $code = $resp->getStatusCode();
            $body = (string)$resp->getBody();
            $json = json_decode($body, true);

            if ($code >= 400) {
                Log::error('[WB] getUploadStatus error', [
                    'code' => $code,
                    'body' => mb_substr($body, 0, 2000),
                    'uploadID' => $uploadId
                ]);
                return [
                    'uploadId' => $uploadId,
                    'error' => true,
                    'errorText' => "HTTP {$code}",
                ];
            }

            if ($json['error']) {
                $errorText = $json['errorText'];
                Log::error('[WB] getUploadStatus error response body', [
                    'body' => $json,
                    'uploadId' => $uploadId,
                ]);
                return [
                    'uploadId' => $uploadId,
                    'error' => true,
                    'errorText' => $errorText,
                ]; 
            }

            Log::info('[WB] getUploadStatus response body', [
                'uploadId' => $uploadId,
                'json' => $json,
            ]);

            $status = (int)($json['data']['status'] ?? 0);

            $map = [
                3 => ['ok' => true,  'text' => 'Обработана успешно'],
                4 => ['ok' => false, 'text' => 'Загрузка отменена'],
                5 => ['ok' => false, 'text' => 'Обработана частично, есть ошибки'],
                6 => ['ok' => false, 'text' => 'Ошибка во всех товарах'],
            ];

            $mapped = $map[$status] ?? ['ok' => false, 'text' => 'Неизвестный статус'];

            return [
                'uploadId'      => $json['data']['uploadID'] ?? null,
                'alreadyExists' => (bool)($json['data']['alreadyExists'] ?? false),
                'status'        => $status,
                'status_text'   => $mapped['text'],
                'error'         => ! $mapped['ok'],
                'errorText'     => $json['errorText'] ?? ($mapped['ok'] ? null : $mapped['text']),
            ];

        } catch (\Throwable $e) {
            Log::error('[WB] getUploadStatus exception', [
                'message' => $e->getMessage(),
            ]);
            return [
                'uploadId' => null,
                'error' => true,
                'errorText' => $e->getMessage(),
            ];
        }
    }

    private function logDiscountErrors(iterable $items): void
    {
        foreach ($items as $item) {
            Log::error('[WB] Ошибка при обновлении скидки', [
                'nmId'      => $item['nmId'] ?? null,
                'errorText' => $item['errorText'] ?? 'Неизвестная ошибка',
            ]);
        }
    }

    public function getUploadDetails(string $wbToken, int $uploadId): array
    {
        $endpoint = 'https://discounts-prices-api.wildberries.ru/api/v2/history/goods/task';
        try {
            $resp = $this->http->get($endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$wbToken}",
                    'Accept'        => 'application/json',
                ],
                'query' => [
                    'uploadID' => $uploadId,
                    'limit'    => 1000,
                    'offset'   => 0,
                ],
                'http_errors' => false,
                'timeout' => 60,
                'verify'  => false,
            ]);

            $code = $resp->getStatusCode();
            $body = (string) $resp->getBody();
            $json = json_decode($body, true);

            Log::info('[WB] getUploadDetails response body', [
                'json' => $json,
            ]);

            if ($code >= 400) {
                Log::error('[WB] getUploadDetails HTTP error', [
                    'code' => $code,
                    'body' => $json,
                ]);

                return [
                    'uploadId' => $uploadId,
                    'error'    => true,
                    'errorText'=> "WB API error {$code}",
                    'items'    => [],
                ];
            }

            if (!empty($json['error']) && $json['error'] === true) {
                Log::error('[WB] getUploadDetails API error', [
                    'errorText' => $json['errorText'] ?? 'Unknown WB API error',
                ]);

                return [
                    'uploadId' => $uploadId,
                    'error'    => true,
                    'errorText'=> $json['errorText'] ?? 'Unknown WB API error',
                    'items'    => [],
                ];
            }

            $data = $json['data'] ?? [];
            $history = $data['historyGoods'] ?? [];

            // парсим товары
            $items = collect($history)->map(function ($item) {
                return [
                    'nmId'         => (int)($item['nmID'] ?? 0),
                    'vendorCode'   => $item['vendorCode'] ?? null,
                    'sizeID'       => $item['sizeID'] ?? null,
                    'techSizeName' => $item['techSizeName'] ?? null,
                    'price'        => (float)($item['price'] ?? 0),
                    'discount'     => (int)($item['discount'] ?? 0),
                    'clubDiscount' => (int)($item['clubDiscount'] ?? 0),
                    'status'       => (int)($item['status'] ?? 0),
                    'errorText'    => $item['errorText'] ?? null,
                ];
            })->values()->all();

            Log::info('[WB] getUploadDetails success', [
                'uploadId' => $uploadId,
                'count'    => count($items),
            ]);

            return [
                'uploadId'  => $data['uploadID'] ?? $uploadId,
                'error'     => false,
                'errorText' => null,
                'items'     => $items,
            ];
        } catch (\Throwable $e) {
            Log::error('[WB] getUploadDetails exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'uploadId'  => $uploadId,
                'error'     => true,
                'errorText' => $e->getMessage(),
                'items'     => [],
            ];
        }
    }

    public function delete(int $id): bool
    {
        $strategy = PricingStrategy::find($id);
        if (! $strategy) {
            return false;
        }

        return (bool) $strategy->delete();
    }

    private function updateStrategyItemStatus(int $id, string $status): array
    {
        try {
            if (!in_array($status, StrategyItem::getAllowedStatuses(), true)) {
                throw new InvalidArgumentException("Недопустимый статус: {$status}");
            }

            if ($id <= 0) {
                throw new InvalidArgumentException("Некорректный Id стратегии: {$id}");
            }

            $updated = StrategyItem::where('pricing_strategy_id', $id)
                ->update(['status' => $status]);

            if ($updated <= 0) {
                throw new RuntimeException("Не удалось обновить статус записи StrategyItem Id: {$id}.");
            }

            return [
                'success' => true,
                'message' => "",
            ];
        } catch (\Throwable $e) {
            Log::critical('Ошибка при обновлении статуса StrategyItem', [
                'strategy_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Произошла непредвиденная ошибка.',
            ];
        }
    }

    private function updateStrategyItemsStatus(array $items): array
    {
        try {
            if (empty($items)) {
                return [
                    'success' => true,
                    'updated' => false,
                    'message' => 'Нет элементов для обновления.',
                ];
            }

            $allowed = StrategyItem::getAllowedStatuses();
            $invalid = collect($items)->pluck('status')->filter(fn($s) => !in_array($s, $allowed, true));

            if ($invalid->isNotEmpty()) {
                throw new InvalidArgumentException('Недопустимые статусы: ' . $invalid->join(', '));
            }

            $updatedCount = 0;

            $grouped = collect($items)->groupBy('status');
            foreach ($grouped as $status => $group) {
                $ids = $group->pluck('id')->all();

                $updated = StrategyItem::whereIn('id', $ids)->update(['status' => $status]);
                $updatedCount += $updated;

                Log::info('Обновлены статусы StrategyItem', [
                    'ids' => $ids,
                    'status' => $status,
                    'updated' => $updated,
                ]);
            }

            return [
                'success' => true,
                'updated' => $updatedCount,
                'message' => "Обновлено {$updatedCount} записей со статусами.",
            ];
        } catch (Throwable $e) {
            Log::error('Ошибка при обновлении статусов StrategyItem', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'updated' => 0,
                'message' => 'Ошибка при обновлении статусов StrategyItem.',
            ];
        }
    }
}