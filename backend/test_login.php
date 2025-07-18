<?php
// Script untuk test login API
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test Login API</h2>";

// Test 1: Test auth.php endpoint directly
echo "<h3>Test 1: Direct Auth API Test</h3>";

// Simulate POST request for login
$_POST['action'] = 'login';
$_POST['username'] = 'admin';
$_POST['password'] = 'password';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Start output buffering to capture the auth.php response
ob_start();
include 'api/auth.php';
$authResponse = ob_get_clean();

echo "<p><strong>Auth API Response:</strong></p>";
echo "<pre style='background:#f5f5f5; padding:10px; border:1px solid #ddd;'>";
echo htmlspecialchars($authResponse);
echo "</pre>";

// Test 2: Test with HR credentials
echo "<h3>Test 2: HR Login Test</h3>";
$_POST['username'] = 'hr@company.com';
$_POST['password'] = 'hr123';

ob_start();
include 'api/auth.php';
$hrResponse = ob_get_clean();

echo "<p><strong>HR API Response:</strong></p>";
echo "<pre style='background:#f5f5f5; padding:10px; border:1px solid #ddd;'>";
echo htmlspecialchars($hrResponse);
echo "</pre>";

// Test 3: Test with wrong credentials
echo "<h3>Test 3: Wrong Credentials Test</h3>";
$_POST['username'] = 'wrong';
$_POST['password'] = 'wrong';

ob_start();
include 'api/auth.php';
$wrongResponse = ob_get_clean();

echo "<p><strong>Wrong Credentials Response:</strong></p>";
echo "<pre style='background:#f5f5f5; padding:10px; border:1px solid #ddd;'>";
echo htmlspecialchars($wrongResponse);
echo "</pre>";

// Test 4: Check session
echo "<h3>Test 4: Session Check</h3>";
$_SERVER['REQUEST_METHOD'] = 'GET';
unset($_POST);

ob_start();
include 'api/auth.php';
$sessionResponse = ob_get_clean();

echo "<p><strong>Session Check Response:</strong></p>";
echo "<pre style='background:#f5f5f5; padding:10px; border:1px solid #ddd;'>";
echo htmlspecialchars($sessionResponse);
echo "</pre>";

echo "<h3>Summary</h3>";
echo "<p>Jika semua test di atas menunjukkan response JSON yang valid, maka masalah ada di frontend.</p>";
echo "<p>Jika ada error, maka masalah ada di backend/database.</p>";

session_start();
require_once 'config/db.php';

// Simulasi login HR
$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = 2; // HR user
$_SESSION['username'] = 'hr1';
$_SESSION['role'] = 'hr';
$_SESSION['division_id'] = null; // HR tidak terikat division

echo "Login simulation completed.\n";
echo "Session ID: " . session_id() . "\n";
echo "User ID: " . $_SESSION['user_id'] . "\n";
echo "Username: " . $_SESSION['username'] . "\n";
echo "Role: " . $_SESSION['role'] . "\n";

// Test dashboard endpoint
echo "\nTesting dashboard endpoint...\n";
$_GET['action'] = 'dashboard';
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
include 'api/employees.php';
$output = ob_get_clean();

echo "Dashboard response: " . $output . "\n";
?> 