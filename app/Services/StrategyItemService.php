<?php

namespace App\Services;

use App\Models\StrategyItem;
use App\Models\PricingStrategy;
use App\Models\WbProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StrategyItemService
{
    public function bulkCreate(int $strategyId, array $items): object
    {
        $result = (object)[
            'success' => false,
            'created' => 0,
            'message' => '',
        ];

        try {
            $strategy = PricingStrategy::select('id', 'account_id')->find($strategyId);

            if (empty($strategy)) {
                $result->message = 'Стратегия не найдена.';
                return $result;
            }

            $strategyAccountId = $strategy->account_id;
            if (empty($strategyAccountId)) {
                $result->message = 'У стратегии не указан кабинет маркетплейса.';
                return $result;
            }

            $productIds = collect($items)
                ->pluck('model_id')
                ->map(fn($v) => (int)$v)
                ->unique()
                ->toArray();

            $validIds = WbProduct::query()
                ->where('account_id', $strategyAccountId)
                ->whereNotNull('account_id')
                ->whereIn('id', $productIds)
                ->pluck('id')
                ->toArray();

            $now = now();
            $created = DB::transaction(function () use ($strategyId, $validIds, $now) {
                return StrategyItem::upsert(
                    collect($validIds)->map(fn($id) => [
                        'strategy_id'   => $strategyId,
                        'model_type'    => WbProduct::class,
                        'model_id'      => $id,
                        'status'        => StrategyItem::STATUS_PAUSED,
                        'temp_discount' => 0,
                        'starts_at'     => null,
                        'ends_at'       => null,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ])->toArray(),
                    ['strategy_id', 'model_type', 'model_id'],
                    []
                );
            });

            $result->success = true;
            $result->created = $created;
            $result->message = "Добавлено {$created} товаров.";
        } catch (\Throwable $e) {
            $result->success = false;
            $result->created = $result->created ?? 0;
            $result->message = 'Ошибка: ' . $e->getMessage();
        }

        return $result;
    }
    
    public function updateItemsTime(int $strategyId, string $field, string $value): int
    {
        if (!in_array($field, ['starts_at', 'ends_at'])) {
            return 0;
        }

        if (!preg_match('/^([0-1][0-9]|2[0-3]):(00|30)$/', $value)) {
            return 0;
        }

        $updatedCount = StrategyItem::where('strategy_id', $strategyId)
            ->where('status', '!=', StrategyItem::STATUS_APPLIED)
            ->update([$field => $value]);

        return $updatedCount;
    }

    public function listByStrategy(
        int $strategyId,
        array $filters = [],
        string $sortBy = 'name',
        string $sortDir = 'asc',
        int $page = 1,
        int $perPage = 50
    ): LengthAwarePaginator {
        $allowedSorts = [
            'name'         => 'wb_products.name',
            'temp_discount'=> 'temp_discount',
            'date_start'   => 'date_start',
            'date_end'     => 'date_end',
            'status'       => 'status',
        ];

        $query = StrategyItem::query()
            ->where('strategy_id', $strategyId)
            ->with([
                'inventoryLevels',
                'wbProduct' => function ($q) {
                    $q->select('id', 'article', 'vendor_code', 'name', 'color')
                    ->with([
                        'wbCategoryLink.category:id,name',
                    ])    
                    ->addSelect([
                            'image' => \App\Models\ProductImage::select('url')
                                ->whereColumn('product_id', 'wb_products.id')
                                ->orderBy('position')
                                ->limit(1)
                        ]);
                }
            ]);

        $query->whereHas('wbProduct', function (Builder $q) use ($filters) {
            foreach ($filters as $filter) {
                $field = $filter['field'] ?? null;
                $value = trim($filter['value'] ?? '');
                if (!$field || $value === '') continue;

                switch ($field) {
                    case 'name':
                        $q->where('name', 'like', "%{$value}%");
                        break;

                    case 'article':
                        $q->where(function ($q2) use ($value) {
                            $q2->where('article', 'like', "%{$value}%")
                            ->orWhere('vendor_code', 'like', "%{$value}%");
                        });
                        break;

                    case 'category':
                        $q->whereHas('wbCategoryLink.category', function ($catQuery) use ($value) {
                            $catQuery->where('name', 'like', "%{$value}%");
                        });
                        break;
                }
            }
        });

        if ($sortBy && isset($allowedSorts[$sortBy])) {
            $sortColumn = $allowedSorts[$sortBy];

            if (str_starts_with($sortColumn, 'wb_products.')) {
                $query->join('wb_products', 'strategy_items.model_id', '=', 'wb_products.id')
                    ->select('strategy_items.*')
                    ->orderBy($sortColumn, $sortDir === 'desc' ? 'desc' : 'asc');
            } else {
                $query->orderBy($sortColumn, $sortDir === 'desc' ? 'desc' : 'asc');
            }
        } else {
            $query->orderBy('strategy_items.id', 'desc');
        }

        $items = $query->paginate($perPage, ['*'], 'page', $page);
        $collection = $items->getCollection();

        switch ($sortBy) {
            case 'stock':
                $collection = $collection->sortBy(
                    fn($item) => $item->inventoryLevels->sum('qty'),
                    SORT_REGULAR,
                    $sortDir === 'desc'
                )->values();
                break;

            case 'discount':
                $collection = $collection->sortBy(
                    fn($item) => $item->discount,
                    SORT_REGULAR,
                    $sortDir === 'desc'
                )->values();
                break;
        }

        $items->setCollection($collection);
        return $items;
    }

    public function getAvailableProducts(
        int $strategyId,
        array $filters = [],
        string $sortBy = 'name',
        string $sortDir = 'asc',
        int $perPage = 10,
        int $page = 1
    ): LengthAwarePaginator {
        $strategy = PricingStrategy::select('id', 'account_id')->find($strategyId);
        if (!$strategy || empty($strategy->account_id)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, $page);
        }

        $strategyAccountId = $strategy->account_id;
        $allowedSorts = [
            'name'     => 'wb_products.name',
            'stock'    => null,
            'discount' => null,
            'category_name' => null,
        ];
        
        $query = WbProduct::query()
            ->where('account_id', $strategyAccountId)
            ->whereNotIn('id', function ($q) use ($strategyId) {
                $q->select('model_id')
                    ->from('strategy_items')
                    ->where('strategy_id', $strategyId)
                    ->where('model_type', \App\Models\WbProduct::class);
            })
            ->with([
                'mainImage:id,product_id,url',
                'inventoryLevels:id,product_id,qty',
                'productPrices' => fn($q) => $q->where('type', 'discount'),
                'wbCategoryLink.category:id,name',
            ])
            ->select('wb_products.id', 'article', 'vendor_code', 'name', 'color');

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $value = trim($filter['value'] ?? '');
            if (!$field || $value === '') continue;

            switch ($field) {
                case 'name':
                    $query->where('name', 'like', "%{$value}%");
                    break;

                case 'article':
                    $query->where(function ($q) use ($value) {
                        $q->where('article', 'like', "%{$value}%")
                            ->orWhere('vendor_code', 'like', "%{$value}%");
                    });
                    break;

                case 'category':
                    $query->whereHas('wbCategoryLink.category', function ($catQ) use ($value) {
                        $catQ->where('name', 'like', "%{$value}%");
                    });
                    break;
            }
        }

        if ($sortBy && isset($allowedSorts[$sortBy]) && $allowedSorts[$sortBy]) {
            $sortColumn = $allowedSorts[$sortBy];
            $query->orderBy($sortColumn, $sortDir);
        } else {
            $query->orderBy('wb_products.id', 'desc');
        }

        $items = $query->paginate($perPage, ['*'], 'page', $page);
        $collection = $items->getCollection();

        switch ($sortBy) {
            case 'stock':
                $collection = $collection->sortBy(
                    fn($item) => $item->inventoryLevels->sum('qty'),
                    SORT_REGULAR,
                    $sortDir === 'desc'
                )->values();
                break;

            case 'discount':
                $collection = $collection->sortBy(
                    fn($item) => (float) ($item->productPrices->first()->value ?? 0),
                    SORT_REGULAR,
                    $sortDir === 'desc'
                )->values();
                break;

            case 'category_name':
                $collection = $collection->sortBy(
                    fn($item) => optional($item->wbCategoryLink->category)->name ?? '',
                    SORT_NATURAL,
                    $sortDir === 'desc'
                )->values();
                break;
        }

        $items->setCollection($collection);

        $items->getCollection()->transform(function ($p) {
            return [
                'id'            => $p->id,
                'article'       => $p->article,
                'vendor_code'   => $p->vendor_code,
                'name'          => $p->name,
                'color'         => $p->color,
                'category_name' => optional($p->wbCategoryLink->category)->name,
                'image'         => $p->mainImage?->url,
                'discount'      => (float) ($p->productPrices->first()->value ?? 0),
                'stock'         => (int) ($p->inventoryLevels->sum('qty') ?? 0),
            ];
        });

        return $items;
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