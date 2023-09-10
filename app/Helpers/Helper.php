<?php

namespace App\Helpers;

use App\Models\Setting;

class Helper {
    public static function getItemLimits() {
        $limits = Setting::query()->where('name', '=', 'DELIVERY_ITEMS_LIMITS')->first()->toArray();
        return json_decode($limits['value'], true);
    }
}
