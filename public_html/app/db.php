<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME'])) {
  $scriptFile = realpath((string)$_SERVER['SCRIPT_FILENAME']);
  $thisFile = realpath(__FILE__);
  if ($scriptFile && $thisFile && $scriptFile === $thisFile) {
    header('Content-Type: text/plain; charset=UTF-8', true, 403);
    exit('Forbidden');
  }
}

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3308';
$db = $_ENV['DB_NAME'] ?? 'farhuaadru';
$user = $_ENV['DB_USER'] ?? 'farhuaadru';
$pass = (string)($_ENV['DB_PASS'] ?? '');
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

if ($pass === '') {
    error_log('DB_PASS is not set in .env — refusing to connect with empty password.');
    throw new RuntimeException('Database configuration incomplete.');
}

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    throw new RuntimeException('Database connection failed.');
}