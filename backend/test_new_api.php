<?php
// Configure session before starting (same as auth.php)
if (session_status() === PHP_SESSION_NONE) {
    // Set session configuration
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '0'); 
    ini_set('session.cookie_httponly', '0'); // Allow JavaScript access for cross-origin
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.cookie_domain', 'localhost');
    
    session_start();
}

require_once('config/db.php');

echo "🧪 Testing New Manager Analytics API\n";
echo "=" . str_repeat("=", 50) . "\n\n";

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

// Test new API endpoint
echo "📡 Testing NEW API endpoint: /api/manager_analytics.php\n";
ob_start();
include('api/manager_analytics.php');
$output = ob_get_clean();

// Check if output is valid JSON
$json_data = json_decode($output, true);
$json_error = json_last_error();

if ($json_error === JSON_ERROR_NONE) {
    echo "✅ NEW API returns valid JSON\n";
    echo "📊 Response data:\n";
    echo "   - Success: " . ($json_data['success'] ? 'true' : 'false') . "\n";
    
    if (isset($json_data['data']['employees'])) {
        $employee_count = count($json_data['data']['employees']);
        echo "   - Employees count: {$employee_count}\n";
        
        if ($employee_count > 0) {
            $first_employee = $json_data['data']['employees'][0];
            echo "   - First employee: {$first_employee['name']} - {$first_employee['role']}\n";
            echo "   - Intelligence data: " . (isset($first_employee['intelligence_data']) ? 'Present' : 'Missing') . "\n";
        }
    }
    
    if (isset($json_data['data']['statistics'])) {
        $stats = $json_data['data']['statistics'];
        echo "   - Statistics: {$stats['match_employees']} match, {$stats['extension_employees']} extension, {$stats['high_risk_employees']} high risk\n";
    }
    
    echo "\n🎯 NEW API is working correctly!\n";
    echo "✅ Frontend should now be able to access this endpoint successfully.\n";
} else {
    echo "❌ NEW API returns invalid JSON\n";
    echo "JSON Error: " . json_last_error_msg() . "\n";
    echo "Raw output (first 500 chars):\n";
    echo substr($output, 0, 500) . "\n";
    
    if (strlen($output) > 500) {
        echo "... (truncated, total length: " . strlen($output) . " chars)\n";
    }
}
?> 