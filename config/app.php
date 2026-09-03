<?php
declare(strict_types=1);

$environment = getenv('APP_ENV') ?: 'development';
$url = rtrim(getenv('APP_URL') ?: '', '/');

if ($environment === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
        throw new RuntimeException('APP_URL debe ser una URL HTTPS válida en producción.');
    }
}

return [
    'environment' => $environment,
    'production' => $environment === 'production',
    'url' => $url,
    'trust_proxy' => filter_var(getenv('TRUST_PROXY') ?: 'false', FILTER_VALIDATE_BOOL),
    'password_reset_enabled' => filter_var(getenv('FEATURE_PASSWORD_RESET') ?: 'false', FILTER_VALIDATE_BOOL),
];
