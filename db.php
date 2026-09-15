<?php

$host = getenv('SMARTGATE_DB_HOST') ?: 'localhost';
$username = getenv('SMARTGATE_DB_USER') ?: 'root';
$password = getenv('SMARTGATE_DB_PASSWORD');
$database = getenv('SMARTGATE_DB_NAME') ?: 'fcapstone';

if ($password === false) {
    $password = '';
}

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

if ($conn->connect_error) {
    error_log('SmartGate database connection failed: ' . $conn->connect_error);
    die('Database connection is currently unavailable.');
}

$conn->set_charset("utf8mb4");

?>
