<?php

namespace App\Support;

use InvalidArgumentException;

class TaggableResolver
{
    public static function map(): array
    {
        return [
            // 'consumables' => \App\Models\Consumable::class,
            // 'suppliers'   => \App\Models\Supplier::class,
            'wb_products'   => \App\Models\WbProduct::class,
        ];
    }

    public static function resolve(string $alias): string
    {
        $map = self::map();
        if (!isset($map[$alias])) {
            throw new InvalidArgumentException("Unsupported taggable type: {$alias}");
        }
        return $map[$alias];
    }
}
