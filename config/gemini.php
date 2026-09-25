<?php

return [
    'api_key' => env('GEMINI_API_KEY'),

    'model' => env(
        'GEMINI_MODEL',
        'gemini-2.5-flash'
    ),

    'base_url' => env(
        'GEMINI_BASE_URL',
        'https://generativelanguage.googleapis.com'
    ),

    'timeout' => (int) env('GEMINI_TIMEOUT', 180),
];
