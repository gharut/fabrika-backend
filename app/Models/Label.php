<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Label extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'labels';

    protected $fillable = [
        'name',
        'product_id',
        'client_id',
        'article',
        'composition',
        'color',
        'has_chestny_znak',
        'category',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'has_chestny_znak' => 'boolean',
        'deleted_at'       => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
        'category'         => ProductCategory::class,
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function chestnyZnakLabels()
    {
        return $this->hasMany(ChestnyZnakLabel::class, 'label_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
