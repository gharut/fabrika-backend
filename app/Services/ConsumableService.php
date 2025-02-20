<?php

namespace App\Services;

use App\Models\Consumable;

final class ConsumableService
{
    // public function getAll()
    // {
    //     return Consumable::orderBy('id', 'desc')->get();
    // }
    //
    // public function getOne(int $id)
    // {
    //     return Consumable::findOrFail($id);
    // }

    public function getSelection()
    {
        return Consumable::select('id as value', 'size as key')->orderBy('id', 'desc')->get()->toArray();
    }

    // public function store(array $data)
    // {
    //     return Consumable::create($data);
    // }
    //
    // public function update(int $id, array $data)
    // {
    //     $item = Consumable::findOrFail($id);
    //     return $item->update($data);
    // }
    //
    // public function delete(int $id)
    // {
    //     $item = Consumable::findOrFail($id);
    //     $item->delete();
    // }
}
