<?php
session_start();
require_once('config/db.php');

echo "🔬 Testing Fixed Manager Analytics API\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Set up proper manager session 
$sql = "SELECT user_id, username, role, division_id FROM users WHERE role = 'manager' LIMIT 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $manager = $result->fetch_assoc();
    $_SESSION['user_id'] = $manager['user_id'];
    $_SESSION['role'] = $manager['role'];  // Use 'role' not 'user_role'
    $_SESSION['division_id'] = $manager['division_id'];
    $_SESSION['logged_in'] = true;
    
    echo "✅ Manager session set:\n";
    echo "   User ID: {$manager['user_id']}\n";
    echo "   Username: {$manager['username']}\n";
    echo "   Role: {$manager['role']}\n";
    echo "   Division ID: {$manager['division_id']}\n\n";
} else {
    echo "❌ No manager found\n";
    exit;
}

echo "📡 Testing API call...\n";

// Test API with output buffering
ob_start();
try {
    include('api/manager_analytics.php');
    $output = ob_get_clean();
    
    // Try to parse JSON
    $json_data = json_decode($output, true);
    $json_error = json_last_error();
    
    if ($json_error === JSON_ERROR_NONE) {
        echo "✅ API returned valid JSON\n";
        echo "📊 Response summary:\n";
        echo "   Success: " . ($json_data['success'] ? 'true' : 'false') . "\n";
        
        if ($json_data['success']) {
            $employees = $json_data['data']['employees'] ?? [];
            $stats = $json_data['data']['statistics'] ?? [];
            
            echo "   Total employees: " . count($employees) . "\n";
            echo "   Match employees: " . ($stats['match_employees'] ?? 0) . "\n";
            echo "   Extension employees: " . ($stats['extension_employees'] ?? 0) . "\n";
            echo "   High risk employees: " . ($stats['high_risk_employees'] ?? 0) . "\n";
            
            if (count($employees) > 0) {
                echo "\n📋 Sample employees:\n";
                foreach (array_slice($employees, 0, 3) as $i => $emp) {
                    echo "   " . ($i+1) . ". {$emp['name']} - {$emp['role']} - " . 
                         ($emp['intelligence_data']['match_category'] ?? 'No category') . "\n";
                }
            }
        } else {
            echo "   Error: " . ($json_data['error'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "❌ Invalid JSON response\n";
        echo "JSON Error: " . json_last_error_msg() . "\n";
        echo "Raw output (first 300 chars): " . substr($output, 0, 300) . "\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ PHP Exception: " . $e->getMessage() . "\n";
}

echo "\n🎯 " . str_repeat("=", 50) . "\n";
echo "✅ Test completed!\n";
?> 