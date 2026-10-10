<?php
// PHP's development server ignores .htaccess. Mirror private-file protection here.
if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}

if (!empty($_SERVER['HTTP_HOST'])) {
    putenv('SITE_URL=http://' . $_SERVER['HTTP_HOST']);
}

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$segments = explode('/', trim(str_replace('\\', '/', $path), '/'));
$private = ['inc', 'scripts', 'tests', 'database', 'docs', 'node_modules', 'tmp', 'private', 'vendor', '.agents', '.npm-cache', '.git', '.vscode', '.idea'];
$baseName = strtolower(basename($path));

if (
    in_array(strtolower($segments[0] ?? ''), $private, true)
    || preg_match('~(^|/)\.~', $path)
    || preg_match('~\.(sql|sqlite|bak|log|env|ini|conf|cfg|sh|bat|cmd|ps1|yml|yaml|md|lock)$~i', $path)
    || preg_match('~^(package(-lock)?\.json|composer\.(json|lock)|admin-access.*|credentials.*)$~i', $baseName)
) {
    http_response_code(403);
    if (function_exists('header_remove')) {
        header_remove('X-Powered-By');
    }
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    return true;
}
$root = dirname(__DIR__);
$file = realpath($root . '/' . ltrim($path, '/'));
if ($file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
require $root . '/index.php';
