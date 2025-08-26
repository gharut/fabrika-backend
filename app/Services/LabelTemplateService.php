<?php
namespace App\Services;

use App\Models\LabelTemplate;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Illuminate\Support\Facades\Auth;

class LabelTemplateService
{   
    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return LabelTemplate::with(['creator','editor'])->paginate($perPage);
    }

    public function getOne(int $id): LabelTemplate
    {
        return LabelTemplate::with(['creator','editor'])
            ->findOrFail($id);
    }

    public function create(array $data): LabelTemplate
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $label = LabelTemplate::create($data);
        return $label;
    }

    public function update(int $id, array $data): LabelTemplate
    {
        $label = LabelTemplate::findOrFail($id);
        $data['updated_by'] = Auth::id();

        $label->fill($data)->save();    
        return $label;
    }

    public function delete(int $id): void
    {
        $label = LabelTemplate::findOrFail($id);
        $label->delete();
    }
}
