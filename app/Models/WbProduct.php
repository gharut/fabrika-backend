<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
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

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
    
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function labels()
    {
        return $this->hasMany(Label::class, 'product_id')
            ->select(['id', 'product_id', 'name']);
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
