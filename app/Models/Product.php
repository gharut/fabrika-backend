<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasTimestamps;
    protected $hidden = ['pivot'];
    protected $fillable = [
        'name',
        'qty',
        'delivered'
    ];

    public function attributes(): HasManyThrough
    {
        return $this->hasManyThrough(Attribute::class, ProductAttribute::class);
    }

}
