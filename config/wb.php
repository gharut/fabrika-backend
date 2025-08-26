<?php

return [
    'base_url' => env('WB_API_URL', 'https://discounts-prices-api.wildberries.ru'),
    'token'    => env('WB_API_TOKEN', ''),

    // Ограничения WB: ~10 запросов / 6 сек
    'rate' => [
        'sleep_ms' => env('WB_SLEEP_MS', 700), // пауза после каждого запроса
        'retries'  => env('WB_RETRIES', 3),    // повторов на 429/5xx
        'backoff_ms' => env('WB_BACKOFF_MS', 500), // старт бэкоффа
    ],
];
