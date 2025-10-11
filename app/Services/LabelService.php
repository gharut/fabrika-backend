<?php
namespace App\Services;

use App\Models\Label;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Illuminate\Support\Facades\Auth;

class LabelService
{
    public function getAll(?int $clientId = null, ?string $name = null, int $perPage = 15): LengthAwarePaginator
    {
        return Label::with(['product', 'client'])
            ->when($clientId, function ($query) use ($clientId) {
                $query->whereHas('product', function ($q) use ($clientId) {
                    $q->where('client_id', $clientId);
                });
            })
            ->when($name, function ($query) use ($name) {
                $query->where('name', 'like', '%' . $name . '%');
            })
            ->paginate($perPage);
    }

    public function getFiltered(array $filters, string $sortBy, string $sortDir): Collection
    {
        $query = Label::with(['product', 'client']);

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $op = $filter['op'] ?? 'eq';
            $value = $filter['value'] ?? null;

            if (!$field || $value === null) continue;

            if (str_contains($field, '.')) {
                [$relation, $subField] = explode('.', $field, 2);
                $query->whereHas($relation, function ($q) use ($subField, $op, $value) {
                    match ($op) {
                        'eq'  => $q->where($subField, '=', $value),
                        'ne'  => $q->where($subField, '!=', $value),
                        'like' => $q->where($subField, 'like', '%' . $value . '%'),
                    };
                });
            } else {
                match ($op) {
                    'eq'  => $query->where($field, '=', $value),
                    'ne'  => $query->where($field, '!=', $value),
                    'like' => $query->where($field, 'like', '%' . $value . '%'),
                };
            }
        }

        $sortDir = in_array(strtolower($sortDir), ['asc', 'desc']) ? strtolower($sortDir) : 'asc';
        if (str_contains($sortBy, '.')) {
            [$relation, $column] = explode('.', $sortBy, 2);

            match ($relation) {
                'product' => $query
                    ->join('wb_products', 'labels.product_id', '=', 'wb_products.id')
                    ->orderBy("wb_products.$column", $sortDir),

                'client' => $query
                    ->join('clients', 'labels.client_id', '=', 'clients.id')
                    ->orderBy("clients.$column", $sortDir),

                default => $query->orderBy('labels.id'),
            };

            $query->select('labels.*');
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        return $query->get();
    }

    public function getOne(int $id): Label
    {
        $label = Label::with(['product','client','creator','editor'])
                    ->findOrFail($id);
        $label->loadMissing('product.brand');
        return $label;
    }

    public function create(array $data): Label
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $label = Label::create($data);
        return $label->loadMissing(['product']);
    }

    public function update(int $id, array $data): Label
    {
        $label = Label::findOrFail($id);
        $data['updated_by'] = Auth::id();

        $label->fill($data)->save();
         return $label->fresh(['product']);
    }

    public function delete(int $id): void
    {
        $label = Label::findOrFail($id);
        $label->delete();
    }
}
