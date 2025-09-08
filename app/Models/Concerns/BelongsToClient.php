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
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.client_id", $ctx->id());
            }
        });

        static::creating(function (Model $model) {
            $ctx = app(ClientContext::class);
            if ($ctx->id() && empty($model->client_id)) {
                $model->client_id = $ctx->id();
            }
        });
    }
}