<?php
session_start();
require_once('config/db.php');

echo "🔬 Testing Clean API Response\n";
echo "=" . str_repeat("=", 40) . "\n\n";

// Set up manager session
$sql = "SELECT user_id, username, role, division_id FROM users WHERE role = 'manager' LIMIT 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $manager = $result->fetch_assoc();
    $_SESSION['user_id'] = $manager['user_id'];
    $_SESSION['user_role'] = $manager['role'];
    $_SESSION['division_id'] = $manager['division_id'];
    $_SESSION['logged_in'] = true;
    
    echo "✅ Manager session set: {$manager['username']} (Division {$manager['division_id']})\n\n";
} else {
    echo "❌ No manager found\n";
    exit;
}

// Test API call with output buffering
echo "📡 Testing API endpoint...\n";
ob_start();
include('manager_analytics_by_division.php');
$output = ob_get_clean();

// Check if output is valid JSON
$json_data = json_decode($output, true);
$json_error = json_last_error();

if ($json_error === JSON_ERROR_NONE) {
    echo "✅ API returns valid JSON\n";
    echo "📊 Data structure:\n";
    echo "   - Success: " . ($json_data['success'] ? 'true' : 'false') . "\n";
    
    if (isset($json_data['data']['employees'])) {
        $employee_count = count($json_data['data']['employees']);
        echo "   - Employees count: {$employee_count}\n";
        
        if ($employee_count > 0) {
            $first_employee = $json_data['data']['employees'][0];
            echo "   - Sample employee: {$first_employee['name']} - {$first_employee['role']}\n";
            echo "   - Intelligence data present: " . (isset($first_employee['intelligence_data']) ? 'Yes' : 'No') . "\n";
        }
    }
    
    if (isset($json_data['data']['statistics'])) {
        $stats = $json_data['data']['statistics'];
        echo "   - Statistics: {$stats['match_employees']} match, {$stats['extension_employees']} extension, {$stats['high_risk_employees']} high risk\n";
    }
    
    echo "\n🎯 API is working correctly!\n";
} else {
    echo "❌ API returns invalid JSON\n";
    echo "JSON Error: " . json_last_error_msg() . "\n";
    echo "Raw output (first 500 chars):\n";
    echo substr($output, 0, 500) . "\n";
    
    if (strlen($output) > 500) {
        echo "... (truncated, total length: " . strlen($output) . " chars)\n";
    }
}
?> 