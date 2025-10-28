<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $fillable = [
        'product_id',
        'value',
        'type',
        'strategy_item_id'
    ];

    protected $casts = [
        'value' => 'double',
    ];

    public function product()
    {
        return $this->belongsTo(WbProduct::class);
    }

    public function strategyItem()
    {
        return $this->belongsTo(StrategyItem::class);
    }
}
