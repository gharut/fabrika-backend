<?php

namespace App\Repositories;
use App\Models\Consumable;
use App\Repositories\Interfaces\ConsumableRepositoryInterface;

class ConsumableRepository implements ConsumableRepositoryInterface
{
    public function incrementConsumable(int $id, int $qty): void
    {
        Consumable::query()->where("id", $id)->increment('qty', $qty);
    }

    public function decrementConsumable(int $id, int $qty): void
    {
        Consumable::query()->where("id", $id)->decrement('qty', $qty);
    }
}
