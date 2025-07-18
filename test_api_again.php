<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing API Response...\n";
echo "======================\n";

// Set up session and GET parameters
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'manager';
$_SESSION['division_id'] = 1;

$_GET['action'] = 'employee_data_mining_detail';
$_GET['eid'] = '1';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Capture all output
ob_start();

try {
    include 'employees.php';
} catch (Exception $e) {
    ob_end_clean();
    echo "Exception: " . $e->getMessage() . "\n";
    exit(1);
}

$output = ob_get_clean();

echo "Raw Output Length: " . strlen($output) . "\n";
echo "First 100 characters: " . substr($output, 0, 100) . "\n";
echo "Last 100 characters: " . substr($output, -100) . "\n";

// Try to find where JSON starts
$json_start = strpos($output, '{');
if ($json_start !== false) {
    echo "\nJSON starts at position: " . $json_start . "\n";
    if ($json_start > 0) {
        echo "Content before JSON: [" . substr($output, 0, $json_start) . "]\n";
        echo "Content before JSON (hex): " . bin2hex(substr($output, 0, $json_start)) . "\n";
    }
    
    $json_content = substr($output, $json_start);
    echo "\nTesting JSON validity...\n";
    $decoded = json_decode($json_content, true);
    if ($decoded === null) {
        echo "JSON is INVALID. Error: " . json_last_error_msg() . "\n";
        echo "JSON content (first 500 chars): " . substr($json_content, 0, 500) . "\n";
    } else {
        echo "JSON is VALID\n";
        echo "Employee name: " . ($decoded['employee']['name'] ?? 'N/A') . "\n";
    }
} else {
    echo "\nNo JSON found in output!\n";
    echo "Full output:\n" . $output . "\n";
}
?> 