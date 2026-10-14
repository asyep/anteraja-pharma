<?php

$backend = dirname(__DIR__, 2).'/server';
require $backend.'/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($backend)->load();
$name = $_ENV['DB_DATABASE'];
if ($name !== 'anteraja_pharma_hackathon') {
    throw new RuntimeException('Setup otomatis hanya boleh membuat database khusus Pharma.');
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
$driver = $_ENV['DB_CONNECTION'] ?? 'pgsql';
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? ($driver === 'mysql' ? '3306' : '5432');
$username = $_ENV['DB_USERNAME'] ?? '';
$password = $_ENV['DB_PASSWORD'] ?? '';

if ($driver === 'pgsql') {
    $pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $username, $password, $options);
    $exists = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
    $exists->execute([$name]);
    if (! $exists->fetchColumn()) {
        $pdo->exec('CREATE DATABASE "'.$name.'"');
    }
} elseif ($driver === 'mysql') {
    $pdo = new PDO("mysql:host={$host};port={$port}", $username, $password, $options);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} else {
    throw new RuntimeException('Setup otomatis hanya mendukung PostgreSQL (pgsql) dan MySQL.');
}

echo "Database khusus tersedia.\n";
