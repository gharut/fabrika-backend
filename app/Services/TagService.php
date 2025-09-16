<?php

namespace App\Services;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TagService
{
    public function list()
    {
        return Tag::orderBy('name')->get();
    }

    public function findById(int $id): ?Tag
    {
        return Tag::find($id);
    }

    public function create(array $data): Tag
    {
        $payload = [
            'name'  => $data['name'],
            'type'  => $data['type'] ?? 'custom',
            'color' => $data['color'] ?? 'primary',
        ];

        return DB::transaction(fn () =>
            Tag::firstOrCreate(
                [
                    'type' => $payload['type'],
                    'name' => $payload['name'],
                ],
                ['color' => $payload['color']]
            )
        );
    }

    public function update(Tag $tag, array $data): Tag
    {
        $tag->fill($data);
        $tag->save();

        return $tag;
    }

    public function delete(Tag $tag): void
    {
        $tag->consumables()->detach();
        $tag->suppliers()->detach();
        $tag->wbProducts()->detach();

        $tag->delete();
    }

    public function attachToModel(Model $model, array $tagIds): void
    {
        $model->tags()->syncWithoutDetaching($tagIds);
    }

    public function detachFromModel(Model $model, array $tagIds): void
    {
        $model->tags()->detach($tagIds);
    }

    public function syncForModel(Model $model, array $tagIds): void
    {
        $model->tags()->sync($tagIds);
    }
}
