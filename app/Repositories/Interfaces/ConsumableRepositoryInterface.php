<?php

namespace App\Repositories\Interfaces;

use App\Models\Consumable;

interface ConsumableRepositoryInterface
{
    public function incrementConsumable(int $id, int $qty): void;
    public function decrementConsumable(int $id, int $qty): void;

}
