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

// Set CORS headers for API requests (same as auth.php)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
} else {
    header("Access-Control-Allow-Origin: http://localhost:3000");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json");

// Handle preflight
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Database connection
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../api/dynamic_intelligence.php';
    
    // Check authentication - use correct session key names
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
        throw new Exception("Unauthorized access - Manager role required");
    }

    $manager_user_id = $_SESSION['user_id'];
    
    // Get manager's division
    $stmt = $conn->prepare("SELECT division_id FROM users WHERE user_id = ? AND role = 'manager'");
    $stmt->bind_param("i", $manager_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Manager not found");
    }
    
    $manager = $result->fetch_assoc();
    $manager_division_id = $manager['division_id'];
    
    // Get employees from manager's division only
    $employees_sql = "
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
            END as days_to_contract_end
        FROM employees e
        LEFT JOIN divisions d ON e.division_id = d.division_id
        LEFT JOIN contracts c ON e.eid = c.eid 
            AND c.contract_id = (SELECT MAX(contract_id) FROM contracts c2 WHERE c2.eid = e.eid)
        WHERE e.division_id = ? AND e.status = 'active'
        ORDER BY e.join_date DESC
    ";
    
    $stmt = $conn->prepare($employees_sql);
    $stmt->bind_param("i", $manager_division_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $employees = [];
    $match_count = 0;
    $extension_count = 0;
    $high_risk_count = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Basic employee data
        $employee = [
            'eid' => $row['eid'],
            'name' => $row['name'],
            'major' => $row['major'],
            'role' => $row['role'],
            'education_level' => $row['education_level'],
            'division_name' => $row['division_name'],
            'tenure_months' => round($row['tenure_months'], 1),
            'current_contract_type' => $row['current_contract_type'],
            'contract_end' => $row['contract_end_date'],
            'days_to_contract_end' => $row['days_to_contract_end'],
            'join_date' => $row['join_date'],
            'status' => $row['status']
        ];
        
        // Get intelligence data
        try {
            $intelligence_data = getDynamicIntelligenceForEmployee($row, $conn);
            $employee['intelligence_data'] = $intelligence_data;
            
            // Count statistics based on intelligence data
            if (isset($intelligence_data['match_category'])) {
                $category = $intelligence_data['match_category'];
                if (in_array($category, ['Siap Permanent', 'Kontrak 1', 'Sesuai'])) {
                    $match_count++;
                } elseif (in_array($category, ['Kontrak 2', 'Kontrak 3', 'Kontrak 3 (Evaluasi)'])) {
                    $extension_count++;
                } elseif (in_array($category, ['Tidak Direkomendasikan'])) {
                    $high_risk_count++;
                }
            }
        } catch (Exception $e) {
            // If intelligence fails, set basic data
            $employee['intelligence_data'] = [
                'match_category' => 'Evaluasi',
                'recommended_duration' => 6,
                'risk_level' => 'Medium',
                'error' => $e->getMessage()
            ];
            $extension_count++; // Count as extension if we can't determine
        }
        
        $employees[] = $employee;
    }
    
    // Get contract distribution for charts
    $contract_distribution = [];
    $duration_distribution = [6 => 0, 12 => 0, 18 => 0, 24 => 0, 36 => 0];
    
    foreach ($employees as $emp) {
        if (isset($emp['intelligence_data']['match_category'])) {
            $category = $emp['intelligence_data']['match_category'];
            $contract_distribution[$category] = ($contract_distribution[$category] ?? 0) + 1;
        }
        
        if (isset($emp['intelligence_data']['recommended_duration'])) {
            $duration = $emp['intelligence_data']['recommended_duration'];
            if (isset($duration_distribution[$duration])) {
                $duration_distribution[$duration]++;
            }
        }
    }
    
    $response = [
        'success' => true,
        'data' => [
            'employees' => $employees,
            'statistics' => [
                'total_employees' => count($employees),
                'match_employees' => $match_count,
                'extension_employees' => $extension_count,
                'high_risk_employees' => $high_risk_count
            ],
            'contract_distribution' => $contract_distribution,
            'duration_distribution' => $duration_distribution,
            'manager_info' => [
                'division_id' => $manager_division_id,
                'user_id' => $manager_user_id
            ]
        ]
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 