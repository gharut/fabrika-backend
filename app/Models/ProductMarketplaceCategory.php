<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMarketplaceCategory extends Model
{
    protected $fillable = [
        'product_id',
        'marketplace_code',
        'marketplace_category_id'
    ];

    public function product(): BelongsTo {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo {
        return $this->belongsTo(MarketplaceCategory::class, 'marketplace_category_id');
    }
}
