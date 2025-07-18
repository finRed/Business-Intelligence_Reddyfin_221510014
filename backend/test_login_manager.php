<?php
// Test manager login
session_start();

$conn = new mysqli('localhost', 'root', '', 'contract_rec_db');

// Get manager user
$result = $conn->query("SELECT * FROM users WHERE role = 'manager' AND division_id IS NOT NULL LIMIT 1");
$manager = $result->fetch_assoc();

if ($manager) {
    echo "TESTING MANAGER LOGIN:\n";
    echo "====================\n\n";
    
    // Simulate login
    $_SESSION['user_id'] = $manager['user_id'];
    $_SESSION['username'] = $manager['username'];
    $_SESSION['role'] = $manager['role'];
    $_SESSION['division_id'] = $manager['division_id'];
    
    echo "✅ Manager logged in:\n";
    echo "   ID: {$manager['user_id']}\n";
    echo "   Username: {$manager['username']}\n";
    echo "   Role: {$manager['role']}\n";
    echo "   Division: {$manager['division_id']}\n\n";
    
    // Test the API call
    echo "🔍 Testing Manager Analytics API:\n";
    echo str_repeat("-", 40) . "\n";
    
    ob_start();
    include 'manager_analytics_by_division.php';
    $output = ob_get_clean();
    
    $response = json_decode($output, true);
    
    if ($response && $response['success']) {
        echo "✅ API SUCCESS\n";
        echo "   Employees: " . count($response['data']['employees']) . "\n";
        echo "   Division Stats: " . json_encode($response['data']['division_stats']) . "\n";
    } else {
        echo "❌ API FAILED\n";
        echo "   Error: " . ($response['error'] ?? 'Unknown') . "\n";
        echo "   Output: " . substr($output, 0, 300) . "\n";
    }
    
} else {
    echo "❌ No manager found in database\n";
}

$conn->close();
?> 