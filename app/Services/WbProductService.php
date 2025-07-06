<?php

namespace App\Services;

use App\Models\WbProduct;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class WbProductService
{
    public function getAll(?int $clientId = null, ?string $name = null): Collection
    {
        $query = WbProduct::with(['client']);

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
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();
        return WbProduct::create($data);
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