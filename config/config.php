<?php

return [
    'name' => 'Payment',

    'payway' => [
        'merchant_id' => env('PAYWAY_MERCHANT_ID'),
        'api_key' => env('PAYWAY_API_KEY'),
        'base_url' => env('PAYWAY_BASE_URL', 'https://checkout-sandbox.payway.com.kh'),
        'callback_url' => env('PAYWAY_CALLBACK_URL'),
    ],
];
