<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ProductCategory;

class WbProduct extends Model
{
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
}
