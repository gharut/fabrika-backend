<?php
// app/Models/StrategyItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Models\WbProduct;
use App\Models\InventoryLevel;

class StrategyItem extends Model
{
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_PAUSED   = 'paused';
    public const STATUS_APPLIED  = 'applied';

    protected $fillable = [
        'strategy_id',
        'model_type',
        'model_id',
        'status',
        'temp_discount',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'temp_discount' => 'decimal:2',
        'starts_at'     => 'string',
        'ends_at'       => 'string',
    ];

    public static function getAllowedStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_APPLIED,
            self::STATUS_PAUSED,
        ];
    }

    public function strategy()
    {
        return $this->belongsTo(PricingStrategy::class, 'strategy_id');
    }

    public function target()
    {
        return $this->morphTo(__FUNCTION__, 'model_type', 'model_id');
    }

    public function wbProduct()
    {
        return $this->belongsTo(\App\Models\WbProduct::class, 'model_id', 'id');
    }

    public function inventoryLevels()
    {
        return $this->hasMany(InventoryLevel::class, 'product_id', 'model_id');
    }

    public function productPrices()
    {
        return $this->hasMany(ProductPrice::class, 'product_id', 'model_id');
    }

    public function getDiscountAttribute(): float
    {
        if ($this->model_type !== WbProduct::class) {
            return 0.0;
        }

        $discount = $this->relationLoaded('productPrices')
            ? $this->productPrices->firstWhere('type', 'discount')
            : \App\Models\ProductPrice::where('product_id', $this->model_id)
                ->where('type', 'discount')
                ->first();

        return (float) ($discount?->value ?? 0);
    }

    public function getIsRevertedAttribute(): bool
    {
        $now = now()->format('H:i:s');

        return $this->status === self::STATUS_APPLIED
            && $this->ends_at !== null
            && $this->ends_at <= $now;
    }

    public function getProductInfoAttribute(): ?WbProduct
    {
        if ($this->model_type !== WbProduct::class) {
            return null;
        }

        return $this->relationLoaded('wbProduct') ? $this->getRelation('wbProduct') : $this->wbProduct()->first();
    }


    public function getStockLevelsAttribute(): Collection
    {
        if ($this->model_type !== WbProduct::class) {
            return collect();
        }
        return $this->relationLoaded('inventoryLevels')
            ? $this->getRelation('inventoryLevels')
            : $this->inventoryLevels()->get();
    }

    public function getProductDiscountAttribute(): float
    {
        if ($this->model_type !== WbProduct::class) {
            return 0.0;
        }

        if ($this->relationLoaded('productPrices')) {
            $discount = $this->productPrices->firstWhere('type', 'discount');
            return (float) ($discount?->value ?? 0);
        }

        return (float) (ProductPrice::where('product_id', $this->model_id)
            ->where('type', 'discount')
            ->value('value') ?? 0);
    }

    public function getTotalQtyAttribute(): int
    {
        if ($this->model_type !== WbProduct::class) {
            return 0;
        }

        if ($this->relationLoaded('inventoryLevels')) {
            return (int) $this->getRelation('inventoryLevels')->sum('qty');
        }

        return (int) $this->inventoryLevels()->sum('qty');
    }

    public function resolveActionForTime(string $nowTime): ?string
    {
        $start = $this->starts_at ? substr($this->starts_at, 0, 5) : null;
        $end   = $this->ends_at   ? substr($this->ends_at, 0, 5)   : null;

        $startsOk = !$start || $nowTime >= $start;
        $endsOk   = !$end   || $nowTime <= $end;
        $inWindow = $startsOk && $endsOk;

        if ($this->temp_discount === null) {
            return null;
        }

        return $inWindow ? 'apply' : 'revert';
    }

    public function resolveCurrentPercent(\DateTimeInterface $now = null): ?float
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return null;
        }

        $now = $now ? \Illuminate\Support\Carbon::parse($now) : now();
        $inWindow = (
            (is_null($this->starts_at) || $this->starts_at->lte($now)) &&
            (is_null($this->ends_at)   || $this->ends_at->gte($now))
        );

        if ($inWindow && $this->temp_discount !== null) {
            return (float) $this->temp_discount;
        }

        return $this->discount !== null ? (float) $this->discount : null;
    }

    public function applyToTarget(?float $percent): void
    {
        /** @var \Illuminate\Database\Eloquent\Model|null $target */
        $target = (new $this->model_type)::query()->find($this->model_id);
        if (! $target) {
            return;
        }

        if ($percent !== null && isset($target->base_price)) {
            $target->sale_price = round(((float)$target->base_price) * (1 - $percent / 100), 2);
        } else {
            if (isset($target->sale_price)) {
                $target->sale_price = null;
            }
        }

        $target->save();
    }
}
