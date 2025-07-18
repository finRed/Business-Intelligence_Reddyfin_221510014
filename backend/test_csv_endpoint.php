<?php
echo "<h2>CSV Upload Endpoint Test</h2>";

// Test 1: Check if we can reach the endpoint
echo "<h3>1. Basic Endpoint Test</h3>";
$url = "http://localhost/web_srk_BI/backend/api/employees.php?action=uploadCSV";
echo "Testing URL: $url<br>";

// Use cURL to test
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['test' => 'value']);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode<br>";
echo "Response: <pre>$response</pre>";
if ($error) {
    echo "cURL Error: $error<br>";
}

// Test 2: Direct PHP include test
echo "<h3>2. Direct PHP Include Test</h3>";
try {
    session_start();
    
    // Simulate being logged in as HR
    $_SESSION['logged_in'] = true;
    $_SESSION['role'] = 'hr';
    $_SESSION['user_id'] = 2;
    $_SESSION['username'] = 'HR1';
    
    echo "Session set up as HR user<br>";
    echo "Logged in: " . ($_SESSION['logged_in'] ? 'Yes' : 'No') . "<br>";
    echo "Role: " . $_SESSION['role'] . "<br>";
    
    // Test the function directly
    require_once __DIR__ . '/backend/config/db.php';
    
    // Simulate a GET request with action=uploadCSV
    $_GET['action'] = 'uploadCSV';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    echo "Attempting to include employees.php...<br>";
    
    // Capture any output
    ob_start();
    include 'backend/api/employees.php';
    $output = ob_get_clean();
    
    echo "Output from employees.php: <pre>$output</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?> 