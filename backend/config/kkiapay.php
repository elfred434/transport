<?php

return [
    'public_key' => env('KKIAPAY_PUBLIC_KEY', ''),
    'private_key' => env('KKIAPAY_PRIVATE_KEY', ''),
    'secret' => env('KKIAPAY_SECRET', ''),
    'sandbox' => env('KKIAPAY_SANDBOX', true),
    'base_url' => env('KKIAPAY_BASE_URL', 'https://api.kkiapay.me'),
    'sandbox_url' => env('KKIAPAY_SANDBOX_URL', 'https://api-sandbox.kkiapay.me'),
];
