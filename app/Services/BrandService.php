<?php

namespace App\Services;

use App\Models\Brand;

class BrandService
{
    public function create(array $data): Brand
    {
        $userId = auth()->id();
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        return Brand::create($data);
    }

    public function update(Brand $brand, array $data): Brand
    {
        $data['updated_by'] = auth()->id();
        $brand->update($data);
        return $brand->refresh();
    }

    public function delete(Brand $brand): void
    {
        $brand->delete();
    }
}
