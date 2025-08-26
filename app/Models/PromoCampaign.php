<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCampaign extends Model
{
    protected $fillable = [
        'external_id',
        'status',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PromoItem::class, 'campaign_id');
    }
}
