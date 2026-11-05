<?php

declare(strict_types=1);

$deploymentConfigPath = __DIR__ . '/db-settings.php';
$deploymentConfig = is_file($deploymentConfigPath) ? require $deploymentConfigPath : [];
$deploymentConfig = is_array($deploymentConfig) ? $deploymentConfig : [];

$dbHost = $deploymentConfig['host'] ?? (getenv('DB_HOST') ?: '127.0.0.1');
$dbName = $deploymentConfig['name'] ?? (getenv('DB_NAME') ?: 'pig_breeding_services');
$dbUser = $deploymentConfig['user'] ?? (getenv('DB_USER') ?: 'root');
$dbPass = $deploymentConfig['pass'] ?? (getenv('DB_PASS') ?: '');
  
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = new PDO(
        'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Database connection failed. Import database.sql and check config.php.');
}
