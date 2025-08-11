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
        'client_name',
        'printer_id',
        'created_by',
        'updated_by',
        'print_single_ean13',
        'print_double_ean13',
        'duplicate_chz'
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'category' => ProductCategory::class,
        'print_single_ean13' => 'boolean',
        'print_double_ean13' => 'boolean',
        'duplicate_chz' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(WbProduct::class, 'product_id');
    }

    public function printer()
    {
        return $this->belongsTo(Printre::class, 'printer_id');
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
