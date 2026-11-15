<?php

declare(strict_types=1);

$verifyResponseSignature = env('EET_VERIFY_RESPONSE_SIGNATURE', true);

return [
    'environment' => env('EET_ENVIRONMENT', 'playground'),

    'endpoints' => [
        'playground' => 'https://pg.trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4',
        'production' => 'https://trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4',
    ],

    'timeout' => (int) env('EET_TIMEOUT', 5),

    'verify_response_signature' => is_bool($verifyResponseSignature)
        ? $verifyResponseSignature
        : filter_var($verifyResponseSignature, FILTER_VALIDATE_BOOLEAN),

    'openssl_binary' => env('EET_OPENSSL_BINARY', 'openssl'),

    'caeet' => [
        'base_url' => env('EET_CAEET_BASE_URL'),
        'endpoints' => [
            'test' => 'https://ca.test.caeet.gov.cz/api',
            'trial' => 'https://ca.zkus.caeet.gov.cz/api',
            'production' => 'https://ca.caeet.gov.cz/api',
        ],
        'timeout' => (int) env('EET_CAEET_TIMEOUT', 10),
    ],
];
