<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'has_chestny_znak' => 'boolean',
    ];

    protected $appends = ['wb_category'];

    public function wbCategoryLink(): HasOne
    {
        return $this->hasOne(ProductMarketplaceCategory::class, 'product_id')
            ->where('marketplace_code', 'wb')
            ->with(['category' => function ($q) {
                $q->select('id', 'name', 'parent_id')
                ->with(['parent:id,name']);
            }]);
    }

    public function wbCategories()
    {
        return $this->hasMany(ProductMarketplaceCategory::class, 'product_id')
            ->where('marketplace_code', 'wb');
    }

    public function getWbCategoryAttribute(): ?array
    {
        if (!$this->relationLoaded('wbCategoryLink')) {
            $this->load('wbCategoryLink.category.parent');
        }

        $link = $this->getRelation('wbCategoryLink');
        $cat  = $link?->category;

        if (!$cat) {
            return null;
        }

        return [
            'id'       => $cat->id,
            'name'     => $cat->name,
            'parent'   => $cat->parent 
                ? ['id' => $cat->parent->id, 'name' => $cat->parent->name]
                : null,
        ];
    }

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
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('position');
    }

    public function mainImage()
    {
        return $this->hasOne(ProductImage::class, 'product_id')->orderBy('position');
    }
}
