<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Concerns\BelongsToClient;

class Printer extends Model
{
    use HasFactory, BelongsToClient;

    protected $fillable = [
        'name',
        'client_id',
        'labels_count',
        'capacity',
        'warning_threshold',
        'last_synced_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'labels_count' => 'integer',
        'warning_threshold' => 'integer',
        'last_synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
