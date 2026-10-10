<?php
declare(strict_types=1);

// Lazy project-specific connection. Never borrow another site's database.
function ps_db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) return $connection;
    $user = getenv('DB_USER');
    if (!$user || !getenv('DB_NAME')) {
        throw new RuntimeException('HerMap database is not configured.');
    }
    $port = (int) (getenv('DB_PORT') ?: 3306);
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: '127.0.0.1', $port, getenv('DB_NAME'));
    $connection = new PDO($dsn, $user, getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $connection->exec("SET time_zone='+00:00'");
    return $connection;
}
