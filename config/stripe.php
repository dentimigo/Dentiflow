<?php

return [
    'enabled' => env('STRIPE_CHECKOUT_ENABLED', false),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'prices' => [
        'basico' => env('STRIPE_PRICE_BASICO', 'price_1UdjkjRteX9kStaU6NRSjt1O'),
        'consultorio' => env('STRIPE_PRICE_CONSULTORIO', 'price_1UdjljRteX9kStaUYu4MCUJy'),
    ],
];
