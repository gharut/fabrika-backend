<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ChestnyZnakLabel;

class ProductSize extends Model
{
    use HasFactory;
    protected $table = 'product_sizes';

    protected $fillable = [
        'product_id',
        'value',
        'barcode',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(WbProduct::class);
    }

    public function chestnyZnakLabels()
    {
        return $this->hasMany(ChestnyZnakLabel::class, 'size_id');
    }
}
