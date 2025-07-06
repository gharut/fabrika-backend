<?php

namespace App\Services;

use App\Models\Printer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class PrinterService
{
    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return Printer::with(['warehouse','creator','editor'])->paginate($perPage);
    }


    public function getOne(int $id): Printer
    {
        return Printer::with(['warehouse','creator','editor'])->findOrFail($id);
    }

    public function create(array $data): Printer
    {
        $data = array_merge($data, [
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return Printer::create($data);
    }

    public function update(int $id, array $data): Printer
    {
        $printer = Printer::findOrFail($id);
        $data['updated_by'] = Auth::id();

        $printer->fill($data)->save();
        return $printer;
    }

    public function delete(int $id): void
    {
        $printer = Printer::findOrFail($id);
        $printer->delete();
    }

    public function setLabelsCount(int $id, int $newCount): Printer
    {
        if ($newCount < 0) {
            throw new InvalidArgumentException('Labels count must be zero or positive.');
        }

        $printer = Printer::findOrFail($id);
        $printer->labels_count = $newCount;
        $printer->save();
        return $printer;
    }
}
