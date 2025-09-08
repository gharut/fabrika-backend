<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'inviter_id',
        'email',
        'role_id',
        'token',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'meta'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'meta' => 'array',
    ];

    public function client(){ return $this->belongsTo(Client::class); }
    public function inviter(){ return $this->belongsTo(User::class,'inviter_id'); }
    public function role(){ return $this->belongsTo(Role::class); }

    public function isActive(): bool
    {
        if ($this->revoked_at || $this->accepted_at) return false;
        if ($this->expires_at && now()->greaterThan($this->expires_at)) return false;
        return true;
    }
}
