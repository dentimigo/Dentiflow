<?php

return [
    'enabled' => env('STRIPE_CHECKOUT_ENABLED', false),
    'live' => (bool) env('STRIPE_LIVE_MODE', false),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'prices' => [
        'basico' => env('STRIPE_PRICE_BASICO', env('STRIPE_LIVE_MODE', false) ? null : 'price_1UdjkjRteX9kStaU6NRSjt1O'),
        'consultorio' => env('STRIPE_PRICE_CONSULTORIO', env('STRIPE_LIVE_MODE', false) ? null : 'price_1UdjljRteX9kStaUYu4MCUJy'),
    ],
];
