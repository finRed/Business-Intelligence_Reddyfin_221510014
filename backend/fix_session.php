<?php
session_start();

echo "=== Session Fix Script ===\n\n";

// Create persistent admin session
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['email'] = 'admin@contractrec.com';
$_SESSION['role'] = 'admin';
$_SESSION['logged_in'] = true;

echo "Admin session created:\n";
print_r($_SESSION);

// Test session persistence
echo "\nTesting session persistence...\n";
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    echo "✓ Session is active\n";
    echo "✓ User ID: " . $_SESSION['user_id'] . "\n";
    echo "✓ Role: " . $_SESSION['role'] . "\n";
    echo "✓ Username: " . $_SESSION['username'] . "\n";
} else {
    echo "✗ Session is not active\n";
}

// Set session cookie parameters for longer persistence
ini_set('session.gc_maxlifetime', 86400); // 24 hours
ini_set('session.cookie_lifetime', 86400); // 24 hours

echo "\nSession settings updated for 24-hour persistence\n";
echo "Session ID: " . session_id() . "\n";

echo "\n=== Session fix completed ===\n";
echo "Silakan coba login sebagai admin dan test fungsi add/delete user\n";
?> 