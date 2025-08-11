<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
        'wb_api_token'
    ];

    protected $casts = [
        'details' => 'array',
    ];
}
