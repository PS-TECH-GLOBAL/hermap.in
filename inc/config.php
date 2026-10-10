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

define('PROD', getenv('APP_ENV') === 'production');
define('SITE_URL', rtrim(getenv('SITE_URL') ?: (PROD ? 'https://hermap.in' : 'http://localhost/hermap.in'), '/'));
define('ASSETS_URL', SITE_URL . '/assets');
define('SUPPORT_EMAIL', getenv('SUPPORT_EMAIL') ?: 'help@hermap.in');
define('BRAND_NAME', 'HerMap.in');
define('BRAND_TAGLINE', 'Know Before You Go.');
define('BRAND_MESSAGE', 'Every Report, Saves a Life.');
define('LEGAL_OPERATOR', getenv('LEGAL_OPERATOR') ?: '');
define('GRIEVANCE_OFFICER', getenv('GRIEVANCE_OFFICER') ?: '');
define('GRIEVANCE_EMAIL', getenv('GRIEVANCE_EMAIL') ?: '');
define('POLICIES_DRAFT', true); // Remove only after operator/legal and operational review.
define('INTAKE_ENABLED', getenv('REPORT_INTAKE_ENABLED') === '1');
define('PUBLIC_MIN_REPORTS', max(5, (int)(getenv('PUBLIC_MIN_REPORTS') ?: 5)));
define('PUBLICATION_DELAY_HOURS', max(24, (int)(getenv('PUBLICATION_DELAY_HOURS') ?: 48)));
date_default_timezone_set('UTC');
ini_set('display_errors', PROD ? '0' : '1');
error_reporting(E_ALL);

if (PHP_SAPI !== 'cli') {
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
