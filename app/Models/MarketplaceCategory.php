<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceCategory extends Model
{
    protected $fillable = [
        'marketplace_code',
        'external_id',
        'name',
        'parent_id',
        'parent_external_id',
    ];

    public function parent(): BelongsTo {
        return $this->belongsTo(MarketplaceCategory::class, 'parent_id');
    }

    public function children(): HasMany {
        return $this->hasMany(MarketplaceCategory::class, 'parent_id');
    }

    public function productLinks(): HasMany {
        return $this->hasMany(ProductMarketplaceCategory::class, 'marketplace_category_id');
    }
}
