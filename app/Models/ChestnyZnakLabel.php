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
    
    const STATUS_AVAILABLE = 'available';
    const STATUS_USED = 'used';

    const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_USED,
    ];

    protected $fillable = [
        'size_id',
        'code',
        'used_by',
        'used_at',
        'created_by',
        'updated_by',
        'operation_id',
        'number',
        'status'
    ];

    protected $casts = [
        'used'    => 'boolean',
        'used_at' => 'datetime',
    ];

    public function setStatusAttribute($value)
    {
        if (!in_array($value, self::STATUSES)) {
            throw new \InvalidArgumentException("Invalid status: {$value}");
        }
        $this->attributes['status'] = $value;
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeUsed($query)
    {
        return $query->where('status', self::STATUS_USED);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }
    
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
