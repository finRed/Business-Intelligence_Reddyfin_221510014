<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Allow CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Check if user is admin or HR
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['error' => 'Only admin or HR can perform this operation']);
    exit;
}

$db = new Database();

try {
    // Find duplicate recommendations for the same employee
    $find_duplicates_sql = "
        SELECT eid, recommended_by, COUNT(*) as duplicate_count,
               GROUP_CONCAT(recommendation_id ORDER BY created_at DESC) as recommendation_ids,
               GROUP_CONCAT(created_at ORDER BY created_at DESC) as created_dates
        FROM recommendations 
        WHERE status = 'pending'
        GROUP BY eid, recommended_by
        HAVING COUNT(*) > 1
        ORDER BY duplicate_count DESC
    ";
    
    $result = $db->query($find_duplicates_sql);
    
    $duplicates_found = [];
    $total_deleted = 0;
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $eid = $row['eid'];
            $recommended_by = $row['recommended_by'];
            $duplicate_count = $row['duplicate_count'];
            $recommendation_ids = explode(',', $row['recommendation_ids']);
            $created_dates = explode(',', $row['created_dates']);
            
            // Get employee and manager names
            $employee_info_sql = "
                SELECT e.name as employee_name, u.username as manager_name
                FROM employees e, users u
                WHERE e.eid = ? AND u.user_id = ?
            ";
            $stmt = $db->prepare($employee_info_sql);
            $stmt->bind_param('ii', $eid, $recommended_by);
            $stmt->execute();
            $info_result = $stmt->get_result();
            $info = $info_result->fetch_assoc();
            
            // Keep the newest recommendation, delete the older ones
            $newest_id = $recommendation_ids[0]; // First one is newest due to ORDER BY created_at DESC
            $ids_to_delete = array_slice($recommendation_ids, 1); // Remove the first (newest) element
            
            $deleted_count = 0;
            if (!empty($ids_to_delete)) {
                $ids_string = implode(',', $ids_to_delete);
                $delete_sql = "DELETE FROM recommendations WHERE recommendation_id IN ($ids_string)";
                
                if ($db->query($delete_sql)) {
                    $deleted_count = count($ids_to_delete);
                    $total_deleted += $deleted_count;
                }
            }
            
            $duplicates_found[] = [
                'employee_id' => $eid,
                'employee_name' => $info['employee_name'],
                'manager_id' => $recommended_by,
                'manager_name' => $info['manager_name'],
                'duplicate_count' => $duplicate_count,
                'recommendation_ids' => $recommendation_ids,
                'created_dates' => $created_dates,
                'kept_id' => $newest_id,
                'deleted_count' => $deleted_count,
                'deleted_ids' => $ids_to_delete
            ];
        }
    }
    
    // Get remaining pending recommendations
    $remaining_sql = "
        SELECT r.recommendation_id, r.eid, e.name as employee_name, 
               r.recommended_by, u.username as manager_name,
               r.recommendation_type, r.recommended_duration,
               r.created_at
        FROM recommendations r
        JOIN employees e ON r.eid = e.eid
        JOIN users u ON r.recommended_by = u.user_id
        WHERE r.status = 'pending'
        ORDER BY r.created_at DESC
    ";
    
    $remaining_result = $db->query($remaining_sql);
    $remaining_recommendations = [];
    
    while ($row = $remaining_result->fetch_assoc()) {
        $remaining_recommendations[] = [
            'recommendation_id' => $row['recommendation_id'],
            'employee_id' => $row['eid'],
            'employee_name' => $row['employee_name'],
            'manager_id' => $row['recommended_by'],
            'manager_name' => $row['manager_name'],
            'recommendation_type' => $row['recommendation_type'],
            'recommended_duration' => $row['recommended_duration'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Pembersihan duplikasi selesai',
        'data' => [
            'duplicates_found' => count($duplicates_found),
            'total_deleted' => $total_deleted,
            'duplicate_details' => $duplicates_found,
            'remaining_recommendations' => $remaining_recommendations,
            'remaining_count' => count($remaining_recommendations)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 