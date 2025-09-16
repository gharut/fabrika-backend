<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use App\Models\Concerns\BelongsToClient;

class Tag extends Model
{
    use HasFactory, BelongsToClient;

    protected $hidden = ['pivot'];

    protected $fillable = [
        'name',
        'type',
        'color',
        'client_id'
    ];
    
    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }
    
    public function scopeCustom($query)
    {
        return $query->where('type', 'custom');
    }

    public function consumables(): MorphToMany
    {
        return $this->morphedByMany(Consumable::class, 'taggable');
    }

    public function wbProducts(): MorphToMany
    {
        return $this->morphedByMany(WbProduct::class, 'taggable');
    }

    public function suppliers(): MorphToMany
    {
        return $this->morphedByMany(Supplier::class, 'taggable');
    }
}
