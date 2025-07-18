<?php
session_start();
require_once '../config/db.php';

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'];

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
            if ($action === 'recommendation' && isset($_GET['eid'])) {
                getContractRecommendation($db, $_GET['eid']);
            } elseif ($action === 'history' && isset($_GET['eid'])) {
                getContractHistory($db, $_GET['eid']);
            } else {
                getContracts($db);
            }
        } else {
            getContracts($db);
        }
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['action'])) {
            if ($input['action'] === 'create') {
                createContract($db, $input);
            } elseif ($input['action'] === 'extend') {
                extendContract($db, $input);
            }
        } else {
            // Direct contract creation without action parameter
            createContractDirect($db, $input);
        }
        break;
        
    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        updateContract($db, $input);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function getContracts($db) {
    try {
        $role = $_SESSION['role'];
        $division_id = $_SESSION['division_id'];
        
        if ($role === 'admin' || $role === 'hr') {
            // Admin and HR can see all contracts
            $sql = "SELECT c.*, e.name as employee_name, e.role, d.division_name
                    FROM contracts c 
                    JOIN employees e ON c.eid = e.eid
                    LEFT JOIN divisions d ON e.division_id = d.division_id
                    ORDER BY c.created_at DESC";
            $result = $db->query($sql);
        } else {
            // Manager can only see contracts in their division
            $sql = "SELECT c.*, e.name as employee_name, e.role, d.division_name
                    FROM contracts c 
                    JOIN employees e ON c.eid = e.eid
                    LEFT JOIN divisions d ON e.division_id = d.division_id
                    WHERE e.division_id = ?
                    ORDER BY c.created_at DESC";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('i', $division_id);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        $contracts = [];
        while ($row = $result->fetch_assoc()) {
            $contracts[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $contracts
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getContractHistory($db, $eid) {
    try {
        $stmt = $db->prepare("SELECT c.*, ch.action, ch.old_end_date, ch.new_end_date, ch.reason, u.username as created_by_name
                             FROM contracts c 
                             LEFT JOIN contract_history ch ON c.contract_id = ch.contract_id
                             LEFT JOIN users u ON ch.created_by = u.user_id
                             WHERE c.eid = ? 
                             ORDER BY c.start_date DESC, ch.created_at DESC");
        $stmt->bind_param('i', $eid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $history
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getContractRecommendation($db, $eid) {
    try {
        // Get employee data
        $stmt = $db->prepare("SELECT e.*, d.division_name FROM employees e 
                             LEFT JOIN divisions d ON e.division_id = d.division_id 
                             WHERE e.eid = ?");
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
        $recommendation = generateSmartRecommendation($db, $employee, $contracts);
        
        echo json_encode([
            'success' => true,
            'employee' => $employee,
            'contracts' => $contracts,
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

function generateSmartRecommendation($db, $employee, $contracts) {
    $recommendation = [
        'recommended_contract' => 'Probation',
        'recommended_duration' => 6,
        'confidence_level' => 'Medium',
        'reasons' => [],
        'risk_factors' => [],
        'success_probability' => 75
    ];
    
    // Check for Non-IT majors - automatic elimination
    $nonItMajors = [
        'kedokteran', 'farmasi', 'hukum', 'akuntansi', 'manajemen', 'ekonomi',
        'psikologi', 'bahasa', 'sastra', 'pendidikan', 'sejarah', 'geografi',
        'biologi', 'kimia', 'fisika', 'matematika', 'pertanian', 'kehutanan',
        'perikanan', 'kedokteran hewan', 'arsitektur', 'sipil', 'mesin',
        'elektro', 'industri', 'teknik', 'seni', 'desain', 'komunikasi',
        'jurnalistik', 'sosiologi', 'antropologi', 'ilmu politik',
        'hubungan internasional', 'administrasi', 'keperawatan', 'kebidanan',
        'gizi', 'kesehatan masyarakat', 'olahraga', 'keolahragaan'
    ];
    
    $employeeMajor = strtolower(trim($employee['major']));
    $isNonIT = false;
    
    // Check if employee major contains any non-IT keywords
    foreach ($nonItMajors as $nonItMajor) {
        if (strpos($employeeMajor, $nonItMajor) !== false) {
            $isNonIT = true;
            break;
        }
    }
    
    // If Non-IT major detected, eliminate immediately
    if ($isNonIT) {
        return [
            'recommended_contract' => 'Tidak Direkomendasikan',
            'recommended_duration' => 0,
            'confidence_level' => 'Very Low',
            'reasons' => [],
            'risk_factors' => [
                'Latar belakang pendidikan Non-IT tidak sesuai untuk posisi teknologi',
                'Jurusan ' . $employee['major'] . ' tidak relevan dengan kebutuhan perusahaan IT'
            ],
            'success_probability' => 0,
            'elimination_reason' => 'Non-IT Education Background'
        ];
    }
    
    // Check current contract status
    $current_contract = null;
    $completed_contracts = 0;
    $total_contract_months = 0;
    
    foreach ($contracts as $contract) {
        if ($contract['status'] === 'active') {
            $current_contract = $contract;
        }
        if ($contract['status'] === 'completed') {
            $completed_contracts++;
            // Calculate contract duration
            $start = new DateTime($contract['start_date']);
            $end = new DateTime($contract['end_date']);
            $total_contract_months += $start->diff($end)->m + ($start->diff($end)->y * 12);
        }
    }
    
    // Get industry statistics for similar roles
    $industry_stats = getIndustryStatistics($db, $employee['role'], $employee['education_level']);
    
    // Business Logic for Contract Recommendations
    if (!$current_contract) {
        // New employee
        $recommendation['recommended_contract'] = 'Probation';
        $recommendation['recommended_duration'] = 6;
        $recommendation['reasons'][] = 'Karyawan baru memerlukan masa percobaan';
    } else {
        switch ($current_contract['type']) {
            case 'probation':
                if ($industry_stats['probation_success_rate'] > 70) {
                    $recommendation['recommended_contract'] = 'Kontrak 2';
                    $recommendation['recommended_duration'] = 6;
                    $recommendation['confidence_level'] = 'High';
                    $recommendation['success_probability'] = $industry_stats['probation_success_rate'];
                    $recommendation['reasons'][] = 'Historical data supports progression from probation for this role';
                } else {
                    $recommendation['recommended_contract'] = 'Kontrak 2 (Evaluasi)';
                    $recommendation['recommended_duration'] = 6;
                    $recommendation['confidence_level'] = 'Low';
                    $recommendation['success_probability'] = 50;
                    $recommendation['reasons'][] = 'Memerlukan evaluasi mendalam selama kontrak';
                    $recommendation['risk_factors'][] = 'Perlu pertimbangan khusus dan monitoring ketat';
                }
                break;
                
            case '1':
                if ($industry_stats['contract_1_success_rate'] > 60) {
                    $recommendation['recommended_contract'] = 'Kontrak 2';
                    $recommendation['recommended_duration'] = 12;
                    $recommendation['confidence_level'] = 'High';
                    $recommendation['success_probability'] = $industry_stats['contract_1_success_rate'];
                    $recommendation['reasons'][] = 'Historical data shows positive outcomes for first contract completions';
                } else {
                    $recommendation['recommended_contract'] = 'Kontrak 2 (Evaluasi)';
                    $recommendation['recommended_duration'] = 6;
                    $recommendation['confidence_level'] = 'Medium';
                    $recommendation['success_probability'] = 65;
                    $recommendation['reasons'][] = 'Kontrak 2 dengan evaluasi mendalam diperlukan';
                    $recommendation['risk_factors'][] = 'Memerlukan monitoring dan pertimbangan khusus';
                }
                break;
                
            case '2':
                if ($completed_contracts >= 2 && $industry_stats['contract_2_success_rate'] > 70) {
                    $recommendation['recommended_contract'] = 'Kontrak 3';
                    $recommendation['recommended_duration'] = 12;
                    $recommendation['confidence_level'] = 'High';
                    $recommendation['success_probability'] = 90;
                    $recommendation['reasons'][] = 'Karyawan telah menyelesaikan 2 kontrak dengan baik';
                    $recommendation['reasons'][] = 'Progres ke kontrak 3 untuk evaluasi lanjutan';
                } else {
                    $recommendation['recommended_contract'] = 'Kontrak 3 (Evaluasi)';
                    $recommendation['recommended_duration'] = 12;
                    $recommendation['confidence_level'] = 'Medium';
                    $recommendation['success_probability'] = 75;
                    $recommendation['reasons'][] = 'Kontrak 3 dengan evaluasi mendalam diperlukan';
                    $recommendation['risk_factors'][] = 'Memerlukan pertimbangan khusus sebelum keputusan akhir';
                }
                break;
                
            case '3':
                if ($completed_contracts >= 3) {
                    $recommendation['recommended_contract'] = 'Kontrak 3 (Evaluasi)';
                    $recommendation['recommended_duration'] = 12;
                    $recommendation['confidence_level'] = 'Medium';
                    $recommendation['success_probability'] = 80;
                    $recommendation['reasons'][] = 'Karyawan telah menyelesaikan 3 kontrak, perlu evaluasi untuk keputusan final';
                    $recommendation['risk_factors'][] = 'Memerlukan pertimbangan mendalam untuk keputusan akhir';
                } else {
                    $recommendation['recommended_contract'] = 'Kontrak 3 (Evaluasi)';
                    $recommendation['recommended_duration'] = 6;
                    $recommendation['confidence_level'] = 'Low';
                    $recommendation['success_probability'] = 60;
                    $recommendation['reasons'][] = 'Evaluasi menyeluruh diperlukan sebelum keputusan akhir';
                    $recommendation['risk_factors'][] = 'Belum menyelesaikan kontrak 3 dengan baik, perlu pertimbangan khusus';
                }
                break;
        }
    }
    
    // Additional factors analysis
    
    // Education vs Role matching
    if (strpos(strtolower($employee['major']), strtolower($employee['role'])) !== false) {
        $recommendation['success_probability'] += 10;
        $recommendation['reasons'][] = 'Latar belakang pendidikan sesuai dengan role';
    } else {
        $recommendation['success_probability'] -= 5;
        $recommendation['risk_factors'][] = 'Pendidikan tidak sepenuhnya sesuai dengan role';
    }
    
    // Education level factor
    if ($employee['education_level'] === 'S1') {
        $recommendation['success_probability'] += 5;
        $recommendation['reasons'][] = 'Tingkat pendidikan S1 mendukung performa';
    }
    
    // Tenure analysis
    $join_date = new DateTime($employee['join_date']);
    $now = new DateTime();
    $tenure_months = $join_date->diff($now)->m + ($join_date->diff($now)->y * 12);
    
    if ($tenure_months > 12) {
        $recommendation['success_probability'] += 10;
        $recommendation['reasons'][] = 'Tenure lebih dari 1 tahun menunjukkan komitmen';
    }
    
    if ($tenure_months > 24) {
        $recommendation['success_probability'] += 15;
        $recommendation['reasons'][] = 'Tenure lebih dari 2 tahun menunjukkan loyalitas tinggi';
    }
    
    // Current status check
    if ($employee['status'] !== 'active') {
        $recommendation['success_probability'] -= 30;
        $recommendation['risk_factors'][] = 'Status karyawan tidak aktif';
        
        // Determine the appropriate contract type with (Evaluasi) instead of "Tidak Direkomendasikan"
        if ($current_contract) {
            switch ($current_contract['type']) {
                case 'probation':
                    $recommendation['recommended_contract'] = 'Kontrak 2 (Evaluasi)';
                    $recommendation['recommended_duration'] = 6;
                    break;
                case '1':
                    $recommendation['recommended_contract'] = 'Kontrak 2 (Evaluasi)';
                    $recommendation['recommended_duration'] = 6;
                    break;
                case '2':
                    $recommendation['recommended_contract'] = 'Kontrak 3 (Evaluasi)';
                    $recommendation['recommended_duration'] = 6;
                    break;
                case '3':
                    $recommendation['recommended_contract'] = 'Kontrak 3 (Evaluasi)';
                    $recommendation['recommended_duration'] = 6;
                    break;
                default:
                    $recommendation['recommended_contract'] = 'Probation (Evaluasi)';
                    $recommendation['recommended_duration'] = 6;
                    break;
            }
        } else {
            $recommendation['recommended_contract'] = 'Probation (Evaluasi)';
            $recommendation['recommended_duration'] = 6;
        }
        
        $recommendation['confidence_level'] = 'Very Low';
        $recommendation['reasons'][] = 'Memerlukan evaluasi khusus karena status tidak aktif';
    }
    
    // Cap probability between 0-100
    $recommendation['success_probability'] = max(0, min(100, $recommendation['success_probability']));
    
    // Adjust confidence level based on probability
    if ($recommendation['success_probability'] >= 85) {
        $recommendation['confidence_level'] = 'Very High';
    } elseif ($recommendation['success_probability'] >= 70) {
        $recommendation['confidence_level'] = 'High';
    } elseif ($recommendation['success_probability'] >= 55) {
        $recommendation['confidence_level'] = 'Medium';
    } else {
        $recommendation['confidence_level'] = 'Low';
    }
    
    return $recommendation;
}

function getIndustryStatistics($db, $role, $education_level) {
    $stats = [
        'probation_success_rate' => 75,
        'contract_1_success_rate' => 65,
        'contract_2_success_rate' => 70,
        'total_similar_employees' => 0
    ];
    
    try {
        // Get statistics for similar roles and education
        $sql = "SELECT 
                    COUNT(CASE WHEN c.type = 'probation' AND c.status = 'completed' THEN 1 END) as probation_completed,
                    COUNT(CASE WHEN c.type = 'probation' THEN 1 END) as probation_total,
                    COUNT(CASE WHEN c.type = '1' AND c.status = 'completed' THEN 1 END) as contract_1_completed,
                    COUNT(CASE WHEN c.type = '1' THEN 1 END) as contract_1_total,
                    COUNT(CASE WHEN c.type = '2' AND c.status = 'completed' THEN 1 END) as contract_2_completed,
                    COUNT(CASE WHEN c.type = '2' THEN 1 END) as contract_2_total,
                    COUNT(DISTINCT e.eid) as total_employees
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
            
            $stats['total_similar_employees'] = $data['total_employees'];
            
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

function createContract($db, $data) {
    try {
        // Only HR can create contracts
        if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
            throw new Exception('Hanya HR yang dapat membuat kontrak');
        }
        
        $required_fields = ['eid', 'type', 'start_date'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field $field harus diisi");
            }
        }
        
        // Calculate end date based on contract type
        $end_date = calculateEndDate($data['start_date'], $data['type'], $data['duration'] ?? null);
        
        // Close any active contracts for this employee
        $close_stmt = $db->prepare("UPDATE contracts SET status = 'completed' WHERE eid = ? AND status = 'active'");
        $close_stmt->bind_param('i', $data['eid']);
        $close_stmt->execute();
        
        // Create new contract
        $stmt = $db->prepare("INSERT INTO contracts (eid, type, start_date, end_date, status, salary, notes) VALUES (?, ?, ?, ?, 'active', ?, ?)");
        $stmt->bind_param('isssis', 
            $data['eid'],
            $data['type'],
            $data['start_date'],
            $end_date,
            $data['salary'] ?? null,
            $data['notes'] ?? null
        );
        
        if ($stmt->execute()) {
            $contract_id = $db->lastInsertId();
            
            // Log contract creation
            $history_stmt = $db->prepare("INSERT INTO contract_history (contract_id, action, new_end_date, reason, created_by) VALUES (?, 'created', ?, ?, ?)");
            $history_stmt->bind_param('issi', $contract_id, $end_date, $data['notes'] ?? 'Kontrak baru dibuat', $_SESSION['user_id']);
            $history_stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Kontrak berhasil dibuat',
                'contract_id' => $contract_id
            ]);
        } else {
            throw new Exception('Gagal membuat kontrak');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function createContractDirect($db, $data) {
    try {
        // Allow HR and Manager to create contracts
        if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager') {
            throw new Exception('Tidak memiliki akses untuk membuat kontrak');
        }
        
        // Use eid directly from the data
        $eid = $data['employee_id']; // This should be eid from the frontend
        
        // Verify employee exists
        $emp_stmt = $db->prepare("SELECT eid, name FROM employees WHERE eid = ?");
        $emp_stmt->bind_param('i', $eid);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->get_result();
        
        if ($emp_result->num_rows === 0) {
            throw new Exception('Karyawan tidak ditemukan');
        }
        
        $employee = $emp_result->fetch_assoc();
        
        // Set start date to today if not provided
        $start_date = date('Y-m-d');
        
        // Calculate end date based on contract type and duration
        $end_date = null;
        if ($data['contract_type'] !== 'Permanent') {
            $duration = $data['duration_months'] ?? 3;
            $end_date = calculateEndDate($start_date, $data['contract_type'], $duration);
        }
        
        // Close any active contracts for this employee
        $close_stmt = $db->prepare("UPDATE contracts SET status = 'completed' WHERE eid = ? AND status = 'active'");
        $close_stmt->bind_param('i', $eid);
        $close_stmt->execute();
        
        // Create new contract
        $duration_months = ($data['contract_type'] === 'Permanent') ? null : ($data['duration_months'] ?? 3);
        $stmt = $db->prepare("INSERT INTO contracts (eid, type, start_date, end_date, duration_months, status, notes) VALUES (?, ?, ?, ?, ?, 'active', ?)");
        $stmt->bind_param('isssss', 
            $eid,
            $data['contract_type'],
            $start_date,
            $end_date,
            $duration_months,
            'Kontrak dibuat melalui rekomendasi sistem'
        );
        
        if ($stmt->execute()) {
            $contract_id = $db->lastInsertId();
            
            // Log contract creation
            $history_stmt = $db->prepare("INSERT INTO contract_history (contract_id, action, new_end_date, reason, created_by) VALUES (?, 'created', ?, ?, ?)");
            $history_stmt->bind_param('issi', $contract_id, $end_date, 'Kontrak dibuat melalui rekomendasi sistem', $_SESSION['user_id']);
            $history_stmt->execute();
            
            // Update employee status to active if contract is created
            $update_emp_stmt = $db->prepare("UPDATE employees SET status = 'active' WHERE eid = ?");
            $update_emp_stmt->bind_param('i', $eid);
            $update_emp_stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Kontrak berhasil dibuat',
                'contract_id' => $contract_id
            ]);
        } else {
            throw new Exception('Gagal membuat kontrak');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function extendContract($db, $data) {
    try {
        if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
            throw new Exception('Hanya HR yang dapat memperpanjang kontrak');
        }
        
        if (!isset($data['contract_id']) || !isset($data['new_end_date'])) {
            throw new Exception('Contract ID dan tanggal berakhir baru harus diisi');
        }
        
        // Get current contract
        $stmt = $db->prepare("SELECT * FROM contracts WHERE contract_id = ?");
        $stmt->bind_param('i', $data['contract_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Kontrak tidak ditemukan');
        }
        
        $contract = $result->fetch_assoc();
        $old_end_date = $contract['end_date'];
        
        // Update contract
        $update_stmt = $db->prepare("UPDATE contracts SET end_date = ?, status = 'extended' WHERE contract_id = ?");
        $update_stmt->bind_param('si', $data['new_end_date'], $data['contract_id']);
        
        if ($update_stmt->execute()) {
            // Log extension
            $history_stmt = $db->prepare("INSERT INTO contract_history (contract_id, action, old_end_date, new_end_date, reason, created_by) VALUES (?, 'extended', ?, ?, ?, ?)");
            $history_stmt->bind_param('issssi', 
                $data['contract_id'], 
                $old_end_date, 
                $data['new_end_date'], 
                $data['reason'] ?? 'Perpanjangan kontrak',
                $_SESSION['user_id']
            );
            $history_stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Kontrak berhasil diperpanjang'
            ]);
        } else {
            throw new Exception('Gagal memperpanjang kontrak');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function updateContract($db, $data) {
    try {
        if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
            throw new Exception('Hanya HR yang dapat mengupdate kontrak');
        }
        
        if (!isset($data['contract_id'])) {
            throw new Exception('Contract ID harus diisi');
        }
        
        $sql = "UPDATE contracts SET ";
        $params = [];
        $types = "";
        
        $allowed_fields = ['type', 'start_date', 'end_date', 'status', 'salary', 'notes'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $sql .= "$field = ?, ";
                $params[] = $data[$field];
                $types .= "s";
            }
        }
        
        $sql = rtrim($sql, ", ");
        $sql .= " WHERE contract_id = ?";
        $params[] = $data['contract_id'];
        $types .= "i";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Kontrak berhasil diupdate'
            ]);
        } else {
            throw new Exception('Gagal mengupdate kontrak');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function calculateEndDate($start_date, $contract_type, $duration = null) {
    $start = new DateTime($start_date);
    
    if ($duration) {
        $start->add(new DateInterval("P{$duration}M"));
        return $start->format('Y-m-d');
    }
    
    switch ($contract_type) {
        case 'probation':
            $start->add(new DateInterval('P3M'));
            break;
        case '1':
            $start->add(new DateInterval('P6M'));
            break;
        case '2':
            $start->add(new DateInterval('P12M'));
            break;
        case '3':
            $start->add(new DateInterval('P12M'));
            break;
        case 'permanent':
            return null; // Permanent contracts don't have end dates
        default:
            $start->add(new DateInterval('P3M'));
    }
    
    return $start->format('Y-m-d');
}
?> 