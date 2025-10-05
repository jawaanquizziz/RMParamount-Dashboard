<?php
// --- DATABASE CONFIGURATION ---

// Database credentials
$servername = "localhost"; // Usually "localhost" for local development
$username = "root";      // The default username for XAMPP/WAMP is "root"
$password = "jawaan2720";  // Your MySQL password (it might be empty by default)
$dbname = "rm_paramount_db"; // The name of your database

// --- CREATE AND CHECK CONNECTION ---

// Create a new mysqli connection object
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    // If connection fails, stop the script and display an error message.
    // In a production environment, you would handle this more gracefully,
    // for example, by logging the error and showing a user-friendly message.
    die("Connection failed: " . $conn->connect_error);
}

// Set the character set to utf8mb4 for better Unicode support (recommended)
$conn->set_charset("utf8mb4");

?>

