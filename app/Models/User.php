<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, CanResetPassword;

    protected $fillable = [
        'email',
        'password',
        'name',
        'address',
        'phone',
        'avatar',
        'telegram',
    ];

    protected string $guard_name = 'api';

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $appends = ['role', 'email_verified'];

    public function getUserRolesCustom()
    {
        return $this->roles()
            ->select('id', 'name', 'guard_name')
            ->get()
            ->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'is_primary' => $this->roles()->first()?->id === $role->id
                ];
            });
    }

    public function getRoleAttribute()
    {
        return $this->roles()->first();
    }
    
    protected static function booted(): void
    {
        static::updating(function (self $user) {
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
        });
    }

    public function getEmailVerifiedAttribute(): bool
    {
        return method_exists($this, 'hasVerifiedEmail') ? $this->hasVerifiedEmail() : !is_null($this->email_verified_at);
    }

    public function clientUsers()
    {
        return $this->hasMany(ClientUser::class, 'user_id');
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_users', 'user_id', 'client_id')
            ->withPivot('role_id')
            ->withTimestamps();
    }
}
