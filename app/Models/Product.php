<?php

namespace App\Models;

use App\Constants\Services;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasTimestamps;
    protected $hidden = ['pivot'];
    protected $fillable = [
        'order_id',
        'name',
        'parent_id',
        'qty',
        'color',
        'size',
        'complect',
        'delivered'
    ];

    protected $casts = [
        'size' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(OrderService::class, 'product_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    public function totalCount() {
        if($this->parent_id != null) {
            return $this->qty;
        }

        return $this->qty + $this->children()->sum('qty');
//        return Product::query()->where("id", '=', $this->id)
//            ->orWhere('parent_id', '=', $this->id)->sum('qty');
    }

    public static function boot() {
        parent::boot();
        self::deleting(function($product) { // before delete() method call this

            $product->services()->each(function($service) {
                $service->delete(); // <-- raise another deleting event on Post to delete comments
            });
            $product->services()->each(function($service) {
                $service->delete(); // <-- raise another deleting event on Post to delete comments
            });
            $product->children()->each(function($child) {
                $child->delete(); // <-- direct deletion
            });
        });
    }

}
