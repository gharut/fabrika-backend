<?php

namespace App\Services;

use App\Models\WbProduct;
use App\Models\Label;
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

    // public function getAllWithSizes(array $filters, string $sortBy, string $sortDir): Collection
    // {
    //     $query = WbProduct::with([
    //         'client',
    //         'brand',
    //         'sizes' => fn($q) =>
    //             $q->select('id', 'product_id', 'barcode', 'value')
    //             ->withCount([
    //                 'chestnyZnakLabels as available_labels_count' => fn($q) =>
    //                     $q->where('used', false)
    //             ])
    //     ]);

    //     foreach ($filters as $filter) {
    //         $field = $filter['field'] ?? null;
    //         $op = $filter['op'] ?? 'eq';
    //         $value = $filter['value'] ?? null;

    //         if (!$field || $value === null) continue;

    //         switch ($op) {
    //             case 'eq':
    //                 $query->where($field, '=', $value);
    //                 break;
    //             case 'ne':
    //                 $query->where($field, '!=', $value);
    //                 break;
    //             case 'like':
    //                 $query->where($field, 'like', '%' . $value . '%');
    //                 break;
    //         }
    //     }

    //     $sortDir = in_array(strtolower($sortDir), ['asc', 'desc']) ? $sortDir : 'asc';
    //     if ($sortBy === 'category') {
    //         $query->orderByRaw("
    //             CASE category
    //                 WHEN 'clothes' THEN 'Одежда'
    //                 WHEN 'shoes' THEN 'Обувь'
    //                 ELSE category
    //             END $sortDir
    //         ");
    //     } else {
    //         $query->orderBy($sortBy, $sortDir);
    //     }

    //     return $query->get();
    // }
    
    public function getAllWithSizes(array $filters, string $sortBy, string $sortDir): Collection
    {
        $query = WbProduct::with([
            'client',
            'brand',
            'sizes' => fn($q) =>
                $q->select('id', 'product_id', 'barcode', 'value')
                ->withCount([
                    'chestnyZnakLabels as available_labels_count' => fn($q) =>
                        $q->where('used', false)
                ])
        ]);

        $applyFilter = function ($q, $filter) use (&$applyFilter) {
            $field = $filter['field'] ?? null;
            $op = $filter['op'] ?? 'eq';
            $value = $filter['value'] ?? null;

            if (!$field || $value === null) return;

            switch ($op) {
                case 'eq': $q->where($field, '=', $value); break;
                case 'ne': $q->where($field, '!=', $value); break;
                case 'like': $q->where($field, 'like', '%' . $value . '%'); break;
            }
        };

        foreach ($filters as $filter) {
            // группа (OR/AND)
            if (isset($filter['group']) && isset($filter['filters'])) {
                $group = strtolower($filter['group']);
                $subFilters = $filter['filters'];

                $query->where(function ($q) use ($subFilters, $group, $applyFilter) {
                    foreach ($subFilters as $i => $subFilter) {
                        if ($group === 'or' && $i === 0) {
                            // первый условие
                            $applyFilter($q, $subFilter);
                        } elseif ($group === 'or') {
                            $q->orWhere(function ($sq) use ($subFilter, $applyFilter) {
                                $applyFilter($sq, $subFilter);
                            });
                        } else {
                            // AND
                            $applyFilter($q, $subFilter);
                        }
                    }
                });
            } else {
                $applyFilter($query, $filter);
            }
        }

        $sortDir = in_array(strtolower($sortDir), ['asc', 'desc']) ? $sortDir : 'asc';
        if ($sortBy === 'category') {
            $query->orderByRaw("
                CASE category
                    WHEN 'clothes' THEN 'Одежда'
                    WHEN 'shoes' THEN 'Обувь'
                    ELSE category
                END $sortDir
            ");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        return $query->get();
    }

    public function getById(int $id): WbProduct
    {
        return WbProduct::with([
            'client',
            'brand',
            'sizes' => fn($q) => $q->select('id', 'product_id', 'barcode', 'value')
        ])->findOrFail($id);
    }

    public function create(array $data): WbProduct
    {
        $currentUserId = Auth::id();
        return DB::transaction(function () use ($data, $currentUserId) {
            $data['created_by'] = $currentUserId;
            $data['updated_by'] = $currentUserId;
            $product = WbProduct::create($data);
            $clientName = $product->client ? $product->client->name : '';

            Label::create([
                'name'        => $product->name,
                'client_name' => $clientName,
                'created_by'  => $currentUserId,
                'updated_by'  => $currentUserId,
                'product_id'  => $product->id,
            ]);

            return $product;
        });
    }

    public function update(int $id, array $data): WbProduct
    {   
        
        $wbProduct = $this->getById($id);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();
        $wbProduct->update($data);
        return $wbProduct;
    }

    public function delete(int $id): void
    {
        $wbProduct = $this->getById($id);
        $wbProduct->delete();
    }
}