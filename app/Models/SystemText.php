<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemText extends Model
{
    protected $fillable = ['title', 'key', 'content'];
}
