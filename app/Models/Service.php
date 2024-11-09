<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes, HasTimestamps;
    protected $hidden = ['pivot'];
    protected $fillable = [
        'name',
        'use_consumable',
        'step',
        'apply_to',
        'multiple_products',
        'count_label',
        'report_type',
        'price',
        'created_by',
        'updated_by'
    ];


    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function attributes(): HasMany
    {
        return $this->HasMany(ServiceAttribute::class, 'service_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
