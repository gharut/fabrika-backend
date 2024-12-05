<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes, HasTimestamps;

    protected static function booted()
    {
        static::creating(function ($order) {
            if (!$order->uuid) {
                $order->uuid = (string) \Str::uuid();
            }
        });
    }

    protected $hidden = ['pivot'];
    protected $fillable = [
        'client_id',
        'supply_date',
        'supply_time',
        'supply_items',
        'supply_details',
        'status',
    ];
    protected $casts = [
        'supply_details' => 'array',
        'supply_items' => 'array',
    ];

    public function products(): HasMany {
        return $this->hasMany(Product::class);
    }

    public function order_services(): HasMany {
        return $this->hasMany(OrderService::class);
    }

    public function client(): BelongsTo {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

}
