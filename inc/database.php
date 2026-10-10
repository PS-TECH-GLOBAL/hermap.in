<?php
declare(strict_types=1);

// Lazy project-specific connection. Never borrow another site's database.
function ps_db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) return $connection;
    $user = defined('DB_USER') ? DB_USER : getenv('DB_USER');
    $name = defined('DB_NAME') ? DB_NAME : getenv('DB_NAME');
    if (!$user || !$name) {
        throw new RuntimeException('HerMap database is not configured.');
    }
    $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
    $port = (int) (defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: 3306));
    $pass = defined('DB_PASSWORD') ? DB_PASSWORD : (getenv('DB_PASSWORD') ?: '');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
    try {
        $connection = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $connection->exec("SET time_zone='+00:00'");
        return $connection;
    } catch (PDOException $e) {
        error_log('HerMap DB connection failure: ' . $e->getMessage());
        throw new RuntimeException('HerMap database service is temporarily unavailable.');
    }
}
