<?php
// Simulate session for testing
session_start();
$_SESSION['user_id'] = 3; // Using user_id 3 which is manager with division 1
$_SESSION['role'] = 'manager';

// Test manager analytics
echo "🔍 TESTING MANAGER ANALYTICS WITH DATA INTELLIGENCE\n";
echo "=================================================\n\n";

// Capture output
ob_start();
include 'manager_analytics_by_division.php';
$output = ob_get_clean();

// Parse JSON response
$response = json_decode($output, true);

if ($response && $response['success']) {
    echo "✅ Manager Analytics API Success\n\n";
    
    // Check employees data
    $employees = $response['data']['employees'] ?? [];
    
    echo "📊 FOUND " . count($employees) . " EMPLOYEES\n";
    echo str_repeat("-", 50) . "\n";
    
    foreach ($employees as $emp) {
        echo "👤 {$emp['name']}\n";
        echo "   Education: {$emp['major']}\n";
        echo "   Role: {$emp['role']}\n";
        echo "   Contract: {$emp['current_contract_type']}\n";
        echo "   Match: {$emp['education_job_match']}\n";
        
        // Check data mining recommendation
        if (isset($emp['data_mining_recommendation'])) {
            $rec = $emp['data_mining_recommendation'];
            echo "   📈 Data Intelligence:\n";
            echo "      Category: {$rec['category']}\n";
            echo "      Duration: {$rec['recommended_duration']} bulan\n";
            echo "      Confidence: {$rec['confidence_level']}\n";
            
            // Special check for Kontrak 3
            if ($emp['current_contract_type'] === 'kontrak3') {
                echo "   🎯 KONTRAK 3 CHECK:\n";
                echo "      Expected: Siap Permanent, 0 bulan (display 24)\n";
                echo "      Actual: {$rec['category']}, {$rec['recommended_duration']} bulan\n";
                
                if ($rec['category'] === 'Siap Permanent' && $rec['recommended_duration'] === 0) {
                    echo "      ✅ CORRECT - Kontrak 3 logic working!\n";
                } else {
                    echo "      ❌ ERROR - Kontrak 3 logic not working!\n";
                }
            }
        }
        
        echo "\n";
    }
    
    // Division stats
    echo "📈 DIVISION STATISTICS:\n";
    echo "   Total Employees: " . ($response['data']['division_stats']['total_employees'] ?? 0) . "\n";
    echo "   Match Count: " . ($response['data']['division_stats']['match_count'] ?? 0) . "\n";
    echo "   Unmatch Count: " . ($response['data']['division_stats']['unmatch_count'] ?? 0) . "\n";
    echo "   Match Percentage: " . ($response['data']['division_stats']['match_percentage'] ?? 0) . "%\n";
    
} else {
    echo "❌ ERROR: " . ($response['error'] ?? 'Unknown error') . "\n";
    echo "Raw output: " . substr($output, 0, 500) . "\n";
}

echo "\n✅ TESTING COMPLETED\n";
?> 