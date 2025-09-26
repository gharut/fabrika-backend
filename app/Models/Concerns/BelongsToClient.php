<?php

namespace App\Models\Concerns;

use App\Support\ClientContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToClient
{
    public static function bootBelongsToClient(): void
    {
        static::addGlobalScope('client', function (Builder $builder) {
            $ctx = app(ClientContext::class);
            if ($ctx->id()) {
                $model = $builder->getModel();
                
                if (method_exists($model, 'applyClientScope')) {
                    $model->applyClientScope($builder, $ctx);
                } else {
                    static::applyDefaultClientScope($builder, $ctx);
                }
            }
        });

        static::creating(function (Model $model) {
            $ctx = app(ClientContext::class);
            if ($ctx->id() && empty($model->client_id)) {
                $model->client_id = $ctx->id();
            }
        });
    }

    protected static function applyDefaultClientScope(Builder $builder, ClientContext $ctx): void
    {
        $table = $builder->getModel()->getTable();
        $builder->where("{$table}.client_id", $ctx->id());
    }
}