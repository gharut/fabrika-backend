<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    use HasFactory;
    protected $table = 'labels';

    protected $fillable = [
        'name',
        'product_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'deleted_at'       => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
        'category'         => ProductCategory::class,
    ];

    public function product()
    {
        return $this->belongsTo(WbProduct::class, 'product_id');
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
