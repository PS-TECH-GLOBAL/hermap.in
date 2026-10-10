<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('DIR_INC', __DIR__);

// Match the OG project bootstrap convention, without sharing project secrets.
if (is_file(ROOT_PATH . '/.env')) {
    foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $name) && getenv($name) === false) {
            putenv($name . '=' . trim(trim($value), "\"'"));
        }
    }
}

// Auto-detect Localhost vs Production Server
$httpHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$isHostLocal = in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true)
    || str_starts_with($httpHost, 'localhost:')
    || str_starts_with($httpHost, '127.0.0.1:');

$isProdHost = in_array($httpHost, ['hermap.in', 'www.hermap.in'], true)
    || str_ends_with($httpHost, 'hermap.in');

$isServerDir = str_contains(__DIR__, 'profs8a3') || str_contains(ROOT_PATH, 'profs8a3');

if ($isProdHost || $isServerDir || getenv('APP_ENV') === 'production') {
    $isProd = true;
} elseif (getenv('APP_ENV') === 'local' || $isHostLocal) {
    $isProd = false;
} else {
    $isProd = (PHP_SAPI !== 'cli');
}

define('PROD', $isProd);

if (PROD) {
    // Production Server Environment (cPanel / Live: https://www.hermap.in)
    define('SITE_URL', 'https://www.hermap.in');
    define('DB_HOST', 'localhost');
    define('DB_PORT', 3306);
    define('DB_NAME', 'profs8a3_hermap26india');
    define('DB_USER', 'profs8a3_hermapusr');
    define('DB_PASSWORD', 'Z;[isu*0Ng(u');
    define('INTAKE_ENABLED', true);
} else {
    // Localhost Development Environment
    define('SITE_URL', rtrim(getenv('SITE_URL') ?: 'http://localhost/hermap.in', '/'));
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
    define('DB_NAME', getenv('DB_NAME') ?: 'hermap');
    define('DB_USER', getenv('DB_USER') ?: 'hermap_app');
    define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '8fcc510cd95ea6de3057061ba43240f49b70f709bad4ef4a');
    define('INTAKE_ENABLED', getenv('REPORT_INTAKE_ENABLED') === '1');
}

// Sync environment variables so both constants and getenv() work identically
putenv('DB_HOST=' . DB_HOST);
putenv('DB_PORT=' . DB_PORT);
putenv('DB_NAME=' . DB_NAME);
putenv('DB_USER=' . DB_USER);
putenv('DB_PASSWORD=' . DB_PASSWORD);
putenv('SITE_URL=' . SITE_URL);
if (!getenv('APP_SECRET')) putenv('APP_SECRET=3165aa1a4164d3eaf4a6631746fb7877b9c11c8d61786845c11538bee539ab53');
if (!getenv('APP_ENCRYPTION_KEY')) putenv('APP_ENCRYPTION_KEY=9f1ad5e15a7c0cb54a9177881592ae675aa007e49584f633cbc9dc994e8fc596');

define('ASSETS_URL', SITE_URL . '/assets');
define('SUPPORT_EMAIL', getenv('SUPPORT_EMAIL') ?: 'help@hermap.in');
define('BRAND_NAME', 'HerMap.in');
define('BRAND_TAGLINE', 'Know Before You Go.');
define('BRAND_MESSAGE', 'Every Report, Saves a Life.');
define('LEGAL_OPERATOR', getenv('LEGAL_OPERATOR') ?: '');
define('GRIEVANCE_OFFICER', getenv('GRIEVANCE_OFFICER') ?: '');
define('GRIEVANCE_EMAIL', getenv('GRIEVANCE_EMAIL') ?: '');
define('POLICIES_DRAFT', true); // Remove only after operator/legal and operational review.
define('PUBLIC_MIN_REPORTS', max(5, (int)(getenv('PUBLIC_MIN_REPORTS') ?: 5)));
define('PUBLICATION_DELAY_HOURS', max(24, (int)(getenv('PUBLICATION_DELAY_HOURS') ?: 48)));
date_default_timezone_set('UTC');
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}

set_exception_handler(static function (Throwable $e): void {
    error_log('HerMap Unhandled Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI !== 'cli') {
        if (!headers_sent()) {
            http_response_code(500);
            if (function_exists('header_remove')) {
                header_remove('X-Powered-By');
            }
            header('Content-Type: text/plain; charset=UTF-8');
        }
        echo "500 Internal Server Error\n";
    } else {
        fwrite(STDERR, "An internal error occurred.\n");
    }
    exit(1);
});

if (PHP_SAPI !== 'cli') {
    if (function_exists('header_remove')) {
        header_remove('X-Powered-By');
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; worker-src 'self' blob:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

require_once DIR_INC . '/functions.php';
require_once DIR_INC . '/database.php';
require_once DIR_INC . '/reporting.php';
