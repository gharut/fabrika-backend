<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Auth;

class Client extends Model
{
    use HasFactory, SoftDeletes, HasTimestamps;
    protected $hidden = ['pivot'];
    protected $fillable = [
        'name',
        'type',
        'phone',
        'email',
        'telegram',
        'details',
        'tin',
        'psrn',
        'account',
        'bank',
        'correspondent_account',
        'bic',
        'legal_address',
        'vat',
        'created_by',
        'updated_by',
        'owner_id',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('accessible', function (Builder $builder) {
            $user = Auth::user();
            
            if (!$user) {
                $builder->whereNull('id');
                return;
            }

            if ($user->hasRole('super-admin')) {
                return;
            }

            $builder->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        });
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'client_users', 'client_id', 'user_id')
            ->withPivot('role_id')
            ->withTimestamps();
    }
    
    protected $casts = [
        'details' => 'array',
    ];
}
