<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    public function getAll(?int $clientId = null): Collection
    {
        $query = Product::with(['children','services']);

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        return $query->get();
    }

    public function getById(int $id): Product
    {
        return Product::with(['children', 'services'])->findOrFail($id);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(int $id, array $data): Product
    {
        $product = $this->getById($id);
        $product->update($data);
        return $product;
    }

    public function delete(int $id): void
    {
        $product = $this->getById($id);
        $product->delete();
    }
}