<?php
session_start();
require_once 'backend/config/db.php';

// Simulate manager session
$_SESSION['user_id'] = 1;  // Change this to actual manager user_id
$_SESSION['role'] = 'manager';
$_SESSION['division_id'] = 1;  // Change this to actual division_id
$_SESSION['logged_in'] = true;

echo "=== MANAGER FILTER TEST ===\n";
echo "Testing with Manager Role\n";
echo "Division ID: " . $_SESSION['division_id'] . "\n\n";

try {
    // Test the same logic as in employees.php
    $role = $_SESSION['role'];
    $division_id = $_SESSION['division_id'];
    
    if ($role === 'manager') {
        $sql = "SELECT e.*, d.division_name, 
                       c.type as current_contract_type
                FROM employees e 
                LEFT JOIN divisions d ON e.division_id = d.division_id
                LEFT JOIN contracts c ON e.eid = c.eid 
                    AND c.contract_id = (
                        SELECT contract_id 
                        FROM contracts c2 
                        WHERE c2.eid = e.eid 
                        ORDER BY 
                            CASE WHEN c2.status = 'active' THEN 1 ELSE 2 END,
                            c2.contract_id DESC
                        LIMIT 1
                    )
                WHERE e.division_id = ? AND e.status = 'active'
                ORDER BY e.created_at DESC
                LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $division_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "EMPLOYEES IN MANAGER'S DIVISION:\n";
        echo str_pad("Name", 25) . str_pad("Division", 20) . str_pad("Contract", 15) . "\n";
        echo str_repeat("-", 60) . "\n";
        
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $count++;
            echo str_pad(substr($row['name'], 0, 24), 25) . 
                 str_pad($row['division_name'] ?? 'N/A', 20) . 
                 str_pad($row['current_contract_type'] ?? 'N/A', 15) . "\n";
        }
        
        echo "\nTotal employees shown: $count\n";
        
        // Compare with total employees in database
        $total_sql = "SELECT COUNT(*) as total FROM employees WHERE status = 'active'";
        $total_result = $conn->query($total_sql);
        $total = $total_result->fetch_assoc()['total'];
        
        echo "Total employees in database: $total\n";
        echo "Filter working: " . ($count < $total ? "YES" : "NO") . "\n";
        
    } else {
        echo "Error: Not testing with manager role\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 