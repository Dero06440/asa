<?php
// ============================================================
// ASA Arrosants et Riverains du Paillon - Configuration
// Les secrets doivent etre definis dans config.local.php
// ============================================================

function appDetectHost(): string {
    $host = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    if (str_contains($host, ':')) {
        $parts = explode(':', $host);
        return $parts[0];
    }
    return $host;
}

function appIsHttps(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? null) === '443') {
        return true;
    }
    if (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    return false;
}

function appDetectBaseUrl(string $host, bool $https): string {
    $scheme = $https ? 'https' : 'http';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = str_replace('\\', '/', dirname($scriptName));
    if (preg_match('#/admin$#', $dir)) {
        $dir = str_replace('\\', '/', dirname($dir));
    }
    $dir = rtrim($dir, '/.');
    return $scheme . '://' . $host . ($dir !== '' ? $dir : '');
}

function appDetectBaseHost(): string {
    return strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
}

$appHost = appDetectHost();
$isLocalHost = in_array($appHost, ['localhost', '127.0.0.1'], true)
    || str_starts_with($appHost, '192.168.')
    || str_starts_with($appHost, '10.')
    || (bool) preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $appHost);

define('APP_ENV', $isLocalHost ? 'local' : 'production');
define('APP_DEBUG_OTP', APP_ENV === 'local');

require_once __DIR__ . '/config.local.php';

define('DB_CHARSET', defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
define('SMTP_PORT', defined('SMTP_PORT') ? SMTP_PORT : 465);
define('SMTP_SECURE', defined('SMTP_SECURE') ? SMTP_SECURE : 'ssl');
define('SMTP_FROM_NAME', defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'ASA Arrosants et Riverains du Paillon');

if (!defined('SMTP_ENABLED')) {
    define('SMTP_ENABLED', APP_ENV !== 'local');
}

define('BASE_URL', appDetectBaseUrl(appDetectBaseHost(), appIsHttps()));
define('APP_NAME', 'ASA Arrosants et Riverains du Paillon');
define('OTP_VALIDITY_MINUTES', 15);
define('OTP_MAX_ATTEMPTS', 3);

date_default_timezone_set('Europe/Paris');
