<?php

declare(strict_types=1);

$appUrl = (string) env('APP_URL', 'http://127.0.0.1:8080');
$baseUri = normalize_base_uri((string) env('APP_BASE_URI', parse_url($appUrl, PHP_URL_PATH) ?: detected_base_uri()));

return [
    'name' => env('APP_NAME', 'Industria Feris CRM'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => $appUrl,
    'base_uri' => $baseUri,
    'timezone' => 'America/Asuncion',
    'company' => [
        'name' => env('COMPANY_NAME', 'Industria Feris'),
        'tax_id' => env('COMPANY_TAX_ID', ''),
        'logo' => '/assets/img/industria-feris-isotipo.jpg',
    ],
];
