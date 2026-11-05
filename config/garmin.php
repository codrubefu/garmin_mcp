<?php

return [
    'api_key' => env('GARMIN_PERSONAL_API_KEY'),
    'base_url' => rtrim((string) env('GARMIN_BASE_URL', 'https://personal.connectapi.garmin.com'), '/'),
    'token_path' => '/personalapitoken-service/personal-api/personal-api-key/token',
    'timeout' => 30,

    // Garmin documents no maximum, so this only protects against oversized responses.
    'max_range_days' => 31,
];
