<?php
session_start();

echo "Current Session Data:\n";
echo "===================\n";
print_r($_SESSION);

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    echo "\nSession Status: ACTIVE\n";
    echo "User ID: " . $_SESSION['user_id'] . "\n";
    echo "Role: " . $_SESSION['role'] . "\n";
    echo "Username: " . $_SESSION['username'] . "\n";
} else {
    echo "\nSession Status: NOT ACTIVE\n";
}

// Test database connection
require_once 'config/db.php';
$db = new Database();

echo "\nDatabase connection: ";
if ($db) {
    echo "SUCCESS\n";
} else {
    echo "FAILED\n";
}
?> 