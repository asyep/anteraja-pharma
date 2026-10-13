<?php

require __DIR__.'/../backend/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__.'/../backend')->load();
$name = $_ENV['DB_DATABASE'];
if ($name !== 'anteraja_pharma_hackathon') {
    throw new RuntimeException('Setup otomatis hanya boleh membuat database khusus Pharma.');
}
$pdo = new PDO('mysql:host='.$_ENV['DB_HOST'].';port='.$_ENV['DB_PORT'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS `anteraja_pharma_hackathon` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo "Database khusus tersedia.\n";
