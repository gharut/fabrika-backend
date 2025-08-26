<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoItem extends Model
{
    protected $fillable = [
        'campaign_id',
        'article',
        'temp_discount',
        'snapshot_discount',
        'snapshot_captured_at',
        'status',
        'error',
    ];

    protected $casts = [
        'snapshot_captured_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromoCampaign::class, 'campaign_id');
    }
}
