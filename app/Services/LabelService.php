<?php
namespace App\Services;

use App\Models\Label;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class LabelService
{
    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return Label::with(['product','client','creator','editor'])
                    ->paginate($perPage);
    }

    public function getOne(int $id): Label
    {
        return Label::with(['product','client','creator','editor'])
                    ->findOrFail($id);
    }

    public function create(array $data): Label
    {
        $data['created_by'] = 1;
        $data['updated_by'] = 1;

        return Label::create($data);
    }

    public function update(int $id, array $data): Label
    {
        $label = Label::findOrFail($id);
        $data['updated_by'] = 1;

        $label->fill($data)->save();
        return $label;
    }

    public function delete(int $id): void
    {
        $label = Label::findOrFail($id);
        $label->delete();
    }
}
