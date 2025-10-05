<?php

// 1. Load the Composer autoloader (which has our new library)
require_once __DIR__ . '/vendor/autoload.php';

// 2. Find and load the .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// 3. Get database credentials securely from the environment
$servername = $_ENV['DB_HOST'];
$username   = $_ENV['DB_USER'];
$password   = $_ENV['DB_PASS'];
$dbname     = $_ENV['DB_NAME'];

// --- CREATE AND CHECK CONNECTION (this part is the same as before) ---
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>