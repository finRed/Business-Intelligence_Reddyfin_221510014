<?php
require_once __DIR__ . '/config/db.php';

// Start session to get manager info
session_start();

echo "=== DEBUG MANAGER SYNC ISSUE ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Check Ahmad Hamdi specifically (eid = 15 based on screenshot)
$ahmad_eid = 15;

echo "1. CHECKING AHMAD HAMDI'S CURRENT CONTRACT:\n";
$contract_query = "
    SELECT 
        c.contract_id,
        c.eid,
        c.type,
        c.start_date,
        c.end_date,
        c.status,
        c.notes,
        DATEDIFF(c.end_date, CURDATE()) as days_to_end
    FROM contracts c 
    WHERE c.eid = ? 
    ORDER BY c.start_date DESC
";

$stmt = $conn->prepare($contract_query);
$stmt->bind_param("i", $ahmad_eid);
$stmt->execute();
$contracts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($contracts as $contract) {
    echo "Contract ID: {$contract['contract_id']}\n";
    echo "Type: {$contract['type']}\n";
    echo "Status: {$contract['status']}\n";
    echo "Start: {$contract['start_date']}\n";
    echo "End: {$contract['end_date']}\n";
    echo "Days to end: {$contract['days_to_end']}\n";
    echo "Notes: {$contract['notes']}\n";
    echo "---\n";
}

echo "\n2. CHECKING AHMAD HAMDI'S RECOMMENDATIONS:\n";
$rec_query = "
    SELECT 
        r.recommendation_id,
        r.eid,
        r.recommendation_type,
        r.recommended_duration,
        r.status,
        r.recommended_by,
        r.created_at,
        r.updated_at,
        r.reason
    FROM recommendations r 
    WHERE r.eid = ? 
    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($rec_query);
$stmt->bind_param("i", $ahmad_eid);
$stmt->execute();
$recommendations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($recommendations as $rec) {
    echo "Rec ID: {$rec['recommendation_id']}\n";
    echo "Type: {$rec['recommendation_type']}\n";
    echo "Duration: {$rec['recommended_duration']}\n";
    echo "Status: {$rec['status']}\n";
    echo "By: {$rec['recommended_by']}\n";
    echo "Created: {$rec['created_at']}\n";
    echo "Updated: {$rec['updated_at']}\n";
    echo "---\n";
}

echo "\n3. CHECKING MANAGER ANALYTICS QUERY RESULT:\n";
// Simulate the exact query from manager_analytics_by_division.php
$manager_user_id = 2; // Manager2 from screenshot
$division_id = 1; // PTSA division

$analytics_query = "
    SELECT 
        e.eid,
        e.name,
        e.role,
        e.major,
        e.education_level,
        e.join_date,
        e.status,
        d.division_name,
        DATEDIFF(CURDATE(), e.join_date) / 30 as tenure_months,
        c.type as current_contract_type,
        c.end_date as contract_end_date,
        CASE 
            WHEN c.end_date IS NULL THEN NULL
            ELSE DATEDIFF(c.end_date, CURDATE())
        END as days_to_contract_end,
        CASE 
            WHEN r.recommendation_id IS NOT NULL THEN 1
            ELSE 0
        END as has_pending_recommendation,
        (SELECT COUNT(*) FROM recommendations WHERE eid = e.eid) as total_recommendations,
        (SELECT COUNT(*) FROM recommendations WHERE eid = e.eid AND status = 'approved') as approved_recommendations,
        (SELECT COUNT(*) FROM recommendations WHERE eid = e.eid AND status = 'pending') as pending_recommendations,
        (SELECT status FROM recommendations WHERE eid = e.eid AND status IN ('approved', 'rejected') ORDER BY updated_at DESC LIMIT 1) as latest_processed_status
    FROM employees e
    LEFT JOIN divisions d ON e.division_id = d.division_id
    LEFT JOIN contracts c ON e.eid = c.eid AND c.status = 'active'
    LEFT JOIN recommendations r ON e.eid = r.eid AND r.status = 'pending' AND r.recommended_by = ?
    WHERE e.eid = ? AND e.status = 'active'
";

$stmt = $conn->prepare($analytics_query);
$stmt->bind_param("ii", $manager_user_id, $ahmad_eid);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result) {
    echo "Name: {$result['name']}\n";
    echo "Current Contract Type: {$result['current_contract_type']}\n";
    echo "Contract End Date: {$result['contract_end_date']}\n";
    echo "Days to Contract End: {$result['days_to_contract_end']}\n";
    echo "Has Pending Recommendation: {$result['has_pending_recommendation']}\n";
    echo "Total Recommendations: {$result['total_recommendations']}\n";
    echo "Approved Recommendations: {$result['approved_recommendations']}\n";
    echo "Pending Recommendations: {$result['pending_recommendations']}\n";
    echo "Latest Processed Status: {$result['latest_processed_status']}\n";
} else {
    echo "No result found!\n";
}

echo "\n=== END DEBUG ===\n";
?> 