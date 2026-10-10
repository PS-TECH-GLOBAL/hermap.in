<?php
require_once __DIR__ . '/inc/config.php';
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$basePath = rtrim(parse_url(SITE_URL, PHP_URL_PATH) ?: '', '/');
if ($basePath !== '' && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))) {
    $requestPath = substr($requestPath, strlen($basePath));
}
$route = trim($requestPath, '/');
if (str_starts_with($route,'api/')) {
    require DIR_INC . '/api.php'; exit;
}
$routes = [
    '' => 'home.php', 'index.php' => 'home.php', 'home' => 'home.php',
    'about' => 'about.php', 'methodology' => 'methodology.php', 'contact' => 'contact.php',
    'privacy-policy' => 'privacy-policy.php', 'terms-and-conditions' => 'terms-and-conditions.php',
    'admin' => 'admin.php',
    'purpose' => 'about.php', 'how-it-works' => 'methodology.php',
    'privacy' => 'privacy-policy.php', 'terms' => 'terms-and-conditions.php',
    'reports' => 'reports.php', 'stories' => 'reports.php',
];
if (!isset($routes[$route])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
require __DIR__ . '/' . $routes[$route];
