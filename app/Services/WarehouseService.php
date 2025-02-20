<?php

namespace App\Services;

use App\Models\Warehouse;

final class WarehouseService
{
    public function getAll()
    {
        return Warehouse::with(['consumable', 'supplier', 'prices'])->orderBy('id', 'desc')->get();
    }

    public function getOne(int $id)
    {
        return Warehouse::findOrFail($id);
    }

    public function store(array $data)
    {
        $item = Warehouse::create($data);

        if (!empty($data['prices'])) {
            $item->prices()->createMany($data['prices']);
        }

        return $item->load(['consumable', 'supplier', 'prices']);
    }

    public function update(int $id, array $data)
    {
        $item = Warehouse::with(['consumable', 'supplier', 'prices'])->findOrFail($id);
        $item->update($data);

        if (isset($data['prices'])) {
            $existingPriceIds = $item->prices()->pluck('id')->toArray();
            $newPriceIds = array_filter(array_column($data['prices'], 'id'));

            $pricesToDelete = array_diff($existingPriceIds, $newPriceIds);
            if (!empty($pricesToDelete)) {
                $item->prices()->whereIn('id', $pricesToDelete)->delete();
            }

            foreach ($data['prices'] as $priceData) {
                if (isset($priceData['id'])) {
                    $price = $item->prices()->find($priceData['id']);
                    if ($price) {
                        $price->update($priceData);
                    }
                } else {
                    $item->prices()->create($priceData);
                }
            }
        }

        return $item->load('prices');
    }

    public function delete(int $id)
    {
        $item = Warehouse::findOrFail($id);
        $item->delete();
    }
}
