<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'path',
        'url',
        'position',
        'type',
        'alt',
        'width',
        'height',
        'storage',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
