<?php

namespace App\Services;

use App\Models\Supplier;

final class SupplierService
{
    // public function getAll()
    // {
    //     return Supplier::orderBy('id', 'desc')->get();
    // }
    //
    // public function getOne(int $id)
    // {
    //     return Supplier::findOrFail($id);
    // }

    public function getSelection()
    {
        return Supplier::select('id as value', 'name as key')->orderBy('id', 'desc')->get()->toArray();
    }

    // public function store(array $data)
    // {
    //     return Supplier::create($data);
    // }
    //
    // public function update(int $id, array $data)
    // {
    //     $item = Supplier::findOrFail($id);
    //     return $item->update($data);
    // }
    //
    // public function delete(int $id)
    // {
    //     $item = Supplier::findOrFail($id);
    //     $item->delete();
    // }
}
