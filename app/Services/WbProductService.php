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
        $query = WbProduct::with(['client']);

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

    public function getAllWithSizes(?int $clientId = null, ?string $name = null): Collection
    {
         $query = WbProduct::with([
            'client',
            'sizes' => function ($q) {
                $q->select('id', 'product_id', 'barcode', 'value');
            }
        ]);

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        if (!empty($name)) {
            $query->where('name', 'like', '%' . $name . '%');
        }

        return $query->get();
    }

    public function getById(int $id): WbProduct
    {
        return WbProduct::with(['client'])->findOrFail($id);
    }

    public function create(array $data): WbProduct
    {
        $currentUserId = Auth::id();
        return DB::transaction(function () use ($data, $currentUserId) {
            $data['created_by'] = $currentUserId;
            $data['updated_by'] = $currentUserId;
            $product = WbProduct::create($data);

            Label::create([
                'name'       => $product->name,
                'created_by' => $currentUserId,
                'updated_by' => $currentUserId,
                'product_id' => $product->id,
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