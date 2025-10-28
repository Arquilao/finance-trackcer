<?php
session_start();

// Database configuration
$host = 'sql307.infinityfree.com';
$username = 'if0_40265789';
$password = 'HavirPia21';
$database = 'if0_40265789_db_ftracker';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");
?>