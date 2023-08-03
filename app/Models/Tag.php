<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    use HasFactory;
    protected $hidden = ['pivot'];

    protected $fillable = [
        'name',
        'type',
    ];

    public function consumables(): MorphToMany
    {
        return $this->morphedByMany(Consumable::class, 'taggable');
    }


    public function suppliers(): MorphToMany
    {
        return $this->morphedByMany(Supplier::class, 'taggable');
    }
}
