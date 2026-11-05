<?php

declare(strict_types=1);

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'pig_breeding_services';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
  
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
