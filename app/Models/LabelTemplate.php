<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\ClientContext;
use App\Models\Concerns\BelongsToClient;

class LabelTemplate extends Model
{
    use HasFactory, BelongsToClient;

    protected $fillable = [
        'name',
        'content',
        'is_system'
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function applyClientScope(Builder $builder, ClientContext $ctx): void
    {
        $table = $this->getTable();
        
        $builder->where(function ($query) use ($table, $ctx) {
            $query->where("{$table}.client_id", $ctx->id())
                  ->orWhere("{$table}.is_system", true);
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}