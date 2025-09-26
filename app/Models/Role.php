<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends \Spatie\Permission\Models\Role
{
    protected $fillable = ['name', 'visible_name', 'guard_name', 'client_id'];
    protected $hidden = ['pivot'];
}
