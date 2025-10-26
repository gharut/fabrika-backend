<?php

namespace App\Services;

use App\Models\ProductSize;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ProductSizeService
{
    public function getAll(?int $productId, int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductSize::with(['product'])
            ->withCount([
                'chestnyZnakLabels as available_count' => function($q) {
                    $q->where('status', 'available');
                },
                'chestnyZnakLabels as used_count' => function($q) {
                    $q->where('status', 'used');
                },
                'chestnyZnakLabels as total_count'
            ])
            ->withSum('inventoryLevels as stock', 'qty');;
        
            if ($productId !== null) {
            $query->where('product_id', $productId);
        }

        return $query->paginate($perPage);
    }

    public function getOne(int $id): ProductSize
    {
        return ProductSize::with(['product'])
            ->withCount([
                'chestnyZnakLabels as available_count' => function($q) {
                    $q->where('status', 'available');
                },
                'chestnyZnakLabels as used_count' => function($q) {
                    $q->where('status', 'used');
                },
                'chestnyZnakLabels as total_count'
            ])
            ->findOrFail($id);
    }

    public function create(array $data): ProductSize
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return ProductSize::create($data);
    }

    public function update(int $id, array $data): ProductSize
    {
        $size = ProductSize::findOrFail($id);
        $data['updated_by'] = Auth::id();

        $size->fill($data)->save();
        return $size;
    }

    public function delete(int $id): void
    {
        $size = ProductSize::findOrFail($id);
        $size->delete();
    }
}
