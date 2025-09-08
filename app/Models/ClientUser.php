<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientUser extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'role_id',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
