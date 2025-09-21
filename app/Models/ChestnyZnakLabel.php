<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Concerns\BelongsToClient;

class ChestnyZnakLabel extends Model
{
    use HasFactory, SoftDeletes, BelongsToClient;

    protected $table = 'chestny_znak_labels';
    
    protected $fillable = [
        'size_id',
        'code',
        'used',
        'used_by',
        'used_at',
        'created_by',
        'updated_by',
        'operation_id',
        'number',
    ];

    protected $casts = [
        'used'    => 'boolean',
        'used_at' => 'datetime',
    ];

    public function size()
    {
        return $this->belongsTo(ProductSize::class, 'size_id');
    }

    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function operation()
    {
        return $this->belongsTo(FileOperation::class, 'operation_id');
    }
}
