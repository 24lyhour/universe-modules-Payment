<?php

return [
    'name' => 'Payment',

    /*
    |--------------------------------------------------------------------------
    | PayWay Configuration
    |--------------------------------------------------------------------------
    |
    | ABA PayWay payment gateway configuration.
    | Get credentials from: https://www.payway.com.kh/
    |
    */
    'payway' => [
        // Merchant credentials
        'merchant_id' => env('PAYWAY_MERCHANT_ID'),
        'api_key' => env('PAYWAY_API_KEY'),

        // API endpoints
        'base_url' => env('PAYWAY_BASE_URL', 'https://checkout-sandbox.payway.com.kh'),

        // Callback URL for payment notifications
        'callback_url' => env('PAYWAY_CALLBACK_URL'),

        // Mobile app deeplinks for payment result
        'return_deeplink_ios' => env('PAYWAY_DEEPLINK_IOS', 'cylicon://payment-result'),
        'return_deeplink_android' => env('PAYWAY_DEEPLINK_ANDROID', 'cylicon://payment-result'),

        // Transaction ID prefix (max 3 chars)
        'tran_id_prefix' => env('PAYWAY_TRAN_ID_PREFIX', 'CYL'),
    ],
];
