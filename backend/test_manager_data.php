<?php
// Test manager analytics API
session_start();

// Set a manager session for testing
$_SESSION['user_id'] = 2; // Assuming manager user ID
$_SESSION['role'] = 'manager';
$_SESSION['user_role'] = 'manager';
$_SESSION['division_id'] = 1;

echo "=== Testing Manager Analytics API ===\n";
echo "Session Role: " . ($_SESSION['role'] ?? 'Not set') . "\n";
echo "Session User Role: " . ($_SESSION['user_role'] ?? 'Not set') . "\n";
echo "Session Division ID: " . ($_SESSION['division_id'] ?? 'Not set') . "\n\n";

// Include the manager analytics file
ob_start();
include 'manager_analytics_simple.php';
$output = ob_get_clean();

echo "=== API Output ===\n";
echo $output . "\n\n";

// Try to parse as JSON
$data = json_decode($output, true);
if ($data) {
    echo "=== Parsed Data Analysis ===\n";
    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    
    if (isset($data['data']['employees'])) {
        echo "Total Employees: " . count($data['data']['employees']) . "\n";
        echo "Active Employees: " . $data['data']['total_active_employees'] . "\n";
        echo "Match Employees: " . $data['data']['match_employees'] . "\n";
        echo "Extension Recommendations: " . $data['data']['extension_recommendations'] . "\n";
        echo "High Risk Employees: " . $data['data']['high_risk_employees'] . "\n";
        
        // Show first few employees
        echo "\n=== First 3 Employees ===\n";
        for ($i = 0; $i < min(3, count($data['data']['employees'])); $i++) {
            $emp = $data['data']['employees'][$i];
            echo "Employee " . ($i+1) . ": " . $emp['name'] . " (EID: " . $emp['eid'] . ")\n";
            echo "  Status: " . $emp['status'] . "\n";
            echo "  Contract: " . $emp['current_contract_type'] . "\n";
            echo "  Education Match: " . $emp['education_job_match'] . "\n";
            echo "  Risk Level: " . $emp['risk_level'] . "\n\n";
        }
    } else {
        echo "No employees data found\n";
        if (isset($data['error'])) {
            echo "Error: " . $data['error'] . "\n";
        }
    }
} else {
    echo "Failed to parse JSON output\n";
    echo "Raw output: $output\n";
}
?> 