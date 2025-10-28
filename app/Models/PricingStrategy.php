<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClient;

class PricingStrategy extends Model
{
    use SoftDeletes, BelongsToClient;

    public const TYPE_TIME_DISCOUNT = 'time_discount';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';

    protected $fillable = [
        'name',
        'type',
        'status',
        'order_by_field',
        'order_direction',
        'account_id',
        'created_by',
        'updated_by',
    ];

    protected $appends = ['items_count'];
    protected $hidden = ['items'];

    public function items()
    {
        return $this->hasMany(StrategyItem::class, 'strategy_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function account()
    {
        return $this->belongsTo(MarketplaceAccount::class, 'account_id');
    }
    
    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopeType($q, string $type)
    {
        return $q->where('type', $type);
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }
}
