<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumableHistory extends Model
{

    use HasFactory;
    protected $table = "consumable_history";

    protected $fillable = [
        'type',
        'consumable_id',
        'supplier_id',
        'operation_id',
        'qty',
        'price_per_unit',
        'delivery_status',
        'payment_status',
        'delivery_date',
        'payment_date'
    ];

    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class, 'consumable_id', 'id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }
}
