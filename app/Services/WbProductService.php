<?php

namespace App\Services;

use App\Models\WbProduct;
use App\Models\Label;
use App\Models\ProductImage;
use App\Models\ProductMarketplaceCategory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WbProductService
{
    public function getAll(?int $clientId = null, ?int $productId = null, ?string $name = null): Collection
    {
        $query = WbProduct::with(['client', 'brand']);

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        if ($productId !== null) {
            $query->where('id', '!=', $productId);
        }

        if (!empty($name)) {
            $query->where('name', 'like', '%' . $name . '%');
        }

        return $query->get();
    }
    
    public function getAllWithSizes(
        array $filters,
        string $sortBy,
        string $sortDir,
        int $perPage = 15,
        int $page = 1
    ): LengthAwarePaginator
    {
        $sortDir = in_array(strtolower($sortDir), ['asc','desc']) ? $sortDir : 'asc';

        $query = WbProduct::query()
            ->with([
                'client',
                'brand',
                'labels',
                'tags',
                'sizes' => fn($q) => $q->select('id','product_id','barcode','value')
                    ->withCount([
                        'chestnyZnakLabels as available_labels_count' => fn($q) => $q->where('status', 'available')
                    ]),
                'wbCategoryLink.category',
            ])
            ->addSelect([
                'main_image_url' => ProductImage::select('url')
                    ->whereColumn('product_id', 'wb_products.id')
                    ->orderBy('position')
                    ->limit(1)
            ]);

        $applyFilter = function ($q, $filter) use (&$applyFilter) {
            $field = $filter['field'] ?? null;
            $op    = $filter['op'] ?? 'eq';
            $value = $filter['value'] ?? null;
            if (!$field || $value === null) return;

            // Специальная обработка для фильтрации по категории
            if ($field === 'category' || $field === 'wb_category') {
                $q->whereHas('wbCategoryLink.category', function ($categoryQuery) use ($op, $value) {
                    switch ($op) {
                        case 'eq':   $categoryQuery->where('name', '=', $value); break;
                        case 'ne':   $categoryQuery->where('name', '!=', $value); break;
                        case 'like': $categoryQuery->where('name', 'like', '%'.$value.'%'); break;
                        case 'in':   $categoryQuery->whereIn('name', (array)$value); break;
                        default:     $categoryQuery->where('name', '=', $value); break;
                    }
                });
            } else {
                // Обычная фильтрация по другим полям
                switch ($op) {
                    case 'eq':   $q->where($field, '=', $value); break;
                    case 'ne':   $q->where($field, '!=', $value); break;
                    case 'like': $q->where($field, 'like', '%'.$value.'%'); break;
                    case 'in':   $q->whereIn($field, (array)$value); break;
                }
            }
        };

        foreach ($filters as $filter) {
            if (isset($filter['group'], $filter['filters'])) {
                $group = strtolower($filter['group']);
                $sub   = $filter['filters'];
                $query->where(function ($q) use ($sub, $group, $applyFilter) {
                    foreach ($sub as $i => $sf) {
                        if ($group === 'or' && $i > 0) $q->orWhere(fn($sq) => $applyFilter($sq, $sf));
                        else                           $applyFilter($q, $sf);
                    }
                });
            } else {
                $applyFilter($query, $filter);
            }
        }

        if ($sortBy === 'wb_category') {
            $query->leftJoin('product_marketplace_categories as pmc', function ($join) {
                    $join->on('pmc.product_id', '=', 'wb_products.id')
                        ->where('pmc.marketplace_code', '=', 'wb');
                })
                ->leftJoin('marketplace_categories as mc', 'mc.id', '=', 'pmc.marketplace_category_id')
                ->select('wb_products.*')
                ->orderBy('mc.name', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getById(int $id): WbProduct
    {
        return WbProduct::with([
            'client',
            'brand',
            'labels',
            'tags',
            'sizes' => fn($q) => $q->select('id', 'product_id', 'barcode', 'value')
        ])->findOrFail($id);
    }

    public function create(array $data): WbProduct
    {
        $currentUserId = Auth::id();
        return DB::transaction(function () use ($data, $currentUserId) {
            $data['created_by'] = $currentUserId;
            $data['updated_by'] = $currentUserId;
            
            $categoryId = $data['category_id'] ?? null;
            unset($data['category_id']);
            
            $product = WbProduct::create($data);
            $clientName = $product->client ? $product->client->name : '';

            Label::create([
                'name'        => $product->name,
                'client_name' => $clientName,
                'created_by'  => $currentUserId,
                'updated_by'  => $currentUserId,
                'product_id'  => $product->id,
                'label_template_id' => 1,
            ]);

            if ($categoryId) {
                ProductMarketplaceCategory::create([
                    'product_id' => $product->id,
                    'marketplace_category_id' => $categoryId,
                    'marketplace_code' => 'wb',
                    'created_by' => $currentUserId,
                    'updated_by' => $currentUserId,
                ]);
            }

            return $product;
        });
    }

    public function update(int $id, array $data): WbProduct
    {   
        $currentUserId = Auth::id();

        return DB::transaction(function () use ($id, $data, $currentUserId) {
            $wbProduct = $this->getById($id);
            $data['updated_by'] = $currentUserId;
            
            $categoryId = $data['category_id'] ?? null;
            unset($data['category_id']);
                
            $wbProduct->update($data);
            $existingCategory = $wbProduct->wbCategories()->first();

            if ($categoryId) {
                if ($existingCategory) {
                    $existingCategory->update([
                        'marketplace_category_id' => $categoryId,
                        'updated_by' => $currentUserId,
                    ]);
                } else {
                    $wbProduct->wbCategories()->create([
                        'marketplace_category_id' => $categoryId,
                        'marketplace_code' => 'wb',
                        'created_by' => $currentUserId,
                        'updated_by' => $currentUserId,
                    ]);
                }
            }

            return $wbProduct;
        });
    }

    public function delete(int $id): void
    {
        $wbProduct = $this->getById($id);
        $wbProduct->delete();
    }
}