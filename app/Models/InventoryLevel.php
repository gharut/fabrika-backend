<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLevel extends Model
{
    use HasFactory;

    protected $table = 'inventory_levels';

    protected $fillable = [
        'product_id',
        'size_id',
        'source',
        'qty',
        'synced_at',
    ];

    protected $casts = [
        'qty' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(WbProduct::class, 'product_id');
    }

    public function size()
    {
        return $this->belongsTo(ProductSize::class, 'size_id');
    }
}
