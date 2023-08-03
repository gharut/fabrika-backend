<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Permission extends \Spatie\Permission\Models\Permission
{
    protected $fillable = ['category', 'name', 'visible_name', 'guard_name'];
    protected $hidden = ['pivot'];
}
