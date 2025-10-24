<?php

namespace App\Services;

use App\Models\StrategyItem;
use App\Models\WbProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StrategyItemService
{
    public function bulkCreate(int $strategyId, array $items): int
    {
        $now = now();
        $payload = array_map(fn($i) => [
            'strategy_id'   => $strategyId,
            'model_type'    => WbProduct::class,
            'model_id'      => (int)$i['model_id'],
            'status'        => StrategyItem::STATUS_ACTIVE,
            'temp_discount' => null,
            'starts_at'     => null,
            'ends_at'       => null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ], $items);

        return DB::transaction(function () use ($payload) {
            StrategyItem::upsert(
                $payload,
                ['strategy_id','model_type','model_id'],
                ['status','temp_discount','starts_at','ends_at','updated_at']
            );
            return count($payload);
        });
    }
    
    public function listByStrategy(int $strategyId, int $perPage = 50): LengthAwarePaginator
    {
        return StrategyItem::query()
            ->with([
                'inventoryLevels',
                'wbProduct' => function ($q) {
                    $q->select('id', 'article', 'vendor_code', 'name', 'color')
                    ->addSelect([
                        'image' => \App\Models\ProductImage::select('url')
                            ->whereColumn('product_id', 'wb_products.id')
                            ->orderBy('position')
                            ->limit(1)
                    ]);
                }
            ])
            ->where('strategy_id', $strategyId)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function getAvailableProducts(int $strategyId, int $perPage = 50): LengthAwarePaginator
    {
        $list = WbProduct::query()
            ->whereNotIn('id', function ($q) use ($strategyId) {
                $q->select('model_id')
                  ->from('strategy_items')
                  ->where('strategy_id', $strategyId)
                  ->where('model_type', \App\Models\WbProduct::class);
            })
            ->with([
                'mainImage:id,product_id,url',
                'inventoryLevels:id,product_id,qty',
                'productPrices' => function ($q) {
                    $q->where('type', 'discount');
                },
            ])
            ->select('id', 'article', 'vendor_code', 'name', 'color')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
        
        $list->getCollection()->transform(function ($p) {
            return [
                'id'            => $p->id,
                'article'       => $p->article,
                'vendor_code'   => $p->vendor_code,
                'name'          => $p->name,
                'color'         => $p->color,
                'category_name' => $p->wb_category['name'],
                'image'         => $p->mainImage?->url,
                'discount'      => (float) ($p->productPrices->firstWhere('type', 'discount')->value ?? 0),
                'stock'         => (int) ($p->inventoryLevels->sum('qty') ?? 0),
            ];
        });

        return $list;
    }

    public function update(int $id, array $data): bool
    {
        $allowed = [
            'temp_discount',
            'starts_at',
            'ends_at',
            'status',
        ];

        $fields = array_intersect_key($data, array_flip($allowed));

        if (empty($fields)) {
            return false;
        }

        return StrategyItem::where('id', $id)->update($fields) > 0;
    }

    public function delete(int $id): bool
    {
        $item = StrategyItem::find($id);
        if (! $item) {
            return false;
        }

        return (bool) $item->delete();
    }
}