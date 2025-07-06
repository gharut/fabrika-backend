<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ProductCategory;

class WbProduct extends Model
{
    protected $table = 'wb_products';

    protected $fillable = [
        'client_id',
        'name',
        'color',
        'article',
        'composition',
        'category',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'category' => ProductCategory::class,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
