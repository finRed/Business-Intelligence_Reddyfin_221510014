<?php
session_start();

// Set CORS headers for API requests
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
} else {
    header("Access-Control-Allow-Origin: http://localhost:3000");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set content type for API responses
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get data intelligence recommendation for the employee
if (isset($_GET['get_intelligence']) && isset($_GET['eid'])) {
    $eid = $_GET['eid'];
    
    try {
        // Get employee data
        $stmt = $db->prepare("
            SELECT e.*, 
                   TIMESTAMPDIFF(MONTH, e.join_date, CURDATE()) as tenure_months,
                   c.type as current_contract_type
            FROM employees e 
            LEFT JOIN contracts c ON e.eid = c.eid AND c.status = 'active'
            WHERE e.eid = ?
        ");
        $stmt->bind_param('i', $eid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Employee not found');
        }
        
        $employee = $result->fetch_assoc();
        
        // Include functions from employees.php
        require_once '../api/employees.php';
        
        // Generate data mining recommendation with resign intelligence
        $recommendation = generateDataMiningContractRecommendation($employee, $db);
        
        echo json_encode([
            'success' => true,
            'employee' => [
                'eid' => $employee['eid'],
                'name' => $employee['name'],
                'role' => $employee['role'],
                'major' => $employee['major'],
                'education_level' => $employee['education_level'],
                'tenure_months' => $employee['tenure_months']
            ],
            'intelligence_recommendation' => $recommendation
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

switch ($method) {
    case 'GET':
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
            if ($action === 'all') {
                getAllRecommendations($db);
            } elseif ($action === 'pending') {
                getPendingRecommendations($db);
            } elseif ($action === 'processed') {
                getManagerProcessedRecommendations($db);
            } elseif ($action === 'by_manager') {
                getRecommendationsByManager($db);
            } elseif ($action === 'detail' && isset($_GET['id'])) {
                getRecommendationDetail($db, $_GET['id']);
            } else {
                getAllRecommendations($db);
            }
        } else {
            getAllRecommendations($db);
        }
        break;
        
    case 'POST':
        error_log("=== POST REQUEST DEBUG ===");
        error_log("_POST: " . print_r($_POST, true));
        error_log("php://input: " . file_get_contents('php://input'));
        error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        
        // Handle both FormData and JSON input
        $input_data = $_POST;
        
        // If POST is empty, try to parse JSON from php://input
        if (empty($_POST)) {
            $raw_input = file_get_contents('php://input');
            if (!empty($raw_input)) {
                $json_data = json_decode($raw_input, true);
                if ($json_data) {
                    $input_data = $json_data;
                }
            }
        }
        
        error_log("Final input_data: " . print_r($input_data, true));
        
        if (isset($input_data['action'])) {
            $action = $input_data['action'];
            error_log("Action: " . $action);
            
            if ($action === 'create') {
                createRecommendation($db, $input_data);
            } elseif ($action === 'process') {
                processRecommendation($db, $input_data);
            } elseif ($action === 'mark_extended') {
                markContractExtended($db, $input_data);
            } else {
                error_log("Unknown action: " . $action);
                http_response_code(400);
                echo json_encode(['error' => 'Unknown action: ' . $action]);
            }
        } else {
            error_log("Missing action parameter in input_data");
            http_response_code(400);
            echo json_encode(['error' => 'Missing action parameter']);
        }
        break;
        
    case 'PUT':
        parse_str(file_get_contents("php://input"), $_PUT);
        if (isset($_PUT['action']) && $_PUT['action'] === 'update_status') {
            updateRecommendationStatus($db, $_PUT);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function getAllRecommendations($db) {
    try {
        // Only HR can see all recommendations
        if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
            throw new Exception('Akses ditolak. Hanya HR yang dapat melihat semua rekomendasi.');
        }
        
        $stmt = $db->prepare("
            SELECT r.*, 
                   e.name as employee_name, 
                   e.email as employee_email,
                   e.role as employee_role,
                   d.division_name,
                   u.username as recommended_by_name,
                   md.division_name as manager_division_name,
                   c.type as current_contract_type,
                   c.end_date as current_contract_end
            FROM recommendations r
            JOIN employees e ON r.eid = e.eid
            LEFT JOIN divisions d ON e.division_id = d.division_id
            LEFT JOIN users u ON r.recommended_by = u.user_id
            LEFT JOIN divisions md ON u.division_id = md.division_id
            LEFT JOIN contracts c ON e.eid = c.eid AND c.status = 'active'
                AND c.contract_id = (
                    SELECT MAX(contract_id) 
                    FROM contracts c2 
                    WHERE c2.eid = e.eid AND c2.status = 'active'
                )
            WHERE u.role = 'manager'
            ORDER BY r.created_at DESC
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $recommendations = [];
        while ($row = $result->fetch_assoc()) {
            $recommendations[] = [
                'recommendation_id' => $row['recommendation_id'],
                'employee' => [
                    'eid' => $row['eid'],
                    'name' => $row['employee_name'],
                    'email' => $row['employee_email'],
                    'role' => $row['employee_role'],
                    'division' => $row['division_name']
                ],
                'recommendation_type' => $row['recommendation_type'],
                'recommended_duration' => $row['recommended_duration'],
                'contract_start_date' => $row['contract_start_date'],
                'reason' => $row['reason'],
                'system_recommendation' => $row['system_recommendation'],
                'status' => $row['status'],
                'recommended_by' => $row['recommended_by_name'],
                'manager_division' => $row['manager_division_name'],
                'current_contract' => [
                    'type' => $row['current_contract_type'],
                    'end_date' => $row['current_contract_end']
                ],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $recommendations
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getPendingRecommendations($db) {
    try {
        // Only HR can process recommendations
        if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
            throw new Exception('Akses ditolak. Hanya HR yang dapat memproses rekomendasi.');
        }
        
        $stmt = $db->prepare("
            SELECT r.*, 
                   e.name as employee_name, 
                   e.email as employee_email,
                   e.role as employee_role,
                   e.join_date,
                   d.division_name,
                   u.username as recommended_by_name,
                   md.division_name as manager_division_name,
                   c.type as current_contract_type,
                   c.end_date as current_contract_end,
                   DATEDIFF(c.end_date, CURDATE()) as days_until_contract_end
            FROM recommendations r
            JOIN employees e ON r.eid = e.eid
            LEFT JOIN divisions d ON e.division_id = d.division_id
            LEFT JOIN users u ON r.recommended_by = u.user_id
            LEFT JOIN divisions md ON u.division_id = md.division_id
            LEFT JOIN contracts c ON e.eid = c.eid AND c.status = 'active'
                AND c.contract_id = (
                    SELECT MAX(contract_id) 
                    FROM contracts c2 
                    WHERE c2.eid = e.eid AND c2.status = 'active'
                )
            WHERE r.status = 'pending' AND u.role = 'manager'
            ORDER BY c.end_date ASC, r.created_at DESC
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $recommendations = [];
        while ($row = $result->fetch_assoc()) {
            $recommendations[] = [
                'recommendation_id' => $row['recommendation_id'],
                'employee' => [
                    'eid' => $row['eid'],
                    'name' => $row['employee_name'],
                    'email' => $row['employee_email'],
                    'role' => $row['employee_role'],
                    'division' => $row['division_name'],
                    'join_date' => $row['join_date']
                ],
                'recommendation_type' => $row['recommendation_type'],
                'recommended_duration' => $row['recommended_duration'],
                'contract_start_date' => $row['contract_start_date'],
                'reason' => $row['reason'],
                'system_recommendation' => $row['system_recommendation'],
                'status' => $row['status'],
                'recommended_by' => $row['recommended_by_name'],
                'manager_division' => $row['manager_division_name'],
                'current_contract' => [
                    'type' => $row['current_contract_type'],
                    'end_date' => $row['current_contract_end'],
                    'days_until_end' => $row['days_until_contract_end']
                ],
                'urgency' => $row['days_until_contract_end'] <= 14 ? 'high' : ($row['days_until_contract_end'] <= 30 ? 'medium' : 'low'),
                'created_at' => $row['created_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $recommendations
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getManagerProcessedRecommendations($db) {
    try {
        // Only managers can see their processed recommendations
        if ($_SESSION['role'] !== 'manager') {
            throw new Exception('Akses ditolak. Hanya manager yang dapat melihat rekomendasi mereka.');
        }
        
        $stmt = $db->prepare("
            SELECT r.*, 
                   e.name as employee_name, 
                   e.email as employee_email,
                   e.role as employee_role,
                   d.division_name,
                   u.username as recommended_by_name,
                   c.type as new_contract_type,
                   c.start_date as new_contract_start,
                   c.end_date as new_contract_end,
                   c.status as new_contract_status,
                   c.notes as contract_notes
            FROM recommendations r
            JOIN employees e ON r.eid = e.eid
            LEFT JOIN divisions d ON e.division_id = d.division_id
            LEFT JOIN users u ON r.recommended_by = u.user_id
            LEFT JOIN contracts c ON e.eid = c.eid AND c.status = 'active' AND c.notes LIKE '%rekomendasi manager%'
            WHERE r.recommended_by = ? AND r.status IN ('approved', 'rejected', 'extended', 'resign') AND u.role = 'manager'
            ORDER BY r.updated_at DESC
        ");
        
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $recommendations = [];
        while ($row = $result->fetch_assoc()) {
            $recommendations[] = [
                'recommendation_id' => $row['recommendation_id'],
                'employee' => [
                    'eid' => $row['eid'],
                    'name' => $row['employee_name'],
                    'email' => $row['employee_email'],
                    'role' => $row['employee_role'],
                    'division' => $row['division_name']
                ],
                'recommendation_type' => $row['recommendation_type'],
                'recommended_duration' => $row['recommended_duration'],
                'contract_start_date' => $row['contract_start_date'],
                'reason' => $row['reason'],
                'system_recommendation' => $row['system_recommendation'],
                'status' => $row['status'],
                'recommended_by' => $row['recommended_by_name'],
                'new_contract' => [
                    'type' => $row['new_contract_type'],
                    'start_date' => $row['new_contract_start'],
                    'end_date' => $row['new_contract_end'],
                    'status' => $row['new_contract_status'],
                    'notes' => $row['contract_notes'],
                    'is_created' => !empty($row['new_contract_type'])
                ],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $recommendations
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function createRecommendation($db, $data = null) {
    try {
        // Use provided data or fallback to $_POST
        $input_data = $data ?? $_POST;
        
        // Debug logging
        error_log("Creating recommendation - Session data: " . print_r($_SESSION, true));
        error_log("Creating recommendation - Input data: " . print_r($input_data, true));
        error_log("Creating recommendation - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("Creating recommendation - CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        
        // Only managers can create recommendations
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
            throw new Exception('Akses ditolak. Hanya manager yang dapat membuat rekomendasi. HR tidak diizinkan membuat rekomendasi.');
        }
        
        // Validate required fields
        if (!isset($input_data['eid']) || !isset($input_data['recommendation_type'])) {
            $missing_fields = [];
            if (!isset($input_data['eid'])) $missing_fields[] = 'eid';
            if (!isset($input_data['recommendation_type'])) $missing_fields[] = 'recommendation_type';
            error_log("Missing fields: " . implode(', ', $missing_fields));
            throw new Exception('Data tidak lengkap. Field yang hilang: ' . implode(', ', $missing_fields));
        }
        
        // Validate EID is numeric
        if (!is_numeric($input_data['eid'])) {
            error_log("Invalid EID: " . $input_data['eid']);
            throw new Exception('EID harus berupa angka');
        }
        
        $eid = intval($input_data['eid']);
        $recommendation_type = $input_data['recommendation_type'];
        $recommended_duration = $input_data['recommended_duration'] ?? null;
        $contract_start_date = $input_data['contract_start_date'] ?? null;
        $reason = trim($input_data['reason'] ?? ''); // Allow empty reason
        $system_recommendation = $input_data['system_recommendation'] ?? '';
        $recommended_by = $_SESSION['user_id'];
        
        // Validate recommendation type
        $valid_types = ['extend', 'permanent', 'terminate', 'review', 'kontrak1', 'kontrak2', 'kontrak3'];
        if (!in_array($recommendation_type, $valid_types)) {
            error_log("Invalid recommendation type: " . $recommendation_type);
            throw new Exception('Jenis rekomendasi tidak valid');
        }
        
        // Note: Reason validation removed - empty reasons are now allowed per user request
        
        // Verify employee is in manager's division
        $check_stmt = $db->prepare("
            SELECT e.eid, e.name, e.division_id 
            FROM employees e 
            WHERE e.eid = ? AND e.status = 'active'
        ");
        $check_stmt->bind_param('i', $eid);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception('Karyawan tidak ditemukan atau sudah tidak aktif');
        }
        
        $employee = $check_result->fetch_assoc();
        
        // Check division if manager role - but allow if no division restriction
        if (isset($_SESSION['division_id']) && $_SESSION['division_id'] && 
            $employee['division_id'] && $employee['division_id'] != $_SESSION['division_id']) {
            throw new Exception('Karyawan tidak ditemukan atau bukan dari divisi Anda');
        }
        
        // Log employee data for debugging
        error_log("Employee data: " . print_r($employee, true));
        
        // Allow recommendations for all employees
        // No education_job_match restriction since it's a computed field
        // (HR can still reject during approval process)
        
        // Check if there's already a pending recommendation for this employee by this manager
        $pending_check = $db->prepare("
            SELECT recommendation_id 
            FROM recommendations 
            WHERE eid = ? AND recommended_by = ? AND status = 'pending'
        ");
        $pending_check->bind_param('ii', $eid, $recommended_by);
        $pending_check->execute();
        $pending_result = $pending_check->get_result();
        
        if ($pending_result->num_rows > 0) {
            throw new Exception('Anda sudah memiliki rekomendasi yang sedang pending untuk karyawan ini. Tunggu hingga HR memproses rekomendasi sebelumnya.');
        }
        
        // Check if employee has active contract (more flexible rules)
        $contract_check = $db->prepare("
            SELECT c.contract_id, c.end_date, c.type,
                   DATEDIFF(c.end_date, CURDATE()) as days_remaining
            FROM contracts c
            WHERE c.eid = ? AND c.status = 'active'
            ORDER BY c.start_date DESC
            LIMIT 1
        ");
        $contract_check->bind_param('i', $eid);
        $contract_check->execute();
        $contract_result = $contract_check->get_result();
        
        if ($contract_result->num_rows > 0) {
            $contract = $contract_result->fetch_assoc();
            
            // Log contract data for debugging
            error_log("Contract data: " . print_r($contract, true));
            
            // Allow recommendations for permanent contracts (for changes/reviews)
            if ($contract['end_date'] === null) {
                error_log("Employee has permanent contract but allowing recommendation for review/changes");
            }
            
            // More flexible timing - allow recommendations up to 60 days before expiration
            if ($contract['end_date'] !== null && $contract['days_remaining'] > 60) {
                $end_date = date('d/m/Y', strtotime($contract['end_date']));
                error_log("Contract still has {$contract['days_remaining']} days remaining, but allowing recommendation");
                // Don't throw exception - just log for now
            }
        }
        
        // Insert recommendation
        $stmt = $db->prepare("
            INSERT INTO recommendations (eid, recommended_by, recommendation_type, recommended_duration, contract_start_date, reason, system_recommendation, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        // Convert null to 0 for recommended_duration if needed
        if ($recommended_duration === null || $recommended_duration === '') {
            $recommended_duration = 0;
        }
        
        // Convert empty contract_start_date to NULL
        if ($contract_start_date === '' || $contract_start_date === null) {
            $contract_start_date = null;
        }
        
        $stmt->bind_param('iisisss', $eid, $recommended_by, $recommendation_type, $recommended_duration, $contract_start_date, $reason, $system_recommendation);
        
        if ($stmt->execute()) {
            $recommendation_id = $db->lastInsertId();
            error_log("Recommendation created successfully with ID: " . $recommendation_id);
            
            echo json_encode([
                'success' => true,
                'message' => 'Rekomendasi berhasil dibuat dan dikirim ke HR',
                'recommendation_id' => $recommendation_id,
                'data' => [
                    'eid' => $eid,
                    'recommendation_type' => $recommendation_type,
                    'recommended_duration' => $recommended_duration,
                    'created_by' => $recommended_by
                ]
            ]);
        } else {
            error_log("Failed to insert recommendation: " . $stmt->error);
            throw new Exception('Gagal membuat rekomendasi: ' . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Error creating recommendation: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'debug_info' => [
                'session_role' => $_SESSION['role'] ?? 'not set',
                'session_user_id' => $_SESSION['user_id'] ?? 'not set',
                'session_division_id' => $_SESSION['division_id'] ?? 'not set',
                'input_data' => $input_data ?? 'not available',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'not set',
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
            ]
        ]);
    }
}

function processRecommendation($db, $data = null) {
    try {
        // Use provided data or fallback to $_POST
        $input_data = $data ?? $_POST;
        
        // Debug logging
        error_log("=== PROCESS RECOMMENDATION DEBUG ===");
        error_log("Session data: " . print_r($_SESSION, true));
        error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
        error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        error_log("Raw input: " . file_get_contents("php://input"));
        error_log("POST data: " . print_r($_POST, true));
        error_log("Input data: " . print_r($input_data, true));
        
        // Only HR can process recommendations
        if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
            throw new Exception('Akses ditolak. Hanya HR yang dapat memproses rekomendasi.');
        }
        
        if (!isset($input_data['recommendation_id']) || !isset($input_data['status'])) {
            throw new Exception('Data tidak lengkap');
        }
        
        $recommendation_id = $input_data['recommendation_id'];
        $status = $input_data['status'];
        $hr_notes = $input_data['hr_notes'] ?? '';
        
        if (!in_array($status, ['approved', 'rejected', 'resign'])) {
            throw new Exception('Status tidak valid');
        }
        
        // Update recommendation status
        $stmt = $db->prepare("
            UPDATE recommendations 
            SET status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE recommendation_id = ?
        ");
        $stmt->bind_param('si', $status, $recommendation_id);
        
        if ($stmt->execute()) {
            // If approved, create new contract
            if ($status === 'approved') {
                $rec_stmt = $db->prepare("
                    SELECT r.*, e.name as employee_name 
                    FROM recommendations r 
                    JOIN employees e ON r.eid = e.eid 
                    WHERE r.recommendation_id = ?
                ");
                $rec_stmt->bind_param('i', $recommendation_id);
                $rec_stmt->execute();
                $rec_result = $rec_stmt->get_result();
                $recommendation = $rec_result->fetch_assoc();
                
                error_log("Processing recommendation: " . print_r($recommendation, true));
                
                if ($recommendation) {
                    $rec_type = $recommendation['recommendation_type'];
                    error_log("Processing recommendation type: {$rec_type}");
                    
                    // Handle different recommendation types
                    if (in_array($rec_type, ['kontrak1', 'kontrak2', 'kontrak3', 'extend', 'permanent'])) {
                        error_log("Creating/extending contract for recommendation type: {$rec_type}");
                        
                    // Deactivate old contract first
                    $deactivate_stmt = $db->prepare("
                        UPDATE contracts 
                        SET status = 'completed' 
                        WHERE eid = ? AND status = 'active'
                    ");
                    $deactivate_stmt->bind_param('i', $recommendation['eid']);
                        
                        if ($deactivate_stmt->execute()) {
                            error_log("Old contract deactivated for employee ID: " . $recommendation['eid']);
                        } else {
                            error_log("Failed to deactivate old contract for employee ID: " . $recommendation['eid']);
                        }
                    
                    // Create new contract
                    $contract_type = getContractTypeFromRecommendation($recommendation['recommendation_type'], $recommendation['recommended_duration']);
                    $duration = $recommendation['recommended_duration'];
                    
                        error_log("Creating new contract - Type: {$contract_type}, Duration: {$duration}");
                        
                        if ($recommendation['recommended_duration'] === 'permanent' || $recommendation['recommendation_type'] === 'permanent') {
                        // For permanent contracts, set end_date to NULL
                        // Determine contract start date
                        $contract_start_date = $recommendation['contract_start_date'] ? $recommendation['contract_start_date'] : date('Y-m-d');
                        
                        $contract_stmt = $db->prepare("
                            INSERT INTO contracts (eid, type, start_date, end_date, duration_months, status, notes) 
                                VALUES (?, 'permanent', ?, NULL, NULL, 'active', ?)
                        ");
                            $notes = "Kontrak permanent berdasarkan rekomendasi manager - ID: {$recommendation_id}";
                        $contract_stmt->bind_param('iss', $recommendation['eid'], $contract_start_date, $notes);
                    } else {
                        // For fixed-term contracts
                        // Determine contract start date
                        $contract_start_date = $recommendation['contract_start_date'] ? $recommendation['contract_start_date'] : date('Y-m-d');
                        
                        $contract_stmt = $db->prepare("
                            INSERT INTO contracts (eid, type, start_date, end_date, duration_months, status, notes) 
                            VALUES (?, ?, ?, DATE_ADD(?, INTERVAL ? MONTH), ?, 'active', ?)
                        ");
                            $notes = "Kontrak tipe {$contract_type} selama {$duration} bulan berdasarkan rekomendasi manager - ID: {$recommendation_id}";
                        $contract_stmt->bind_param('isssiss', $recommendation['eid'], $contract_type, $contract_start_date, $contract_start_date, $duration, $duration, $notes);
                        }
                        
                        if ($contract_stmt->execute()) {
                            $new_contract_id = $db->getConnection()->insert_id;
                            error_log("New contract created successfully - ID: {$new_contract_id}, Employee ID: {$recommendation['eid']}, Type: {$contract_type}");
                            
                            // Create contract history entry
                            $history_stmt = $db->prepare("
                                INSERT INTO contract_history (contract_id, action, reason, created_by) 
                                VALUES (?, 'created', ?, ?)
                            ");
                            $history_reason = "Kontrak baru dibuat berdasarkan persetujuan rekomendasi ID: {$recommendation_id}";
                            $created_by = $_SESSION['user_id'];
                            $history_stmt->bind_param('isi', $new_contract_id, $history_reason, $created_by);
                            $history_stmt->execute();
                            
                        } else {
                            error_log("Failed to create new contract for employee ID: " . $recommendation['eid'] . " - Error: " . $db->getConnection()->error);
                            throw new Exception('Gagal membuat kontrak baru: ' . $db->getConnection()->error);
                        }
                    } elseif ($rec_type === 'terminate') {
                        error_log("Processing termination for employee ID: " . $recommendation['eid']);
                        
                        // For termination, update employee status to resigned and deactivate contract
                        $terminate_employee_stmt = $db->prepare("
                            UPDATE employees 
                            SET status = 'resigned', resign_date = CURDATE(), resign_reason = ?
                            WHERE eid = ?
                        ");
                        $resign_reason = "Terminasi berdasarkan rekomendasi manager - ID: {$recommendation_id}";
                        $terminate_employee_stmt->bind_param('si', $resign_reason, $recommendation['eid']);
                        
                        if ($terminate_employee_stmt->execute()) {
                            error_log("Employee terminated successfully - ID: " . $recommendation['eid']);
                            
                            // Deactivate contract
                            $deactivate_stmt = $db->prepare("
                                UPDATE contracts 
                                SET status = 'terminated', updated_at = CURRENT_TIMESTAMP
                                WHERE eid = ? AND status = 'active'
                            ");
                            $deactivate_stmt->bind_param('i', $recommendation['eid']);
                            $deactivate_stmt->execute();
                            
                        } else {
                            error_log("Failed to terminate employee ID: " . $recommendation['eid'] . " - Error: " . $db->getConnection()->error);
                            throw new Exception('Gagal melakukan terminasi karyawan: ' . $db->getConnection()->error);
                        }
                    } elseif ($rec_type === 'review') {
                        error_log("Processing review for employee ID: " . $recommendation['eid']);
                        // For review, just log that it was approved - no contract changes needed
                        error_log("Review recommendation approved for employee ID: " . $recommendation['eid']);
                    } else {
                        error_log("Unknown recommendation type: {$rec_type}");
                    }
                } else {
                    error_log("No recommendation data found for ID: {$recommendation_id}");
                }
            } elseif ($status === 'resign') {
                // Handle resign status - resign employee directly
                $rec_stmt = $db->prepare("
                    SELECT r.*, e.name as employee_name 
                    FROM recommendations r 
                    JOIN employees e ON r.eid = e.eid 
                    WHERE r.recommendation_id = ?
                ");
                $rec_stmt->bind_param('i', $recommendation_id);
                $rec_stmt->execute();
                $rec_result = $rec_stmt->get_result();
                $recommendation = $rec_result->fetch_assoc();
                
                if ($recommendation) {
                    $resign_date = $input_data['resign_date'] ?? date('Y-m-d');
                    $resign_reason = "Resign berdasarkan keputusan HR - Rekomendasi ID: {$recommendation_id}";
                    if (!empty($hr_notes)) {
                        $resign_reason .= " | Catatan HR: {$hr_notes}";
                    }
                    
                    error_log("Processing resign for employee ID: {$recommendation['eid']} with date: {$resign_date}");
                    
                    // Update employee status to resigned
                    $resign_stmt = $db->prepare("
                        UPDATE employees 
                        SET status = 'resigned', resign_date = ?, resign_reason = ?
                        WHERE eid = ?
                    ");
                    $resign_stmt->bind_param('ssi', $resign_date, $resign_reason, $recommendation['eid']);
                    
                    if ($resign_stmt->execute()) {
                        error_log("Employee resigned successfully - ID: {$recommendation['eid']}");
                        
                        // Deactivate active contract
                        $deactivate_stmt = $db->prepare("
                            UPDATE contracts 
                            SET status = 'terminated', updated_at = CURRENT_TIMESTAMP
                            WHERE eid = ? AND status = 'active'
                        ");
                        $deactivate_stmt->bind_param('i', $recommendation['eid']);
                        $deactivate_stmt->execute();
                        
                        error_log("Contract terminated for resigned employee - ID: {$recommendation['eid']}");
                        
                    } else {
                        error_log("Failed to resign employee ID: {$recommendation['eid']} - Error: " . $db->getConnection()->error);
                        throw new Exception('Gagal melakukan resign karyawan: ' . $db->getConnection()->error);
                    }
                } else {
                    error_log("No recommendation data found for resign - ID: {$recommendation_id}");
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Rekomendasi berhasil diproses'
            ]);
        } else {
            throw new Exception('Gagal memproses rekomendasi');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getContractType($duration) {
    if ($duration <= 6) return '1';
    if ($duration <= 12) return '2';
    if ($duration <= 24) return '3';
    return 'permanent';
}

function getContractTypeFromRecommendation($recommendation_type, $duration) {
    // Map new recommendation types to contract types (sesuai database schema)
    switch ($recommendation_type) {
        case 'kontrak1':
            return '1';
        case 'kontrak2':
            return '2';
        case 'kontrak3':
            return '3';
        case 'extend':
            // Legacy support - use duration-based mapping
            return getContractType($duration);
        case 'permanent':
            return 'permanent';
        default:
            return '1';
    }
}

function markContractExtended($db, $data = null) {
    try {
        // Use provided data or fallback to $_POST
        $input_data = $data ?? $_POST;
        
        // Only HR can mark contracts as extended
        if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
            throw new Exception('Akses ditolak. Hanya HR yang dapat menandai kontrak sudah diperpanjang.');
        }
        
        if (!isset($input_data['recommendation_id'])) {
            throw new Exception('Recommendation ID tidak ditemukan');
        }
        
        $recommendation_id = $input_data['recommendation_id'];
        
        // Update recommendation to mark as extended
        $stmt = $db->prepare("
            UPDATE recommendations 
            SET status = 'extended', updated_at = CURRENT_TIMESTAMP 
            WHERE recommendation_id = ? AND status = 'approved'
        ");
        $stmt->bind_param('i', $recommendation_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Kontrak berhasil ditandai sebagai sudah diperpanjang'
                ]);
            } else {
                throw new Exception('Rekomendasi tidak ditemukan atau belum disetujui');
            }
        } else {
            throw new Exception('Gagal menandai kontrak sebagai sudah diperpanjang');
        }
        
    } catch (Exception $e) {
        error_log("Error marking contract as extended: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?> 