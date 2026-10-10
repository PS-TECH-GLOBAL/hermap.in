<?php
// PHP's development server ignores .htaccess. Mirror private-file protection here.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$segments = explode('/', trim(str_replace('\\', '/', $path), '/'));
$private = ['inc', 'scripts', 'tests', 'database', 'docs', 'node_modules', 'tmp', '.agents', '.npm-cache'];
$denied = ['PLAN.md', 'README.md', 'CONTRIBUTING.md', 'SECURITY.md', 'package.json', 'package-lock.json'];
if (in_array(strtolower($segments[0] ?? ''), $private, true) || in_array(strtolower(basename($path)), array_map('strtolower', $denied), true)
    || preg_match('~(^|/)\.~', $path) || preg_match('~\.(sql|sqlite|bak|log)$~i', $path)) {
    http_response_code(403);
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
