<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ProductCategory;
use App\Models\Concerns\BelongsToClient;

class WbProduct extends Model
{
    use BelongsToClient;

    protected $table = 'wb_products';

    protected $fillable = [
        'client_id',
        'name',
        'color',
        'article',
        'brand_id',
        'vendor_code',
        'composition',
        'has_chestny_znak',
        'category',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'has_chestny_znak' => 'boolean',
        'category' => ProductCategory::class,
    ];

    // protected static function booted()
    // {
    //     static::addGlobalScope('visibleForUser', function (Builder $builder) {
    //         if ($user = auth()->user()) {
    //             $builder->where('created_by', $user->id);
    //         }
    //     });
    // }

    // protected static function booted()
    // {
    //     static::addGlobalScope('visibleForUser', function (Builder $builder) {
    //         if ($user = auth()->user()) {
    //             if ($user->hasRole('super-admin')) {
    //                 return;
    //             }

    //             if ($user->hasRole('manager') || $user->hasRole('seller')) {
    //                 $builder->where(function ($q) use ($user) {
    //                     $q->where('created_by', $user->id)
    //                     ->orWhereHas('client', function ($q2) use ($user) {
    //                         $q2->where('created_by', $user->id);
    //                     });
    //                 });
    //             } else {
    //                 $builder->whereRaw('0=1');
    //             }
    //         }
    //     });
    // }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function sizes()
    {
        return $this->hasMany(ProductSize::class, 'product_id')
            ->select(['id', 'product_id', 'barcode', 'value']);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function mainImage()
    {
        return $this->hasOne(ProductImage::class)->orderBy('position');
    }
}
