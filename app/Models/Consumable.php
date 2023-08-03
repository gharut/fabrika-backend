<?php

namespace App\Models;

use App\Enums\PriceThreshold;
use App\Enums\Unit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consumable extends Model
{
    use HasFactory, SoftDeletes;
    protected $hidden = ['pivot'];
    protected $fillable = [
        'name',
        'size',
        'unit',
        'price',
        'qty',
        'price_threshold_type',
        'price_threshold',
        'qty_threshold'
    ];

    protected $casts = [
        'unit' => Unit::class,
        'price_threshold_type' => PriceThreshold::class,
    ];

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ConsumableHistory::class);
    }
}
