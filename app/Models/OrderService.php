<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderService extends Model
{
    use HasFactory, HasTimestamps;
    protected $table = 'order_services';
    protected $hidden = ['pivot'];
    protected $fillable = [
        'order_id',
        'product_id',
        'service_id',
        'service_attribute',
        'price',
    ];

    public function product(): BelongsTo {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function order(): BelongsTo {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

}
