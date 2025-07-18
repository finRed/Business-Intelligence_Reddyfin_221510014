<?php
// Suppress output that corrupts JSON but keep error logging
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session only if not already started, and suppress any warnings
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../python_analysis_integration.php';

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

switch ($method) {
    case 'GET':
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
            if ($action === 'dashboard') {
                getDashboardData($db);
            } elseif ($action === 'turnover_analysis') {
                getTurnoverAnalysis($db);
            } elseif ($action === 'retention_analysis') {
                getRetentionAnalysis($db);
            } elseif ($action === 'manager_analytics') {
                getManagerAnalytics($db);
            } elseif ($action === 'education_job_analysis') {
                getEducationJobAnalysis($db);
            } elseif ($action === 'division_intelligence') {
                getDivisionIntelligence($db);
            } elseif ($action === 'recommendation' && isset($_GET['eid'])) {
                getEmployeeRecommendation($db, $_GET['eid']);
            } elseif ($action === 'detail' && isset($_GET['eid'])) {
                getEmployeeDetail($db, $_GET['eid']);
            } elseif ($action === 'recommendations') {
                getRecommendations($db);
            } elseif ($action === 'employee_data_mining_detail' && isset($_GET['eid'])) {
                getEmployeeDataMiningDetail($db, $_GET['eid']);
            } else {
                getEmployees($db);
            }
        } else {
            getEmployees($db);
        }
        break;
        
    case 'POST':
        
        // Handle both JSON and FormData
        if (isset($_POST['action'])) {
            // FormData from frontend
            $action = $_POST['action'];
            if ($action === 'create') {
                addEmployeeFromForm($db, $_POST);
            } elseif ($action === 'add') {
                $input = json_decode(file_get_contents('php://input'), true);
                addEmployee($db, $input);
            } elseif ($action === 'recommend') {
                $input = json_decode(file_get_contents('php://input'), true);
                addRecommendation($db, $input);
            }
        } else {
            // JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['action'])) {
                if ($input['action'] === 'add') {
                    addEmployee($db, $input);
                } elseif ($input['action'] === 'recommend') {
                    addRecommendation($db, $input);
                }
            }
        }
        break;
        
    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        updateEmployee($db, $input);
        break;
        
    case 'DELETE':
        $input = json_decode(file_get_contents('php://input'), true);
        deleteEmployee($db, $input['eid']);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function getEmployees($db) {
    try {
        $role = $_SESSION['role'];
        $division_id = $_SESSION['division_id'];
        
        if ($role === 'admin' || $role === 'hr') {
            // Admin and HR can see all employees
            $sql = "SELECT e.*, d.division_name, 
                           CASE 
                               WHEN e.birth_date IS NOT NULL AND e.birth_date != '0000-00-00' 
                               THEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE())
                               ELSE NULL 
                           END as age,
                           c.type as current_contract_type, 
                           c.start_date as contract_start, 
                           c.end_date as contract_end,
                           c.status as contract_status,
                           CASE 
                               WHEN e.status = 'active' AND c.type = 'probation' AND c.status = 'active' THEN 'probation'
                               WHEN e.status = 'active' AND (c.type != 'probation' OR c.type IS NULL) THEN 'active'
                               ELSE e.status
                           END as display_status
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
                    ORDER BY e.created_at DESC";
            $result = $db->query($sql);
        } else {
            // Manager can only see employees in their division
            $sql = "SELECT e.*, d.division_name, 
                           CASE 
                               WHEN e.birth_date IS NOT NULL AND e.birth_date != '0000-00-00' 
                               THEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE())
                               ELSE NULL 
                           END as age,
                           c.type as current_contract_type, 
                           c.start_date as contract_start, 
                           c.end_date as contract_end,
                           c.status as contract_status,
                           CASE 
                               WHEN e.status = 'active' AND c.type = 'probation' AND c.status = 'active' THEN 'probation'
                               WHEN e.status = 'active' AND (c.type != 'probation' OR c.type IS NULL) THEN 'active'
                               ELSE e.status
                           END as display_status
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
                    WHERE e.division_id = ?
                    ORDER BY e.created_at DESC";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('i', $division_id);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            // Calculate education-job match for each employee
            $education_match = calculateAdvancedEducationJobMatch($row);
            $row['education_job_match'] = $education_match['match_status'];
            
            $employees[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $employees
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getEmployeeDetail($db, $eid) {
    try {
        // Get employee details
        $stmt = $db->prepare("SELECT e.*, d.division_name,
                             CASE 
                                 WHEN e.birth_date IS NOT NULL AND e.birth_date != '0000-00-00' 
                                 THEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE())
                                 ELSE NULL 
                             END as age
                             FROM employees e 
                             LEFT JOIN divisions d ON e.division_id = d.division_id 
                             WHERE e.eid = ?");
        $stmt->bind_param('i', $eid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Karyawan tidak ditemukan');
        }
        
        $employee = $result->fetch_assoc();
        
        // Calculate education-job match for this employee
        $education_match = calculateAdvancedEducationJobMatch($employee);
        $employee['education_job_match'] = $education_match['match_status'];
        
        // Get contract history
        $contract_stmt = $db->prepare("SELECT * FROM contracts WHERE eid = ? ORDER BY start_date DESC");
        $contract_stmt->bind_param('i', $eid);
        $contract_stmt->execute();
        $contract_result = $contract_stmt->get_result();
        
        $contracts = [];
        while ($row = $contract_result->fetch_assoc()) {
            $contracts[] = $row;
        }
        
        // Get recommendations
        $rec_stmt = $db->prepare("SELECT r.*, u.username as recommended_by_name 
                                 FROM recommendations r 
                                 JOIN users u ON r.recommended_by = u.user_id 
                                 WHERE r.eid = ? 
                                 ORDER BY r.created_at DESC");
        $rec_stmt->bind_param('i', $eid);
        $rec_stmt->execute();
        $rec_result = $rec_stmt->get_result();
        
        $recommendations = [];
        while ($row = $rec_result->fetch_assoc()) {
            $recommendations[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'employee' => $employee,
            'contracts' => $contracts,
            'recommendations' => $recommendations
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getDashboardData($db) {
    try {
        $role = $_SESSION['role'];
        $division_id = $_SESSION['division_id'] ?? null;
        
        // Total employees
        if ($role === 'manager' && $division_id) {
            $total_stmt = $db->prepare("SELECT COUNT(*) as total FROM employees WHERE division_id = ?");
            $total_stmt->bind_param('i', $division_id);
            $total_stmt->execute();
            $total_result = $total_stmt->get_result();
        } else {
            $total_result = $db->query("SELECT COUNT(*) as total FROM employees");
        }
        $total_employees = $total_result->fetch_assoc()['total'];
        
        // Active employees
        if ($role === 'manager' && $division_id) {
            $active_stmt = $db->prepare("SELECT COUNT(*) as active FROM employees WHERE status = 'active' AND division_id = ?");
            $active_stmt->bind_param('i', $division_id);
            $active_stmt->execute();
            $active_result = $active_stmt->get_result();
        } else {
            $active_result = $db->query("SELECT COUNT(*) as active FROM employees WHERE status = 'active'");
        }
        $active_employees = $active_result->fetch_assoc()['active'];
        
        // Resigned employees
        if ($role === 'manager' && $division_id) {
            $resigned_stmt = $db->prepare("SELECT COUNT(*) as resigned FROM employees WHERE status = 'resigned' AND division_id = ?");
            $resigned_stmt->bind_param('i', $division_id);
            $resigned_stmt->execute();
            $resigned_result = $resigned_stmt->get_result();
        } else {
            $resigned_result = $db->query("SELECT COUNT(*) as resigned FROM employees WHERE status = 'resigned'");
        }
        $resigned_employees = $resigned_result->fetch_assoc()['resigned'];
        
        // Contract distribution
        if ($role === 'manager' && $division_id) {
            $contract_stmt = $db->prepare("SELECT c.type, COUNT(*) as count 
                           FROM contracts c 
                           JOIN employees e ON c.eid = e.eid 
                           WHERE c.status = 'active' AND e.division_id = ?
                           GROUP BY c.type");
            $contract_stmt->bind_param('i', $division_id);
            $contract_stmt->execute();
            $contract_result = $contract_stmt->get_result();
        } else {
            $contract_result = $db->query("SELECT type, COUNT(*) as count FROM contracts WHERE status = 'active' GROUP BY type");
        }
        $contract_distribution = [];
        if ($contract_result) {
        while ($row = $contract_result->fetch_assoc()) {
            $contract_distribution[] = $row;
            }
        }
        
        // Education distribution
        if ($role === 'manager' && $division_id) {
            $education_stmt = $db->prepare("SELECT education_level, COUNT(*) as count FROM employees WHERE division_id = ? GROUP BY education_level");
            $education_stmt->bind_param('i', $division_id);
            $education_stmt->execute();
            $education_result = $education_stmt->get_result();
        } else {
            $education_result = $db->query("SELECT education_level, COUNT(*) as count FROM employees GROUP BY education_level");
        }
        $education_distribution = [];
        if ($education_result) {
        while ($row = $education_result->fetch_assoc()) {
            $education_distribution[] = $row;
            }
        }
        
        // Pending recommendations
        if ($role === 'manager' && $division_id) {
            $pending_stmt = $db->prepare("SELECT COUNT(*) as pending 
                                         FROM recommendations r 
                                         JOIN employees e ON r.eid = e.eid 
                                         WHERE r.status = 'pending' AND e.division_id = ?");
            $pending_stmt->bind_param('i', $division_id);
            $pending_stmt->execute();
            $pending_result = $pending_stmt->get_result();
        } else {
            $pending_result = $db->query("SELECT COUNT(*) as pending FROM recommendations WHERE status = 'pending'");
        }
        $pending_recommendations = $pending_result->fetch_assoc()['pending'];

        // Division distribution (only for admin/HR)
        $division_distribution = [];
        if ($role === 'admin' || $role === 'hr') {
            $division_result = $db->query("SELECT d.division_name, COUNT(e.eid) as count 
                           FROM divisions d 
                           LEFT JOIN employees e ON d.division_id = e.division_id 
                           GROUP BY d.division_id, d.division_name");
            if ($division_result) {
            while ($row = $division_result->fetch_assoc()) {
                $division_distribution[] = $row;
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_employees' => $total_employees,
                'active_employees' => $active_employees,
                'resigned_employees' => $resigned_employees,
                'pending_recommendations' => $pending_recommendations,
                'contract_distribution' => $contract_distribution,
                'education_distribution' => $education_distribution,
                'division_distribution' => $division_distribution
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Dashboard error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Gagal memuat data dashboard: ' . $e->getMessage()
        ]);
    }
}

function getTurnoverAnalysis($db) {
    try {
        $role = $_SESSION['role'];
        $division_id = $_SESSION['division_id'] ?? null;
        $period = $_GET['period'] ?? 'all';
        
        // Determine date condition based on period
        $date_condition = '';
        $date_params = [];
        
        switch ($period) {
            case '1month':
                $date_condition = "AND resign_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                break;
            case '3months':
                $date_condition = "AND resign_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                break;
            case '6months':
                $date_condition = "AND resign_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                break;
            case '1year':
                $date_condition = "AND resign_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
            case 'all':
            default:
                $date_condition = '';
                break;
        }
        
        // Get total employees for the period
        if ($role === 'manager' && $division_id) {
            $total_sql = "SELECT COUNT(*) as total FROM employees WHERE division_id = ?";
            $total_stmt = $db->prepare($total_sql);
            $total_stmt->bind_param('i', $division_id);
            $total_stmt->execute();
            $total_result = $total_stmt->get_result();
        } else {
            $total_sql = "SELECT COUNT(*) as total FROM employees";
            $total_result = $db->query($total_sql);
        }
        $total_employees = $total_result->fetch_assoc()['total'];
        
        // Get resigned employees for the period
        if ($role === 'manager' && $division_id) {
            $resigned_sql = "SELECT COUNT(*) as resigned FROM employees WHERE status = 'resigned' AND division_id = ? $date_condition";
            $resigned_stmt = $db->prepare($resigned_sql);
            $resigned_stmt->bind_param('i', $division_id);
            $resigned_stmt->execute();
            $resigned_result = $resigned_stmt->get_result();
        } else {
            $resigned_sql = "SELECT COUNT(*) as resigned FROM employees WHERE status = 'resigned' $date_condition";
            $resigned_result = $db->query($resigned_sql);
        }
        $resigned_employees = $resigned_result->fetch_assoc()['resigned'];
        
        // Get active employees
        if ($role === 'manager' && $division_id) {
            $active_sql = "SELECT COUNT(*) as active FROM employees WHERE status = 'active' AND division_id = ?";
            $active_stmt = $db->prepare($active_sql);
            $active_stmt->bind_param('i', $division_id);
            $active_stmt->execute();
            $active_result = $active_stmt->get_result();
        } else {
            $active_sql = "SELECT COUNT(*) as active FROM employees WHERE status = 'active'";
            $active_result = $db->query($active_sql);
        }
        $active_employees = $active_result->fetch_assoc()['active'];
        
        // Get turnover details by division (for admin/HR)
        $division_turnover = [];
        if ($role === 'admin' || $role === 'hr') {
            $division_sql = "SELECT d.division_name, 
                                   COUNT(e.eid) as total_employees,
                                   SUM(CASE WHEN e.status = 'resigned' $date_condition THEN 1 ELSE 0 END) as resigned_employees,
                                   SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_employees
                            FROM divisions d 
                            LEFT JOIN employees e ON d.division_id = e.division_id 
                            GROUP BY d.division_id, d.division_name";
            $division_result = $db->query($division_sql);
            if ($division_result) {
                while ($row = $division_result->fetch_assoc()) {
                    $total = intval($row['total_employees']);
                    $resigned = intval($row['resigned_employees']);
                    $row['turnover_rate'] = $total > 0 ? round(($resigned / $total) * 100, 2) : 0;
                    $division_turnover[] = $row;
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_employees' => $total_employees,
                'resigned_employees' => $resigned_employees,
                'active_employees' => $active_employees,
                'period' => $period,
                'division_turnover' => $division_turnover
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Turnover analysis error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Gagal memuat data turnover: ' . $e->getMessage()
        ]);
    }
}

function getRetentionAnalysis($db) {
    try {
        $role = $_SESSION['role'];
        $division_id = $_SESSION['division_id'] ?? null;
        $period = $_GET['period'] ?? 'all';
        
        // Same logic as turnover but focused on retention
        $date_condition = '';
        
        switch ($period) {
            case '1month':
                $date_condition = "AND join_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                break;
            case '3months':
                $date_condition = "AND join_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                break;
            case '6months':
                $date_condition = "AND join_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                break;
            case '1year':
                $date_condition = "AND join_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
            case 'all':
            default:
                $date_condition = '';
                break;
        }
        
        // Get total employees for the period
        if ($role === 'manager' && $division_id) {
            $total_sql = "SELECT COUNT(*) as total FROM employees WHERE division_id = ? $date_condition";
            $total_stmt = $db->prepare($total_sql);
            $total_stmt->bind_param('i', $division_id);
            $total_stmt->execute();
            $total_result = $total_stmt->get_result();
        } else {
            $total_sql = "SELECT COUNT(*) as total FROM employees WHERE 1=1 $date_condition";
            $total_result = $db->query($total_sql);
        }
        $total_employees = $total_result->fetch_assoc()['total'];
        
        // Get active employees for the period
        if ($role === 'manager' && $division_id) {
            $active_sql = "SELECT COUNT(*) as active FROM employees WHERE status = 'active' AND division_id = ? $date_condition";
            $active_stmt = $db->prepare($active_sql);
            $active_stmt->bind_param('i', $division_id);
            $active_stmt->execute();
            $active_result = $active_stmt->get_result();
        } else {
            $active_sql = "SELECT COUNT(*) as active FROM employees WHERE status = 'active' $date_condition";
            $active_result = $db->query($active_sql);
        }
        $active_employees = $active_result->fetch_assoc()['active'];
        
        // Get resigned employees for the period
        if ($role === 'manager' && $division_id) {
            $resigned_sql = "SELECT COUNT(*) as resigned FROM employees WHERE status = 'resigned' AND division_id = ? $date_condition";
            $resigned_stmt = $db->prepare($resigned_sql);
            $resigned_stmt->bind_param('i', $division_id);
            $resigned_stmt->execute();
            $resigned_result = $resigned_stmt->get_result();
        } else {
            $resigned_sql = "SELECT COUNT(*) as resigned FROM employees WHERE status = 'resigned' $date_condition";
            $resigned_result = $db->query($resigned_sql);
        }
        $resigned_employees = $resigned_result->fetch_assoc()['resigned'];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_employees' => $total_employees,
                'active_employees' => $active_employees,
                'resigned_employees' => $resigned_employees,
                'period' => $period
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Retention analysis error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Gagal memuat data retensi: ' . $e->getMessage()
        ]);
    }
}

function getRecommendations($db) {
    try {
        $role = $_SESSION['role'];
        $division_id = $_SESSION['division_id'];
        
        if ($role === 'manager') {
            // Manager hanya bisa lihat rekomendasi untuk divisinya
            $sql = "SELECT r.*, e.name as employee_name, e.eid, e.role as employee_role, 
                           d.division_name, u.username as recommended_by_name
                    FROM recommendations r 
                    JOIN employees e ON r.eid = e.eid
                    LEFT JOIN divisions d ON e.division_id = d.division_id
                    LEFT JOIN users u ON r.recommended_by = u.user_id
                    WHERE e.division_id = ? AND r.status = 'pending'
                    ORDER BY r.created_at DESC";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('i', $division_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            // HR dan Admin bisa lihat semua rekomendasi
            $sql = "SELECT r.*, e.name as employee_name, e.eid, e.role as employee_role, 
                           d.division_name, u.username as recommended_by_name
                    FROM recommendations r 
                    JOIN employees e ON r.eid = e.eid
                    LEFT JOIN divisions d ON e.division_id = d.division_id
                    LEFT JOIN users u ON r.recommended_by = u.user_id
                    WHERE r.status = 'pending'
                    ORDER BY r.created_at DESC";
            $result = $db->query($sql);
        }
        
        $recommendations = [];
        while ($row = $result->fetch_assoc()) {
            $recommendations[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $recommendations
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getEmployeeRecommendation($db, $eid) {
    try {
        // Get employee data
        $stmt = $db->prepare("SELECT * FROM employees WHERE eid = ?");
        $stmt->bind_param('i', $eid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Karyawan tidak ditemukan');
        }
        
        $employee = $result->fetch_assoc();
        
        // Get contract history
        $contract_stmt = $db->prepare("SELECT * FROM contracts WHERE eid = ? ORDER BY start_date DESC");
        $contract_stmt->bind_param('i', $eid);
        $contract_stmt->execute();
        $contract_result = $contract_stmt->get_result();
        
        $contracts = [];
        while ($row = $contract_result->fetch_assoc()) {
            $contracts[] = $row;
        }
        
        // Generate recommendation based on business logic
        $recommendation = generateRecommendation($db, $employee, $contracts);
        
        echo json_encode([
            'success' => true,
            'recommendation' => $recommendation
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function generateRecommendation($db, $employee, $contracts) {
    // Advanced recommendation system based on data intelligence analysis
    $analytics = performEmployeeAnalytics($db, $employee, $contracts);
    $recommendations = generateContractRecommendations($employee, $analytics);
    $recommendation = $recommendations[0]; // Ambil rekomendasi utama
    $recommendation['detailed_analysis'] = $analytics;
    $recommendation['insights'] = generateInsights($analytics);
    
    return $recommendation;
}

function performEmployeeAnalytics($db, $employee, $contracts) {
    // Comprehensive data analysis
    $educationRoleMatch = analyzeEducationRoleMatch($employee);
    $contractHistory = analyzeContractHistory($contracts);
    $resignationRisk = calculateResignationRisk($db, $employee);
    $successProbability = calculateSuccessProbability($db, $employee, $contracts);
    $industryBenchmark = getIndustryBenchmark($db, $employee['role'], $employee['education_level']);
    
    return [
        'education_role_match' => $educationRoleMatch,
        'contract_history' => $contractHistory,
        'resignation_risk' => $resignationRisk,
        'success_probability' => $successProbability,
        'industry_benchmark' => $industryBenchmark,
        'tenure_analysis' => analyzeTenure($employee)
    ];
}

function analyzeEducationRoleMatch($employee) {
    $major = strtoupper($employee['major'] ?? '');
    $role = strtoupper($employee['role'] ?? '');
    $designation = strtoupper($employee['designation'] ?? '');
    
    // DATA INTELLIGENCE: Comprehensive IT Keywords - FIXED TO MATCH DYNAMIC INTELLIGENCE
    $it_education_keywords = [
        'INFORMATION TECHNOLOGY', 'TEKNIK INFORMATIKA', 'ILMU KOMPUTER', 'SISTEM INFORMASI',
        'COMPUTER SCIENCE', 'SOFTWARE ENGINEERING', 'INFORMATIKA', 'TEKNIK KOMPUTER',
        'KOMPUTER', 'TEKNOLOGI INFORMASI', 'INFORMATION SYSTEM', 'INFORMATICS ENGINEERING',
        'INFORMATIC ENGINEERING', 'COMPUTER ENGINEERING', 'INFORMATION MANAGEMENT',
        'INFORMATICS MANAGEMENT', 'MANAGEMENT INFORMATIKA', 'SISTEM KOMPUTER',
        'KOMPUTERISASI AKUNTANASI', 'COMPUTERIZED ACCOUNTING', 'COMPUTATIONAL SCIENCE',
        'INFORMATICS', 'INFORMATICS TECHNOLOGY', 'COMPUTER AND INFORMATICS ENGINEERING',
        'ENGINEERING INFORMATIC', 'INDUSTRIAL ENGINEERING_INFORMATIC',
        // CRITICAL: Added missing IT Education Majors from CSV Analysis
        'STATISTICS', 'TECHNOLOGY MANAGEMENT', 'ELECTRICAL ENGINEERING', 'ELECTRONICS ENGINEERING',
        'MASTER IN INFORMATICS', 'ICT', 'COMPUTER SCIENCE & ELECTRONICS', 'COMPUTER TELECOMMUNICATION',
        'TELECOMMUNICATION ENGINEERING', 'TELECOMMUNICATIONS ENGINEERING', 'TELECOMMUNICATION & MEDIA',
        'TEKNIK ELEKTRO', 'COMPUTER SCINCE', 'COMPUTER SCIENCES & ENGINEERING', 'COMPUTER SYSTEM',
        'COMPUTER SIENCE', 'COMPUTER TECHNOLOGY', 'COMPUTER SCINCE', 'INFORMASTION SYSTEM',
        'INFORMATICS TECHNIQUE', 'INFORMATIOCS', 'INFORMATICS TECHNOLOGY', 'TEKNIK KOMPUTER & JARINGAN',
        'INFORMATION ENGINEERING', 'SOFTWARE ENGINEERING TECHNOLOGY', 'DIGITAL MEDIA TECHNOLOGY',
        'INFORMATIC ENGINEETING', 'INFORMATION SYTEM', 'INFORMASTION SYSTEM', 'MATERIALS ENGINEERING',
        'NETWORK MANAGEMENT', 'INFORMATICS SYSTEM', 'BUSINESS INFORMATION SYSTEM',
        'PHYSICS ENGINEERING', 'ELECTRICAL ENGINEERING_ELECTRONICS', 'ELECTRONIC ENGINEERING',
        'ELECTRONICS & COMPUTER ENGINEERING', 'TELECOMMUNICATION ENGINEERING_ELECTRONICS'
    ];
    
    $it_job_keywords = [
        'DEVELOPER', 'PROGRAMMER', 'SOFTWARE', 'TECHNICAL', 'FRONTEND', 'BACKEND', 
        'FULLSTACK', 'FULL STACK', 'JAVA', 'NET', '.NET', 'API', 'ETL', 'MOBILE',
        'ANDROID', 'WEB', 'UI/UX', 'FRONT END', 'BACK END', 'PEGA', 'RPA',
        'ANALYST', 'BUSINESS ANALYST', 'SYSTEM ANALYST', 'DATA ANALYST', 'DATA SCIENTIST',
        'QUALITY ASSURANCE', 'QA', 'TESTER', 'TEST ENGINEER', 'CONSULTANT',
        'IT CONSULTANT', 'TECHNOLOGY CONSULTANT', 'TECHNICAL CONSULTANT',
        'PRODUCT OWNER', 'SCRUM MASTER', 'PMO', 'IT OPERATION', 'DEVOPS',
        'SYSTEM ADMINISTRATOR', 'DATABASE ADMINISTRATOR', 'NETWORK',
        // Enhanced IT Job Roles from CSV Analysis
        'BI DEVELOPER', 'BI COGNOS DEVELOPER', 'POWER BI DEVELOPER', 'ETL DEVELOPER',
        'DATA ENGINEER', 'DATABASE ADMINISTRATOR', 'DBA', 'DEVOPS ENGINEER', 'CLOUD ENGINEER',
        'INFRASTRUCTURE ENGINEER', 'SECURITY ENGINEER', 'CYBERSECURITY', 'NETWORK ENGINEER',
        'NETWORK ADMINISTRATOR', 'IT SUPPORT', 'TECHNICAL SUPPORT', 'HELP DESK',
        'SYSTEM ADMINISTRATOR', 'SOFTWARE ENGINEER', 'SOFTWARE ARCHITECT', 'SOLUTIONS ARCHITECT',
        'MOBILE DEVELOPER', 'IOS DEVELOPER', 'ANDROID DEVELOPER', 'REACT DEVELOPER',
        'ANGULAR DEVELOPER', 'VUE DEVELOPER', 'NODE.JS DEVELOPER', 'PYTHON DEVELOPER',
        'JAVA DEVELOPER', 'C# DEVELOPER', 'PHP DEVELOPER', 'GOLANG DEVELOPER',
        'PROJECT MANAGER', 'IT PROJECT MANAGER', 'TECHNICAL PROJECT MANAGER',
        'DELIVERY MANAGER', 'PRODUCT MANAGER', 'PROGRAM MANAGER', 'SCRUM MASTER',
        'AGILE COACH', 'TEAM LEAD', 'TECH LEAD', 'ENGINEERING MANAGER', 'IT MANAGER',
        'DEVELOPMENT MANAGER', 'BUSINESS INTELLIGENCE ANALYST', 'BI ANALYST', 'REPORT DESIGNER',
        'REPORTING ANALYST', 'PROCESS ANALYST', 'FUNCTIONAL ANALYST', 'TECHNICAL ANALYST',
        'PERFORMANCE ANALYST', 'IT QUALITY ASSURANCE', 'TESTING ENGINEER', 'TESTER SUPPORT',
        'JR TESTER', 'IT TESTER', 'TESTING SPECIALIST', 'TESTING COORDINATOR', 'QUALITY TESTER',
        'JR QUALITY ASSURANCE', 'QUALITY ASSURANCE TESTER', 'TESTING ENGINEER SPECIALIST',
        'APPRENTICE TESTER', 'JR TESTER LEVEL 1', 'LEAD QA', 'LEAD BACKEND DEVELOPER'
    ];
    
    // Check if education is IT-related
    $is_it_education = false;
    foreach ($it_education_keywords as $keyword) {
        if (strpos($major, $keyword) !== false) {
            $is_it_education = true;
            break;
        }
    }
    
    // Check if job is IT-related
    $is_it_job = false;
    foreach ($it_job_keywords as $keyword) {
        if (strpos($role, $keyword) !== false || strpos($designation, $keyword) !== false) {
            $is_it_job = true;
            break;
        }
    }
    
    // UNIFIED SCORING LOGIC - match manager_analytics_simple.php exactly
    $score = 0;
    if ($is_it_education && $is_it_job) {
        $score = 3; // Perfect match
        $match_status = 'Match';
        $level = 'High';
        $category = 'Perfect Match';
    } elseif ($is_it_education || $is_it_job) {
        $score = 2; // Good match
        $match_status = 'Match';
        $level = 'Medium';
        $category = 'Good Match';
    } else {
        $score = 1; // Basic match
        $match_status = 'Unmatch';
        $level = 'Low';
        $category = 'No Match';
    }
    
    return [
        'score' => $score,
        'match_status' => $match_status,
        'level' => $level,
        'category' => $category,
        'details' => [
            'education_match' => $is_it_education,
            'role_match' => $is_it_job,
            'education_field' => $major,
            'role_field' => $role
        ]
    ];
}

function analyzeContractHistory($contracts) {
    $history = [
        'total_contracts' => count($contracts),
        'completed_contracts' => 0,
        'active_contracts' => 0,
        'progression_path' => [],
        'average_duration' => 0,
        'success_rate' => 0
    ];
    
    $totalDuration = 0;
    foreach ($contracts as $contract) {
        if ($contract['status'] === 'completed') {
            $history['completed_contracts']++;
            $totalDuration += $contract['duration_months'] ?? 0;
        } elseif ($contract['status'] === 'active') {
            $history['active_contracts']++;
        }
        $history['progression_path'][] = $contract['type'];
    }
    
    if ($history['completed_contracts'] > 0) {
        $history['average_duration'] = $totalDuration / $history['completed_contracts'];
        $history['success_rate'] = ($history['completed_contracts'] / $history['total_contracts']) * 100;
    }
    
    return $history;
}

function calculateResignationRisk($db, $employee) {
    // Based on Python analysis: 41.3% overall resignation, 23.6% at permanent stage
    $riskFactors = [];
    $riskScore = 0.0;
    
    // Get historical resignation data for similar profiles
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'resigned' THEN 1 END) as resigned
        FROM employees 
        WHERE role = ? AND education_level = ? AND status IN ('active', 'resigned')
    ");
    $stmt->bind_param('ss', $employee['role'], $employee['education_level']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $resignationRate = $result['total'] > 0 ? ($result['resigned'] / $result['total']) : 0.413; // Default to overall rate
    $riskScore += $resignationRate * 0.4;
    
    // Education-role mismatch factor
    $match = analyzeEducationRoleMatch($employee);
    if ($match['score'] < 0.5) {
        $riskScore += 0.3;
        $riskFactors[] = 'Ketidaksesuaian pendidikan dengan role';
    }
    
    // Experience factor (join date)
    $joinYears = (time() - strtotime($employee['join_date'])) / (365 * 24 * 3600);
    if ($joinYears < 1) {
        $riskScore += 0.2;
        $riskFactors[] = 'Karyawan baru (< 1 tahun)';
    }
    
    // Age factor
    if (isset($employee['birthdate'])) {
        $age = (time() - strtotime($employee['birthdate'])) / (365 * 24 * 3600);
        if ($age < 25 || $age > 45) {
            $riskScore += 0.1;
            $riskFactors[] = 'Faktor usia (< 25 atau > 45 tahun)';
        }
    }
    
    return [
        'score' => min($riskScore, 1.0),
        'level' => getRiskLevel($riskScore),
        'factors' => $riskFactors,
        'benchmark_resignation_rate' => round($resignationRate * 100, 1)
    ];
}

function calculateSuccessProbability($db, $employee, $contracts) {
    // Calculate success probability based on various factors
    $baseScore = 0.6; // Base 60% success rate
    
    // Education factor
    if ($employee['education_level'] === 'S1') {
        $baseScore += 0.15;
    } elseif ($employee['education_level'] === 'S2') {
        $baseScore += 0.2;
    }
    
    // Experience factor
    $joinYears = (time() - strtotime($employee['join_date'])) / (365 * 24 * 3600);
    if ($joinYears > 2) {
        $baseScore += 0.1;
    }
    
    // Contract history factor
    $completedContracts = count(array_filter($contracts, function($c) { return $c['status'] === 'completed'; }));
    $baseScore += min($completedContracts * 0.05, 0.15);
    
    // Education-role match factor
    $match = analyzeEducationRoleMatch($employee);
    $baseScore += ($match['score'] - 0.5) * 0.2;
    
    return [
        'score' => min(max($baseScore, 0.1), 0.95),
        'percentage' => round(min(max($baseScore, 0.1), 0.95) * 100, 1)
    ];
}

function getIndustryBenchmark($db, $role, $educationLevel) {
    // Get industry statistics for the role
    $stmt = $db->prepare("
        SELECT 
            AVG(CASE WHEN c.status = 'completed' THEN c.duration_months END) as avg_duration,
            COUNT(CASE WHEN c.status = 'completed' THEN 1 END) * 100.0 / COUNT(*) as success_rate
        FROM contracts c
        JOIN employees e ON c.eid = e.eid
        WHERE e.role = ? AND e.education_level = ?
    ");
    $stmt->bind_param('ss', $role, $educationLevel);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return [
        'avg_duration' => round($result['avg_duration'] ?? 6, 1),
        'success_rate' => round($result['success_rate'] ?? 70, 1),
        'sample_size' => 'Berdasarkan data historis serupa'
    ];
}

function analyzeTenure($employee) {
    $joinDate = strtotime($employee['join_date']);
    $tenureMonths = (time() - $joinDate) / (30 * 24 * 3600);
    
    return [
        'months' => round($tenureMonths, 1),
        'category' => getTenureCategory($tenureMonths),
        'loyalty_score' => min($tenureMonths / 24, 1.0) // Max score at 2 years
    ];
}

function generateIntelligentRecommendation($employee, $analytics) {
    $recommendations = [];
    
    // Primary recommendation logic
    $match = $analytics['education_role_match'];
    $risk = $analytics['resignation_risk'];
    $success = $analytics['success_probability'];
    $history = $analytics['contract_history'];
    
    // Rule 1: Education-role mismatch (max 6 months)
    if ($match['score'] < 0.5) {
        $recommendations[] = [
            'recommended_type' => 'probation',
            'recommended_duration' => 6,
            'confidence' => 'high',
            'reasons' => ['Ketidaksesuaian pendidikan-role. Maksimal 6 bulan untuk evaluasi.'],
            'success_probability' => 35
        ];
    }
    
    // Rule 2: High resignation risk
    if ($risk['score'] > 0.7) {
        $recommendations[] = [
            'recommended_type' => 'probation',
            'recommended_duration' => 6,
            'confidence' => 'high',
            'reasons' => ['Risiko resign tinggi. Probation 6 bulan untuk mitigasi risiko.'],
            'success_probability' => 40
        ];
    }
    
    // Rule 3: Good match with S1 education (up to 12 months)
    if ($match['score'] >= 0.7 && $employee['education_level'] === 'S1') {
        $recommendations[] = [
            'recommended_type' => '1',
            'recommended_duration' => 12,
            'confidence' => 'high',
            'reasons' => ['Kecocokan baik dengan pendidikan S1. Rekomendasi kontrak 12 bulan.'],
            'success_probability' => 75
        ];
    }
    
    // Rule 4: Permanent eligibility (after 2+ successful contracts)
    if ($history['completed_contracts'] >= 2 && $success['score'] > 0.8) {
        $recommendations[] = [
            'recommended_type' => 'permanent',
            'recommended_duration' => null,
            'confidence' => 'very_high',
            'reasons' => ['Telah menyelesaikan 2+ kontrak dengan sukses. Layak permanent.'],
            'success_probability' => 90
        ];
    }
    
    // Default recommendation
    if (empty($recommendations)) {
        $duration = $analytics['industry_benchmark']['avg_duration'];
        $recommendations[] = [
            'recommended_type' => 'probation',
            'recommended_duration' => round($duration),
            'confidence' => 'medium',
            'reasons' => ['Berdasarkan standar industri untuk role serupa.'],
            'success_probability' => $analytics['industry_benchmark']['success_rate']
        ];
    }
    
    // Sort by success probability
    usort($recommendations, function($a, $b) {
        return $b['success_probability'] - $a['success_probability'];
    });
    
    $primary = $recommendations[0];
    $primary['detailed_analysis'] = $analytics;
    $primary['insights'] = generateInsights($analytics);
    $primary['risk_factors'] = $risk['factors'];
    
    return $primary;
}

// Helper functions
function getMatchLevel($score) {
    if ($score >= 0.8) return 'Excellent';
    if ($score >= 0.6) return 'Good';
    if ($score >= 0.4) return 'Fair';
    return 'Poor';
}

function getMatchDescription($score, $major, $role) {
    $level = getMatchLevel($score);
    return "$level match antara $major dan $role (Score: " . round($score * 100, 1) . "%)";
}

function getRiskLevel($score) {
    if ($score >= 0.7) return 'High';
    if ($score >= 0.4) return 'Medium';
    return 'Low';
}

function getTenureCategory($months) {
    if ($months < 6) return 'Baru';
    if ($months < 12) return 'Junior';
    if ($months < 24) return 'Regular';
    return 'Senior';
}

function generateInsights($analytics) {
    $insights = [];
    
    $match = $analytics['education_role_match'];
    if ($match['score'] > 0.8) {
        $insights[] = 'Kecocokan pendidikan dan role sangat baik';
    }
    
    $risk = $analytics['resignation_risk'];
    if ($risk['score'] < 0.3) {
        $insights[] = 'Risiko resign rendah, karyawan berpotensi loyal';
    }
    
    $tenure = $analytics['tenure_analysis'];
    if ($tenure['loyalty_score'] > 0.8) {
        $insights[] = 'Menunjukkan loyalitas tinggi dengan masa kerja ' . $tenure['months'] . ' bulan';
    }
    
    return $insights;
}

function getResignStatistics($db, $role, $education_level) {
    $stats = [
        'probation_success_rate' => 75,
        'contract_1_success_rate' => 65,
        'contract_2_success_rate' => 70,
        'total_employees' => 0
    ];
    
    try {
        // Get statistics for similar roles and education
        $sql = "SELECT 
                    COUNT(CASE WHEN c.type = 'probation' AND c.status = 'completed' THEN 1 END) as probation_completed,
                    COUNT(CASE WHEN c.type = 'probation' THEN 1 END) as probation_total,
                    COUNT(CASE WHEN c.type = '1' AND c.status = 'completed' THEN 1 END) as contract_1_completed,
                    COUNT(CASE WHEN c.type = '1' THEN 1 END) as contract_1_total,
                    COUNT(CASE WHEN c.type = '2' AND c.status = 'completed' THEN 1 END) as contract_2_completed,
                    COUNT(CASE WHEN c.type = '2' THEN 1 END) as contract_2_total
                FROM employees e
                JOIN contracts c ON e.eid = c.eid
                WHERE e.role LIKE ? AND e.education_level = ?";
        
        $stmt = $db->prepare($sql);
        $role_pattern = "%$role%";
        $stmt->bind_param('ss', $role_pattern, $education_level);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            
            if ($data['probation_total'] > 0) {
                $stats['probation_success_rate'] = ($data['probation_completed'] / $data['probation_total']) * 100;
            }
            if ($data['contract_1_total'] > 0) {
                $stats['contract_1_success_rate'] = ($data['contract_1_completed'] / $data['contract_1_total']) * 100;
            }
            if ($data['contract_2_total'] > 0) {
                $stats['contract_2_success_rate'] = ($data['contract_2_completed'] / $data['contract_2_total']) * 100;
            }
        }
        
    } catch (Exception $e) {
        // Use default values if query fails
    }
    
    return $stats;
}

function addEmployeeFromForm($db, $data) {
    try {
        // Only HR and Admin can add employees
        if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
            throw new Exception('Hanya HR dan Admin yang dapat menambahkan karyawan');
        }
        
        // Map frontend field names to database field names
        $required_fields = ['name', 'email', 'join_date', 'education_level', 'major', 'role', 'division_id'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field $field harus diisi");
            }
        }
        
        // Set employee status based on contract type
        $status = 'active'; // Default status
        
        // Convert string boolean to actual boolean (frontend sends 'true'/'false' as strings)
        $is_active_probation = ($data['is_active_probation'] === 'true' || $data['is_active_probation'] === true);
        
        // If probation is active, set status to probation
        if ($is_active_probation) {
            $status = 'probation';
        }
        // If probation is finished AND contract_type is specified, set status to active
        else if (!empty($data['contract_type']) && !$is_active_probation) {
            $status = 'active';
        }
        
        // Prepare variables untuk bind_param (tidak bisa menggunakan ?? operator langsung)
        $phone = $data['phone'] ?? null;
        $birth_date = $data['birth_date'] ?? null;
        $address = $data['address'] ?? null;
        
        // Database menggunakan kolom 'name' - removed probation_start_date karena tidak ada di database
        $stmt = $db->prepare("INSERT INTO employees (name, email, phone, birth_date, address, education_level, major, role, division_id, status, join_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param('sssssssssss', 
            $data['name'], // frontend dan database sama-sama menggunakan 'name'
            $data['email'],
            $phone,
            $birth_date,
            $address,
            $data['education_level'],
            $data['major'],
            $data['role'],
            $data['division_id'],
            $status,
            $data['join_date']
        );
        
        if ($stmt->execute()) {
            $eid = $db->lastInsertId();
            
            // Create contracts based on workflow
            // Always create probation contract first (if probation dates are provided)
            if (!empty($data['join_date']) && !empty($data['probation_end_date'])) {
                $probation_start = $data['join_date'];
                $probation_end = $data['probation_end_date'];
                $probation_status = $is_active_probation ? 'active' : 'completed';
                
                $probation_stmt = $db->prepare("INSERT INTO contracts (eid, type, start_date, end_date, status) VALUES (?, 'probation', ?, ?, ?)");
                $probation_stmt->bind_param('isss', $eid, $probation_start, $probation_end, $probation_status);
                $probation_stmt->execute();
            }
            
            // If probation is finished AND contract_type is specified, create the new contract
            if (!empty($data['contract_type']) && !$is_active_probation) {
                $contract_type = $data['contract_type'];
                $contract_start = !empty($data['contract_start_date']) ? $data['contract_start_date'] : $data['join_date'];
                $contract_end = null;
                
                // Calculate end date based on contract type
                if ($contract_type === 'permanent') {
                    $contract_end = null; // Permanent contract has no end date
                } else if (!empty($data['contract_end_date'])) {
                    $contract_end = $data['contract_end_date']; // Use provided end date
                } else {
                    // Calculate end date based on contract type (fallback)
                    switch ($contract_type) {
                        case '1':
                            $contract_end = date('Y-m-d', strtotime($contract_start . ' + 6 months'));
                            break;
                        case '2':
                            $contract_end = date('Y-m-d', strtotime($contract_start . ' + 12 months'));
                            break;
                        case '3':
                            $contract_end = date('Y-m-d', strtotime($contract_start . ' + 24 months'));
                            break;
                        default:
                            $contract_end = date('Y-m-d', strtotime($contract_start . ' + 3 months'));
                    }
            }
            
                // Insert the new contract
            if ($contract_end) {
                $contract_stmt = $db->prepare("INSERT INTO contracts (eid, type, start_date, end_date, status) VALUES (?, ?, ?, ?, 'active')");
                $contract_stmt->bind_param('isss', $eid, $contract_type, $contract_start, $contract_end);
            } else {
                $contract_stmt = $db->prepare("INSERT INTO contracts (eid, type, start_date, status) VALUES (?, ?, ?, 'active')");
                $contract_stmt->bind_param('iss', $eid, $contract_type, $contract_start);
            }
            $contract_stmt->execute();
            } else if ($is_active_probation) {
                // If probation is still active, create only probation contract (if not already created above)
                if (empty($data['probation_end_date'])) {
                    $probation_start = $data['join_date'];
                    $probation_end = date('Y-m-d', strtotime($probation_start . ' + 3 months'));
                    
                    $probation_stmt = $db->prepare("INSERT INTO contracts (eid, type, start_date, end_date, status) VALUES (?, 'probation', ?, ?, 'active')");
                    $probation_stmt->bind_param('iss', $eid, $probation_start, $probation_end);
                    $probation_stmt->execute();
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Karyawan berhasil ditambahkan',
                'eid' => $eid
            ]);
        } else {
            throw new Exception('Gagal menambahkan karyawan: ' . $db->getConnection()->error);
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function addEmployee($db, $data) {
    try {
        // Only HR can add employees
        if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
            throw new Exception('Hanya HR yang dapat menambahkan karyawan');
        }
        
        $required_fields = ['name', 'email', 'join_date', 'education_level', 'major', 'role', 'division_id'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field $field harus diisi");
            }
        }
        
        // Set employee status based on contract type
        $status = 'active'; // Default status
        
        // Convert string boolean to actual boolean (frontend sends 'true'/'false' as strings)
        $is_active_probation = ($data['is_active_probation'] === 'true' || $data['is_active_probation'] === true);
        
        // If probation is active, set status to probation
        if ($is_active_probation) {
            $status = 'probation';
        }
        // If probation is finished AND contract_type is specified, set status to active
        else if (!empty($data['contract_type']) && !$is_active_probation) {
            $status = 'active';
        }
        
        // Prepare variables untuk bind_param
        $phone = $data['phone'] ?? null;
        $birth_date = $data['birth_date'] ?? null;
        $address = $data['address'] ?? null;
        $last_education_place = $data['last_education_place'] ?? null;
        $designation = $data['designation'] ?? null;
        
        $stmt = $db->prepare("INSERT INTO employees (name, email, phone, birth_date, address, join_date, education_level, major, last_education_place, designation, role, division_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param('sssssssssssss', 
            $data['name'],
            $data['email'],
            $phone,
            $birth_date,
            $address,
            $data['join_date'],
            $data['education_level'],
            $data['major'],
            $last_education_place,
            $designation,
            $data['role'],
            $data['division_id'],
            $status
        );
        
        if ($stmt->execute()) {
            $eid = $db->lastInsertId();
            
            // Create contracts based on workflow
            // Always create probation contract first (if probation dates are provided)
            if (!empty($data['join_date']) && !empty($data['probation_end_date'])) {
                $probation_start = $data['join_date'];
                $probation_end = $data['probation_end_date'];
                $probation_status = $is_active_probation ? 'active' : 'completed';
                
                $probation_stmt = $db->prepare("INSERT INTO contracts (eid, type, start_date, end_date, status) VALUES (?, 'probation', ?, ?, ?)");
                $probation_stmt->bind_param('isss', $eid, $probation_start, $probation_end, $probation_status);
                $probation_stmt->execute();
            }
            
            // If probation is finished AND contract_type is specified, create the new contract
            if (!empty($data['contract_type']) && !$is_active_probation) {
                $contract_type = $data['contract_type'];
                $contract_start = !empty($data['contract_start_date']) ? $data['contract_start_date'] : $data['join_date'];
                $contract_end = null;
                
                // Calculate end date based on contract type
                if ($contract_type === 'permanent') {
                    $contract_end = null; // Permanent contract has no end date
                } else if (!empty($data['contract_end_date'])) {
                    $contract_end = $data['contract_end_date']; // Use provided end date
                } else {
                    // Calculate end date based on contract type (fallback)
                    switch ($contract_type) {
                        case '1':
                            $contract_end = date('Y-m-d', strtotime($contract_start . ' + 6 months'));
                            break;
                        case '2':
                            $contract_end = date('Y-m-d', strtotime($contract_start . ' + 12 months'));
                            break;
                        case '3':
                            $contract_end = date('Y-m-d', strtotime($contract_start . ' + 24 months'));
                            break;
                        default:
                            $contract_end = date('Y-m-d', strtotime($contract_start . ' + 3 months'));
                    }
            }
            
                // Insert the new contract
            if ($contract_end) {
                $contract_stmt = $db->prepare("INSERT INTO contracts (eid, type, start_date, end_date, status) VALUES (?, ?, ?, ?, 'active')");
                $contract_stmt->bind_param('isss', $eid, $contract_type, $contract_start, $contract_end);
            } else {
                $contract_stmt = $db->prepare("INSERT INTO contracts (eid, type, start_date, status) VALUES (?, ?, ?, 'active')");
                $contract_stmt->bind_param('iss', $eid, $contract_type, $contract_start);
            }
            $contract_stmt->execute();
            } else if ($is_active_probation) {
                // If probation is still active, create only probation contract (if not already created above)
                if (empty($data['probation_end_date'])) {
                    $probation_start = $data['join_date'];
                    $probation_end = date('Y-m-d', strtotime($probation_start . ' + 3 months'));
                    
                    $probation_stmt = $db->prepare("INSERT INTO contracts (eid, type, start_date, end_date, status) VALUES (?, 'probation', ?, ?, 'active')");
                    $probation_stmt->bind_param('iss', $eid, $probation_start, $probation_end);
                    $probation_stmt->execute();
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Karyawan berhasil ditambahkan',
                'eid' => $eid
            ]);
        } else {
            throw new Exception('Gagal menambahkan karyawan');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function addRecommendation($db, $data) {
    try {
        if (!isset($data['eid']) || !isset($data['recommendation_type'])) {
            throw new Exception('Data tidak lengkap');
        }
        
        // Prepare variables untuk bind_param
        $recommended_duration = $data['recommended_duration'] ?? null;
        $reason = $data['reason'] ?? null;
        
        // Create system recommendation text
        $recommended_contract = $data['recommended_contract'] ?? '';
        $system_recommendation = "Rekomendasi: {$recommended_contract}";
        if ($recommended_duration) {
            $system_recommendation .= " ({$recommended_duration} bulan)";
        }
        
        $stmt = $db->prepare("INSERT INTO recommendations (eid, recommended_by, recommendation_type, recommended_duration, contract_start_date, reason, system_recommendation, status) VALUES (?, ?, ?, ?, NULL, ?, ?, 'pending')");
        
        $stmt->bind_param('iisiss',
            $data['eid'],
            $_SESSION['user_id'],
            $data['recommendation_type'],
            $recommended_duration,
            $reason,
            $system_recommendation
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Rekomendasi berhasil ditambahkan'
            ]);
        } else {
            throw new Exception('Gagal menambahkan rekomendasi');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function updateEmployee($db, $data) {
    try {
        if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
            throw new Exception('Hanya HR yang dapat mengupdate karyawan');
        }
        
        if (!isset($data['eid'])) {
            throw new Exception('Employee ID harus diisi');
        }
        
        $sql = "UPDATE employees SET ";
        $params = [];
        $types = "";
        
        $allowed_fields = ['name', 'email', 'phone', 'birth_date', 'address', 'status', 'education_level', 'major', 'last_education_place', 'designation', 'role', 'division_id', 'resign_date', 'resign_reason'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $sql .= "$field = ?, ";
                $params[] = $data[$field];
                $types .= "s";
            }
        }
        
        $sql = rtrim($sql, ", ");
        $sql .= " WHERE eid = ?";
        $params[] = $data['eid'];
        $types .= "i";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Data karyawan berhasil diupdate'
            ]);
        } else {
            throw new Exception('Gagal mengupdate data karyawan');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function deleteEmployee($db, $eid) {
    try {
        if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
            throw new Exception('Hanya HR yang dapat menghapus karyawan');
        }
        
        $stmt = $db->prepare("DELETE FROM employees WHERE eid = ?");
        $stmt->bind_param('i', $eid);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Karyawan berhasil dihapus'
            ]);
        } else {
            throw new Exception('Gagal menghapus karyawan');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// Fungsi analisis untuk manager
function getManagerAnalytics($db) {
    try {
        if ($_SESSION['role'] !== 'manager') {
            throw new Exception('Akses ditolak. Hanya manager yang dapat mengakses fitur ini.');
        }
        
        $division_id = $_SESSION['division_id'];
        
        // Enhanced query dengan data mining fields - Pastikan mengambil kontrak terbaru
        $employees_sql = "SELECT e.*, d.division_name,
                         CASE 
                             WHEN e.birth_date IS NOT NULL AND e.birth_date != '0000-00-00' 
                             THEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE())
                             ELSE NULL 
                         END as age,
                         c.type as current_contract_type, 
                         c.start_date as contract_start, 
                         c.end_date as contract_end,
                         c.status as contract_status,
                         TIMESTAMPDIFF(MONTH, e.join_date, CURDATE()) as tenure_months,
                         CASE 
                             WHEN c.end_date IS NOT NULL 
                             THEN DATEDIFF(c.end_date, CURDATE())
                             ELSE NULL 
                         END as days_to_contract_end
                  FROM employees e 
                  LEFT JOIN divisions d ON e.division_id = d.division_id
                  LEFT JOIN contracts c ON e.eid = c.eid AND c.status = 'active' 
                      AND c.contract_id = (
                          SELECT MAX(contract_id) 
                          FROM contracts c2 
                          WHERE c2.eid = e.eid AND c2.status = 'active'
                      )
                  WHERE e.division_id = ? AND e.status = 'active'
                  ORDER BY e.join_date DESC";
        
        $stmt = $db->prepare($employees_sql);
        $stmt->bind_param('i', $division_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            // Advanced education-job match menggunakan data mining
            $education_match = calculateAdvancedEducationJobMatch($row);
            $row['education_job_match'] = $education_match['match_status'];
            $row['match_level'] = $education_match['level'];
            $row['match_category'] = $education_match['category'];
            $row['match_details'] = $education_match['details'];
            
            // Data mining contract recommendation
            $row['data_mining_recommendation'] = generateDataMiningContractRecommendation($row, $db);
            
            $employees[] = $row;
        }
        
        // Enhanced analytics dengan data mining insights
        $stats = calculateDivisionStats($db, $division_id);
        $contract_summary = getContractStatusSummary($employees);
        $urgent_actions = getUrgentActions($employees);
        $division_analytics = generateDivisionAnalytics($employees);
        
        // Data Mining Insights Summary
        $data_mining_summary = [
            'avg_education_match' => array_sum(array_column($employees, 'education_job_match')) / count($employees),
            'high_match_employees' => count(array_filter($employees, function($emp) { 
                return $emp['education_job_match'] >= 70; 
            })),
            'recommended_extensions' => count(array_filter($employees, function($emp) { 
                return $emp['data_mining_recommendation']['recommended_duration'] >= 18; 
            })),
            'high_risk_employees' => count(array_filter($employees, function($emp) { 
                return $emp['data_mining_recommendation']['risk_level'] === 'High'; 
            }))
        ];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'employees' => $employees,
                'division_stats' => array_merge($stats, [
                    'division_name' => $employees[0]['division_name'] ?? 'Unknown Division',
                    'contract_summary' => $contract_summary,
                    'urgent_actions_count' => count($urgent_actions),
                    'data_mining_summary' => $data_mining_summary
                ]),
                'analytics' => $division_analytics,
                'contract_intelligence' => [
                    'status_summary' => $contract_summary,
                    'urgent_actions' => $urgent_actions,
                    'data_mining_insights' => $data_mining_summary,
                    'performance_insights' => array_map(function($emp) {
                        return [
                            'name' => $emp['name'],
                            'match_score' => $emp['education_job_match'],
                            'contract_type' => $emp['current_contract_type'],
                            'data_mining_recommendation' => $emp['data_mining_recommendation'],
                            'recommended_duration' => $emp['data_mining_recommendation']['recommended_duration'],
                            'success_probability' => $emp['data_mining_recommendation']['success_probability']
                        ];
                    }, array_slice($employees, 0, 10))
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getEducationJobAnalysis($db) {
    try {
        if ($_SESSION['role'] !== 'manager') {
            throw new Exception('Akses ditolak');
        }
        
        $division_id = $_SESSION['division_id'];
        $pythonAnalysis = new PythonAnalysisIntegration();
        
        // Analisis kecocokan pendidikan-pekerjaan
        $analysis_sql = "SELECT e.name, e.major, e.role, e.education_level, e.designation,
                                e.join_date, 
                                TIMESTAMPDIFF(MONTH, e.join_date, CURDATE()) as tenure_months,
                                c.type as current_contract_type,
                                (SELECT COUNT(*) FROM contracts WHERE eid = e.eid) as total_contracts
                        FROM employees e 
                        LEFT JOIN contracts c ON e.eid = c.eid AND c.status = 'active'
                        WHERE e.division_id = ? AND e.status = 'active'";
        
        $stmt = $db->prepare($analysis_sql);
        $stmt->bind_param('i', $division_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $education_analysis = [];
        $match_distribution = ['Tinggi' => 0, 'Sedang' => 0, 'Rendah' => 0];
        $contract_progression = [];
        
        while ($row = $result->fetch_assoc()) {
            // Gunakan fungsi analyzeEducationRoleMatch yang sudah diperbaiki
            $match_analysis = analyzeEducationRoleMatch($row);
            $match_score = $match_analysis['score'];
            $match_status = $match_analysis['match_status'];
            
            $row['match_score'] = $match_score;
            $row['match_status'] = $match_status;
            $row['match_level'] = $match_analysis['level'];
            $row['match_category'] = $match_analysis['category'];
            
            $education_analysis[] = $row;
            
            // Update match distribution berdasarkan status baru
            if ($match_status === 'Match') {
                if ($match_score >= 3) {
                    $match_distribution['Tinggi']++;
                } elseif ($match_score >= 2) {
                    $match_distribution['Sedang']++;
                } else {
                    $match_distribution['Rendah']++;
                }
            } else {
                $match_distribution['Rendah']++;
            }
            
            // Progression analysis
            if ($row['tenure_months'] >= 3 && $row['current_contract_type'] === 'probation') {
                $contract_progression[] = [
                    'name' => $row['name'],
                    'status' => 'Ready for Extension',
                    'match_status' => $match_status,
                    'match_score' => $match_score
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'education_analysis' => $education_analysis,
                'match_distribution' => $match_distribution,
                'contract_progression' => $contract_progression,
                'insights' => generateEducationInsights($education_analysis)
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getDivisionIntelligence($db) {
    try {
        if ($_SESSION['role'] !== 'manager') {
            throw new Exception('Akses ditolak');
        }
        
        $division_id = $_SESSION['division_id'];
        $pythonAnalysis = new PythonAnalysisIntegration();
        
        // Predictive analytics untuk post-probation
        $prediction_sql = "SELECT e.*, 
                                  TIMESTAMPDIFF(MONTH, e.join_date, CURDATE()) as tenure_months,
                                  c.type as current_contract_type,
                                  c.end_date as contract_end_date,
                                  DATEDIFF(c.end_date, CURDATE()) as days_to_contract_end
                          FROM employees e 
                          LEFT JOIN contracts c ON e.eid = c.eid AND c.status = 'active'
                          WHERE e.division_id = ? AND e.status = 'active'";
        
        $stmt = $db->prepare($prediction_sql);
        $stmt->bind_param('i', $division_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $predictions = [];
        $upcoming_decisions = [];
        $performance_insights = [];
        
        // Get Python analysis results for this division
        $pythonResults = $pythonAnalysis->generatePerformancePredictionsPHP($division_id);
        
        while ($row = $result->fetch_assoc()) {
            $prediction = generatePerformancePrediction($row);
            $predictions[] = $prediction;
            
            // Upcoming contract decisions
            if ($row['days_to_contract_end'] <= 30 && $row['days_to_contract_end'] > 0) {
                $upcoming_decisions[] = [
                    'employee' => $row,
                    'prediction' => $prediction,
                    'urgency' => $row['days_to_contract_end'] <= 7 ? 'high' : 'medium'
                ];
            }
            
            // Performance insights
            $performance_insights[] = generatePerformanceInsight($row);
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'predictions' => $predictions,
                'upcoming_decisions' => $upcoming_decisions,
                'performance_insights' => $performance_insights,
                'division_health' => calculateDivisionHealth($predictions),
                'python_analysis' => $pythonResults
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function calculateAdvancedEducationJobMatch($employee) {
    $major = strtoupper($employee['major'] ?? '');
    $role = strtoupper($employee['role'] ?? '');
    $designation = strtoupper($employee['designation'] ?? '');
    
    // DATA INTELLIGENCE: Comprehensive IT Keywords - FIXED TO MATCH DYNAMIC INTELLIGENCE
    $it_education_keywords = [
        'INFORMATION TECHNOLOGY', 'TEKNIK INFORMATIKA', 'ILMU KOMPUTER', 'SISTEM INFORMASI',
        'COMPUTER SCIENCE', 'SOFTWARE ENGINEERING', 'INFORMATIKA', 'TEKNIK KOMPUTER',
        'KOMPUTER', 'TEKNOLOGI INFORMASI', 'INFORMATION SYSTEM', 'INFORMATICS ENGINEERING',
        'INFORMATIC ENGINEERING', 'COMPUTER ENGINEERING', 'INFORMATION MANAGEMENT',
        'INFORMATICS MANAGEMENT', 'MANAGEMENT INFORMATIKA', 'SISTEM KOMPUTER',
        'KOMPUTERISASI AKUNTANASI', 'COMPUTERIZED ACCOUNTING', 'COMPUTATIONAL SCIENCE',
        'INFORMATICS', 'INFORMATICS TECHNOLOGY', 'COMPUTER AND INFORMATICS ENGINEERING',
        'ENGINEERING INFORMATIC', 'INDUSTRIAL ENGINEERING_INFORMATIC',
        // CRITICAL: Added missing IT Education Majors from CSV Analysis
        'STATISTICS', 'TECHNOLOGY MANAGEMENT', 'ELECTRICAL ENGINEERING', 'ELECTRONICS ENGINEERING',
        'MASTER IN INFORMATICS', 'ICT', 'COMPUTER SCIENCE & ELECTRONICS', 'COMPUTER TELECOMMUNICATION',
        'TELECOMMUNICATION ENGINEERING', 'TELECOMMUNICATIONS ENGINEERING', 'TELECOMMUNICATION & MEDIA',
        'TEKNIK ELEKTRO', 'COMPUTER SCINCE', 'COMPUTER SCIENCES & ENGINEERING', 'COMPUTER SYSTEM',
        'COMPUTER SIENCE', 'COMPUTER TECHNOLOGY', 'COMPUTER SCINCE', 'INFORMASTION SYSTEM',
        'INFORMATICS TECHNIQUE', 'INFORMATIOCS', 'INFORMATICS TECHNOLOGY', 'TEKNIK KOMPUTER & JARINGAN',
        'INFORMATION ENGINEERING', 'SOFTWARE ENGINEERING TECHNOLOGY', 'DIGITAL MEDIA TECHNOLOGY',
        'INFORMATIC ENGINEETING', 'INFORMATION SYTEM', 'INFORMASTION SYSTEM', 'MATERIALS ENGINEERING',
        'NETWORK MANAGEMENT', 'INFORMATICS SYSTEM', 'BUSINESS INFORMATION SYSTEM',
        'PHYSICS ENGINEERING', 'ELECTRICAL ENGINEERING_ELECTRONICS', 'ELECTRONIC ENGINEERING',
        'ELECTRONICS & COMPUTER ENGINEERING', 'TELECOMMUNICATION ENGINEERING_ELECTRONICS'
    ];
    
    $it_job_keywords = [
        'DEVELOPER', 'PROGRAMMER', 'SOFTWARE', 'TECHNICAL', 'FRONTEND', 'BACKEND', 
        'FULLSTACK', 'FULL STACK', 'JAVA', 'NET', '.NET', 'API', 'ETL', 'MOBILE',
        'ANDROID', 'WEB', 'UI/UX', 'FRONT END', 'BACK END', 'PEGA', 'RPA',
        'ANALYST', 'BUSINESS ANALYST', 'SYSTEM ANALYST', 'DATA ANALYST', 'DATA SCIENTIST',
        'QUALITY ASSURANCE', 'QA', 'TESTER', 'TEST ENGINEER', 'CONSULTANT',
        'IT CONSULTANT', 'TECHNOLOGY CONSULTANT', 'TECHNICAL CONSULTANT',
        'PRODUCT OWNER', 'SCRUM MASTER', 'PMO', 'IT OPERATION', 'DEVOPS',
        'SYSTEM ADMINISTRATOR', 'DATABASE ADMINISTRATOR', 'NETWORK',
        // Enhanced IT Job Roles from CSV Analysis
        'BI DEVELOPER', 'BI COGNOS DEVELOPER', 'POWER BI DEVELOPER', 'ETL DEVELOPER',
        'DATA ENGINEER', 'DATABASE ADMINISTRATOR', 'DBA', 'DEVOPS ENGINEER', 'CLOUD ENGINEER',
        'INFRASTRUCTURE ENGINEER', 'SECURITY ENGINEER', 'CYBERSECURITY', 'NETWORK ENGINEER',
        'NETWORK ADMINISTRATOR', 'IT SUPPORT', 'TECHNICAL SUPPORT', 'HELP DESK',
        'SYSTEM ADMINISTRATOR', 'SOFTWARE ENGINEER', 'SOFTWARE ARCHITECT', 'SOLUTIONS ARCHITECT',
        'MOBILE DEVELOPER', 'IOS DEVELOPER', 'ANDROID DEVELOPER', 'REACT DEVELOPER',
        'ANGULAR DEVELOPER', 'VUE DEVELOPER', 'NODE.JS DEVELOPER', 'PYTHON DEVELOPER',
        'JAVA DEVELOPER', 'C# DEVELOPER', 'PHP DEVELOPER', 'GOLANG DEVELOPER',
        'PROJECT MANAGER', 'IT PROJECT MANAGER', 'TECHNICAL PROJECT MANAGER',
        'DELIVERY MANAGER', 'PRODUCT MANAGER', 'PROGRAM MANAGER', 'SCRUM MASTER',
        'AGILE COACH', 'TEAM LEAD', 'TECH LEAD', 'ENGINEERING MANAGER', 'IT MANAGER',
        'DEVELOPMENT MANAGER', 'BUSINESS INTELLIGENCE ANALYST', 'BI ANALYST', 'REPORT DESIGNER',
        'REPORTING ANALYST', 'PROCESS ANALYST', 'FUNCTIONAL ANALYST', 'TECHNICAL ANALYST',
        'PERFORMANCE ANALYST', 'IT QUALITY ASSURANCE', 'TESTING ENGINEER', 'TESTER SUPPORT',
        'JR TESTER', 'IT TESTER', 'TESTING SPECIALIST', 'TESTING COORDINATOR', 'QUALITY TESTER',
        'JR QUALITY ASSURANCE', 'QUALITY ASSURANCE TESTER', 'TESTING ENGINEER SPECIALIST',
        'APPRENTICE TESTER', 'JR TESTER LEVEL 1', 'LEAD QA', 'LEAD BACKEND DEVELOPER'
    ];
    
    // Check if education is IT-related
    $is_it_education = false;
    foreach ($it_education_keywords as $keyword) {
        if (strpos($major, $keyword) !== false) {
            $is_it_education = true;
            break;
        }
    }
    
    // Check if job is IT-related
    $is_it_job = false;
    foreach ($it_job_keywords as $keyword) {
        if (strpos($role, $keyword) !== false || strpos($designation, $keyword) !== false) {
            $is_it_job = true;
            break;
        }
    }
    
    // UNIFIED SCORING LOGIC - match manager_analytics_simple.php exactly
    $score = 0;
    if ($is_it_education && $is_it_job) {
        $score = 3; // Perfect match
        $match_status = 'Match';
        $level = 'High';
        $category = 'Perfect Match';
    } elseif ($is_it_education || $is_it_job) {
        $score = 2; // Good match
        $match_status = 'Match';
        $level = 'Medium';
        $category = 'Good Match';
    } else {
        $score = 1; // Basic match
        $match_status = 'Unmatch';
        $level = 'Low';
        $category = 'No Match';
    }
    
    return [
        'score' => $score,
        'match_status' => $match_status,
        'level' => $level,
        'category' => $category,
        'details' => [
            'education_match' => $is_it_education,
            'role_match' => $is_it_job,
            'education_field' => $major,
            'role_field' => $role
        ]
    ];
}

function analyzeResignPatterns($db, $major, $role, $education_level) {
    try {
        // Query untuk mendapatkan pola resign berdasarkan karakteristik serupa
        $sql = "SELECT 
                    TIMESTAMPDIFF(MONTH, join_date, resign_date) as resign_duration,
                    COUNT(*) as resign_count,
                    AVG(TIMESTAMPDIFF(MONTH, join_date, resign_date)) as avg_resign_duration
                FROM employees 
                WHERE status = 'resigned' 
                AND major LIKE ? 
                AND role LIKE ?
                AND education_level = ?
                AND resign_date IS NOT NULL
                GROUP BY 
                    CASE 
                        WHEN TIMESTAMPDIFF(MONTH, join_date, resign_date) <= 3 THEN '0-3 bulan'
                        WHEN TIMESTAMPDIFF(MONTH, join_date, resign_date) <= 6 THEN '4-6 bulan'
                        WHEN TIMESTAMPDIFF(MONTH, join_date, resign_date) <= 12 THEN '7-12 bulan'
                        WHEN TIMESTAMPDIFF(MONTH, join_date, resign_date) <= 24 THEN '13-24 bulan'
                        ELSE '24+ bulan'
                    END";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('sss', $major, $role, $education_level);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $resign_patterns = [];
        $total_resigns = 0;
        
        while ($row = $result->fetch_assoc()) {
            $resign_patterns[] = $row;
            $total_resigns += $row['resign_count'];
        }
        
        // Hitung success rate untuk profil serupa
        $success_sql = "SELECT 
                           COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
                           COUNT(*) as total_count
                       FROM employees 
                       WHERE major LIKE ? 
                       AND role LIKE ?
                       AND education_level = ?";
        
        $success_stmt = $db->prepare($success_sql);
        $success_stmt->bind_param('sss', $major, $role, $education_level);
        $success_stmt->execute();
        $success_result = $success_stmt->get_result();
        $success_data = $success_result->fetch_assoc();
        
        $success_rate = $success_data['total_count'] > 0 ? 
            ($success_data['active_count'] / $success_data['total_count']) * 100 : 0;
        
        return [
            'resign_patterns' => $resign_patterns,
            'total_resigns' => $total_resigns,
            'success_rate' => round($success_rate, 1),
            'sample_size' => $success_data['total_count']
        ];
        
    } catch (Exception $e) {
        return [
            'resign_patterns' => [],
            'total_resigns' => 0,
            'success_rate' => 50, // Default neutral
            'sample_size' => 0
        ];
    }
}

function analyzeResignBeforeContractEnd($db, $major, $role, $education_level, $target_duration_months) {
    try {
        // Query untuk mendapatkan pola resign sebelum kontrak habis berdasarkan durasi kontrak tertentu
        $sql = "SELECT 
                    c.type as contract_type,
                    TIMESTAMPDIFF(MONTH, c.start_date, e.resign_date) as resign_duration_months,
                    c.duration_months as planned_duration_months,
                    COUNT(*) as resign_count,
                    AVG(TIMESTAMPDIFF(MONTH, c.start_date, e.resign_date)) as avg_resign_duration
                FROM employees e
                JOIN contracts c ON e.eid = c.eid
                WHERE e.status = 'resigned' 
                AND e.major LIKE ? 
                AND e.role LIKE ?
                AND e.education_level = ?
                AND e.resign_date IS NOT NULL
                AND c.duration_months = ?
                AND TIMESTAMPDIFF(MONTH, c.start_date, e.resign_date) < c.duration_months
                GROUP BY c.type, c.duration_months";
        
        $stmt = $db->prepare($sql);
        $major_pattern = "%$major%";
        $role_pattern = "%$role%";
        $stmt->bind_param('sssi', $major_pattern, $role_pattern, $education_level, $target_duration_months);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $early_resign_data = [];
        $total_early_resigns = 0;
        
        while ($row = $result->fetch_assoc()) {
            $early_resign_data[] = $row;
            $total_early_resigns += $row['resign_count'];
        }
        
        // Query untuk total karyawan dengan profil serupa dan durasi kontrak yang sama
        $total_sql = "SELECT COUNT(*) as total_similar_contracts
                     FROM employees e
                     JOIN contracts c ON e.eid = c.eid
                     WHERE e.major LIKE ? 
                     AND e.role LIKE ?
                     AND e.education_level = ?
                     AND c.duration_months = ?";
        
        $total_stmt = $db->prepare($total_sql);
        $total_stmt->bind_param('sssi', $major_pattern, $role_pattern, $education_level, $target_duration_months);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();
        $total_data = $total_result->fetch_assoc();
        
        $total_contracts = $total_data['total_similar_contracts'];
        $early_resign_rate = $total_contracts > 0 ? ($total_early_resigns / $total_contracts) * 100 : 0;
        
        // Analisis pola resign berdasarkan periode
        $resign_periods = [];
        foreach ($early_resign_data as $data) {
            $period = '';
            $resign_month = $data['resign_duration_months'];
            
            if ($resign_month <= 3) {
                $period = '0-3 bulan';
            } elseif ($resign_month <= 6) {
                $period = '4-6 bulan';
            } elseif ($resign_month <= 12) {
                $period = '7-12 bulan';
            } else {
                $period = '13+ bulan';
            }
            
            if (!isset($resign_periods[$period])) {
                $resign_periods[$period] = 0;
            }
            $resign_periods[$period] += $data['resign_count'];
        }
        
        return [
            'target_duration_months' => $target_duration_months,
            'early_resign_rate' => round($early_resign_rate, 1),
            'total_early_resigns' => $total_early_resigns,
            'total_similar_contracts' => $total_contracts,
            'resign_periods' => $resign_periods,
            'early_resign_data' => $early_resign_data,
            'sample_size' => $total_contracts,
            'risk_assessment' => $early_resign_rate > 30 ? 'High Risk' : 
                               ($early_resign_rate > 15 ? 'Medium Risk' : 'Low Risk')
        ];
        
    } catch (Exception $e) {
        return [
            'target_duration_months' => $target_duration_months,
            'early_resign_rate' => 0,
            'total_early_resigns' => 0,
            'total_similar_contracts' => 0,
            'resign_periods' => [],
            'early_resign_data' => [],
            'sample_size' => 0,
            'risk_assessment' => 'Unknown'
        ];
    }
}

function getDurationRiskAnalysis($db, $major, $role, $education_level) {
    $duration_options = [6, 12, 18, 24];
    $duration_risks = [];
    
    foreach ($duration_options as $duration) {
        $risk_analysis = analyzeResignBeforeContractEnd($db, $major, $role, $education_level, $duration);
        $duration_risks[$duration] = $risk_analysis;
    }
    
    return $duration_risks;
}

function generateDataMiningContractRecommendation($employee, $db) {
    $education_match = calculateAdvancedEducationJobMatch($employee);
    $resign_analysis = analyzeResignPatterns(
        $db, 
        $employee['major'] ?? '', 
        $employee['role'] ?? '', 
        $employee['education_level'] ?? ''
    );
    
    // NEW: Analisis resign sebelum kontrak habis untuk berbagai durasi
    $duration_risk_analysis = getDurationRiskAnalysis(
        $db,
        $employee['major'] ?? '',
        $employee['role'] ?? '',
        $employee['education_level'] ?? ''
    );
    
    $tenure_months = $employee['tenure_months'] ?? 0;
    $current_contract = $employee['current_contract_type'] ?? 'probation';
    
    // Base recommendation logic berdasarkan Python analysis patterns
    $recommendation = [
        'recommended_duration' => 12, // Default 1 year
        'confidence_level' => 'Medium',
        'risk_level' => 'Medium',
        'success_probability' => 50,
        'reasoning' => [],
        'data_insights' => []
    ];
    
    // Factor 1: Education-Job Match Status
    if ($education_match['match_status'] === 'Match') {
        $recommendation['recommended_duration'] = 24; // 2 years
        $recommendation['confidence_level'] = 'High';
        $recommendation['success_probability'] += 25;
        $recommendation['reasoning'][] = 'Good education-job match detected';
    } else {
        $recommendation['recommended_duration'] = 12; // 1 year
        $recommendation['confidence_level'] = 'Low';
        $recommendation['risk_level'] = 'High';
        $recommendation['success_probability'] -= 15;
        $recommendation['reasoning'][] = 'No education-job match - monitor performance closely';
    }
    
    // Factor 2: Historical Success Rate untuk profil serupa
    if ($resign_analysis['success_rate'] >= 80) {
        $recommendation['recommended_duration'] += 6; // Add 6 months
        $recommendation['confidence_level'] = 'High';
        $recommendation['success_probability'] += 20;
        $recommendation['reasoning'][] = 'Strong historical performance data for similar employee profiles';
    } elseif ($resign_analysis['success_rate'] <= 40) {
        $recommendation['recommended_duration'] = max(6, $recommendation['recommended_duration'] - 6);
        $recommendation['risk_level'] = 'High';
        $recommendation['success_probability'] -= 15;
        $recommendation['reasoning'][] = 'Historical data shows challenges for similar employee profiles';
    }
    
    // NEW Factor 3: Resign Before Contract End Intelligence
    $recommended_duration = $recommendation['recommended_duration'];
    $duration_risk = $duration_risk_analysis[$recommended_duration] ?? null;
    
    if ($duration_risk && $duration_risk['sample_size'] >= 5) {
        if ($duration_risk['early_resign_rate'] > 30) {
            // Tinggi tingkat resign sebelum habis kontrak, turunkan durasi
            $recommendation['recommended_duration'] = max(6, $recommended_duration - 6);
            $recommendation['risk_level'] = 'High';
            $recommendation['success_probability'] -= 20;
            $recommendation['reasoning'][] = sprintf(
                'Data menunjukkan %.1f%% karyawan serupa resign sebelum habis kontrak %d bulan. Durasi dikurangi untuk mitigasi risiko.',
                $duration_risk['early_resign_rate'],
                $recommended_duration
            );
        } elseif ($duration_risk['early_resign_rate'] > 15) {
            // Medium risk, tambahkan warning tapi tidak ubah durasi drastis
            $recommendation['success_probability'] -= 10;
            $recommendation['reasoning'][] = sprintf(
                'Perhatian: %.1f%% karyawan serupa resign sebelum habis kontrak %d bulan. Pantau performa closely.',
                $duration_risk['early_resign_rate'],
                $recommended_duration
            );
        } else {
            // Low risk, durasi aman
            $recommendation['success_probability'] += 10;
            $recommendation['reasoning'][] = sprintf(
                'Data positif: Hanya %.1f%% karyawan serupa resign sebelum habis kontrak %d bulan.',
                $duration_risk['early_resign_rate'],
                $recommended_duration
            );
        }
    }
    
    // Factor 4: Alternative duration suggestion jika current duration berisiko
    $final_duration = $recommendation['recommended_duration'];
    $alternative_durations = [];
    
    foreach ($duration_risk_analysis as $duration => $risk_data) {
        if ($duration != $final_duration && $risk_data['sample_size'] >= 3) {
            if ($risk_data['early_resign_rate'] < 20) {
                $alternative_durations[] = [
                    'duration' => $duration,
                    'early_resign_rate' => $risk_data['early_resign_rate'],
                    'reason' => sprintf('%d bulan: %.1f%% resign rate', $duration, $risk_data['early_resign_rate'])
                ];
            }
        }
    }
    
    // Sort alternatives by lowest resign rate
    usort($alternative_durations, function($a, $b) {
        return $a['early_resign_rate'] - $b['early_resign_rate'];
    });
    
    if (!empty($alternative_durations)) {
        $recommendation['alternative_durations'] = array_slice($alternative_durations, 0, 2);
    }

    // Factor 5: Tenure Performance
    if ($tenure_months >= 12 && $current_contract !== 'permanent') {
        $recommendation['recommended_duration'] = min(36, $recommendation['recommended_duration'] + 12);
        $recommendation['confidence_level'] = 'High';
        $recommendation['success_probability'] += 15;
        $recommendation['reasoning'][] = 'Proven long-term commitment (' . $tenure_months . ' months)';
    } elseif ($tenure_months >= 6) {
        $recommendation['success_probability'] += 10;
        $recommendation['reasoning'][] = 'Stable tenure performance (' . $tenure_months . ' months)';
    }
    
    // Cap duration and probability
    $recommendation['recommended_duration'] = min(36, max(3, $recommendation['recommended_duration']));
    $recommendation['success_probability'] = min(95, max(5, $recommendation['success_probability']));
    
    // Determine final risk level
    if ($recommendation['success_probability'] >= 70) {
        $recommendation['risk_level'] = 'Low';
    } elseif ($recommendation['success_probability'] >= 50) {
        $recommendation['risk_level'] = 'Medium';
    } else {
        $recommendation['risk_level'] = 'High';
    }
    
    // Add data insights
    $recommendation['data_insights'] = [
        'education_match' => $education_match,
        'resign_analysis' => $resign_analysis,
        'duration_risk_analysis' => $duration_risk_analysis,
        'sample_size' => $resign_analysis['sample_size'],
        'data_confidence' => $resign_analysis['sample_size'] >= 10 ? 'High' : 
                           ($resign_analysis['sample_size'] >= 5 ? 'Medium' : 'Low')
    ];
    
    return $recommendation;
}

function getEmployeeDataMiningDetail($db, $eid) {
    try {
        // Get employee details with contract information
        $stmt = $db->prepare("SELECT e.*, d.division_name,
                             CASE 
                                 WHEN e.birth_date IS NOT NULL AND e.birth_date != '0000-00-00' 
                                 THEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE())
                                 ELSE NULL 
                             END as age,
                             c.type as current_contract_type,
                             c.start_date as contract_start,
                             c.end_date as contract_end,
                             c.status as contract_status,
                             DATEDIFF(CURDATE(), e.join_date) / 30 as tenure_months
                             FROM employees e 
                             LEFT JOIN divisions d ON e.division_id = d.division_id 
                             LEFT JOIN contracts c ON e.eid = c.eid AND c.status = 'active'
                                 AND c.contract_id = (
                                     SELECT MAX(contract_id) 
                                     FROM contracts c2 
                                     WHERE c2.eid = e.eid AND c2.status = 'active'
                                 )
                             WHERE e.eid = ?");
        $stmt->bind_param('i', $eid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Karyawan tidak ditemukan');
        }
        
        $employee = $result->fetch_assoc();
        
        // Calculate education-job match for compatibility
        $education_match = calculateAdvancedEducationJobMatch($employee);
        $employee['education_job_match'] = $education_match['match_status'];
        
        // *** INTEGRATE NEW DYNAMIC INTELLIGENCE SYSTEM ***
        $dynamic_intelligence = null;
        
        try {
            // Include dynamic intelligence if not already included
            if (!function_exists('calculateDynamicIntelligence')) {
                // Use output buffering to prevent any accidental output
                ob_start();
                require_once __DIR__ . '/dynamic_intelligence.php';
                $include_output = ob_get_clean();
                
                // Log any unexpected output for debugging
                if (!empty($include_output)) {
                    error_log("Unexpected output from dynamic_intelligence.php: " . $include_output);
                }
            }
            
            // Get MySQLi connection from Database class
            $mysqli_conn = $db->getConnection();
            
            // Validate connection before proceeding
            if (!$mysqli_conn || $mysqli_conn->connect_error) {
                throw new Exception("Database connection error");
            }
            
            // Calculate NEW Dynamic Intelligence
            $dynamic_intelligence = calculateDynamicIntelligence($mysqli_conn, $employee);
            
        } catch (Exception $e) {
            // Fallback to basic analysis if dynamic intelligence fails
            error_log("Dynamic Intelligence Error: " . $e->getMessage());
            $dynamic_intelligence = [
                'match_category' => $education_match['match_status'],
                'optimal_duration' => 12, // Default
                'confidence_level' => 'Medium',
                'sample_size' => 0,
                'reasoning' => ['Fallback analysis - dynamic intelligence unavailable'],
                'historical_patterns' => [
                    'success_rate' => 60,
                    'resign_rate' => 40,
                    'avg_duration' => 12,
                    'total_similar' => 0,
                    'profiles' => []
                ]
            ];
        } catch (Error $e) {
            // Handle PHP fatal errors as well
            error_log("Dynamic Intelligence Fatal Error: " . $e->getMessage());
            $dynamic_intelligence = [
                'match_category' => $education_match['match_status'],
                'optimal_duration' => 12, // Default
                'confidence_level' => 'Medium',
                'sample_size' => 0,
                'reasoning' => ['Fallback analysis - dynamic intelligence fatal error'],
                'historical_patterns' => [
                    'success_rate' => 60,
                    'resign_rate' => 40,
                    'avg_duration' => 12,
                    'total_similar' => 0,
                    'profiles' => []
                ]
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'employee' => $employee,
                'education_match_analysis' => $education_match,
                'dynamic_intelligence' => $dynamic_intelligence, // NEW: Use dynamic intelligence
                // Keep old data for backward compatibility
                'data_mining_summary' => [
                    'match_status' => $dynamic_intelligence['match_category'],
                    'recommended_duration' => $dynamic_intelligence['optimal_duration'],
                    'risk_assessment' => 'Low', // Default since no scoring
                    'confidence_level' => 'Medium' // Fixed default since we removed confidence_level
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("getEmployeeDataMiningDetail Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Gagal memuat detail karyawan: ' . $e->getMessage()
        ]);
    }
}

function generateContractRecommendations($employee, $analytics) {
    $recommendations = [];
    $match = $analytics['education_role_match'];
    $risk = $analytics['resignation_risk'];
    $success = $analytics['contract_success'];
    $history = $analytics['contract_history'];
    
    // Rule 1: Perfect Match (Score 3+) - High potential for longer contracts
    if ($match['score'] >= 3) {
        $recommendations[] = [
            'recommended_type' => '1',
            'recommended_duration' => 12,
            'confidence' => 'very_high',
            'reasons' => ['Perfect match pendidikan-role (Score: ' . $match['score'] . '). Sangat cocok untuk kontrak 12 bulan.'],
            'success_probability' => 85
        ];
    }
    
    // Rule 2: Good Match (Score 2) - Standard contract
    elseif ($match['score'] >= 2) {
        $recommendations[] = [
            'recommended_type' => '1',
            'recommended_duration' => 6,
            'confidence' => 'high',
            'reasons' => ['Good match pendidikan-role (Score: ' . $match['score'] . '). Cocok untuk kontrak 6 bulan.'],
            'success_probability' => 75
        ];
    }
    
    // Rule 3: Basic Match (Score 1) - Short contract with monitoring
    elseif ($match['score'] >= 1) {
        $recommendations[] = [
            'recommended_type' => 'probation',
            'recommended_duration' => 6,
            'confidence' => 'medium',
            'reasons' => ['Basic match pendidikan-role (Score: ' . $match['score'] . '). Probation 6 bulan untuk evaluasi.'],
            'success_probability' => 65
        ];
    }
    
    // Rule 4: No Match (Score 0) - Very short probation
    else {
        $recommendations[] = [
            'recommended_type' => 'probation',
            'recommended_duration' => 6,
            'confidence' => 'low',
            'reasons' => ['No match pendidikan-role (Score: ' . $match['score'] . '). Probation 6 bulan untuk evaluasi.'],
            'success_probability' => 45
        ];
    }
    
    // Adjustment based on risk factors
    if ($risk['score'] > 0.7) {
        // High risk - reduce duration and probability
        $recommendations[0]['recommended_duration'] = max(6, $recommendations[0]['recommended_duration'] - 6);
        $recommendations[0]['success_probability'] -= 15;
        $recommendations[0]['reasons'][] = 'Risiko resign tinggi - durasi dikurangi untuk mitigasi.';
    }
    
    // Adjustment based on education level
    if ($employee['education_level'] === 'S2' && $match['score'] >= 2) {
        $recommendations[0]['recommended_duration'] += 6;
        $recommendations[0]['success_probability'] += 10;
        $recommendations[0]['reasons'][] = 'Bonus S2 education - durasi ditambah.';
    }
    
    // Permanent eligibility (after 2+ successful contracts with good match)
    if ($history['completed_contracts'] >= 2 && $match['score'] >= 2 && $success['score'] > 0.8) {
        $recommendations[] = [
            'recommended_type' => 'permanent',
            'recommended_duration' => null,
            'confidence' => 'very_high',
            'reasons' => ['Telah menyelesaikan 2+ kontrak dengan sukses dan match score baik. Layak permanent.'],
            'success_probability' => 90
        ];
    }
    
    // Default recommendation jika tidak ada yang cocok
    if (empty($recommendations)) {
        $duration = $analytics['industry_benchmark']['avg_duration'];
        $recommendations[] = [
            'recommended_type' => 'probation',
            'recommended_duration' => max(6, min(12, $duration)),
            'confidence' => 'medium',
            'reasons' => ['Rekomendasi berdasarkan benchmark industri.'],
            'success_probability' => 60
        ];
    }
    
    return $recommendations;
}

// Backward compatibility function
function calculateEducationRoleMatch($employee) {
    return analyzeEducationRoleMatch($employee);
}



?> 