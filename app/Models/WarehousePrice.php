<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehousePrice extends Model
{
    use HasFactory, SoftDeletes, HasTimestamps;

    protected $fillable = [
        'warehouse_id',
        'min_quantity',
        'max_quantity',
        'price',
        'cost_price'
    ];

    protected $casts = [
        'price' => 'float',
        'cost_price' => 'float',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
