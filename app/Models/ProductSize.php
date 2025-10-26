<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ChestnyZnakLabel;
use App\Models\InventoryLevel;

class ProductSize extends Model
{
    use HasFactory;
    protected $table = 'product_sizes';

    protected $fillable = [
        'product_id',
        'value',
        'tech_size',
        'barcode',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'stock'      => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(WbProduct::class);
    }

    public function chestnyZnakLabels()
    {
        return $this->hasMany(ChestnyZnakLabel::class, 'size_id');
    }

    public function inventoryLevels()
    {
        return $this->hasMany(InventoryLevel::class, 'size_id');
    }
}
