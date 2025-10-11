<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

class SystemAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'system_accounts';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'role',
        'active_from',
        'active_to',
        'revoked_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'active_from' => 'datetime',
        'active_to' => 'datetime',
        'revoked_at' => 'datetime',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super-admin';
    }
}
