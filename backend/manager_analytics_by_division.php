<?php
// CORS headers only if not already sent
if (!headers_sent()) {
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
}

// Handle preflight
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

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

try {
    // Database connection
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'contract_rec_db';
    
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Get current user info from session (use correct session key names)
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
        throw new Exception("Manager not found or no division assigned");
    }
    
    $manager = $result->fetch_assoc();
    $manager_division_id = $manager['division_id'];
    
    if (!$manager_division_id) {
        throw new Exception("Manager has no division assigned");
    }
    
    // Get employees from manager's division only
    $stmt = $conn->prepare("
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
        LEFT JOIN contracts c ON e.eid = c.eid 
            AND c.contract_id = (SELECT MAX(contract_id) FROM contracts c2 WHERE c2.eid = e.eid)
        LEFT JOIN recommendations r ON e.eid = r.eid AND r.status = 'pending' AND r.recommended_by = ?
        WHERE e.division_id = ? AND e.status = 'active'
        ORDER BY e.join_date DESC
    ");
    
    $stmt->bind_param("ii", $_SESSION['user_id'], $manager_division_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate education-job match (simplified algorithm)
        $education_match = analyzeEducationRoleMatch($row);
        
        // Calculate success probability based on tenure and education match
        $success_probability = calculateManagerSuccessProbability($row['tenure_months'], $education_match['score']);
        
        // Determine risk level
        $risk_level = determineRiskLevel($success_probability, $row['tenure_months']);
        
        // Use Data Intelligence untuk contract recommendation with error handling
        try {
            $employee_for_intelligence = [
                'eid' => $row['eid'],
                'major' => $row['major'],
                'role' => $row['role'],
                'education_level' => $row['education_level'],
                'current_contract_type' => $row['current_contract_type'],
                'tenure_months' => $row['tenure_months']
            ];
            
            // Include Data Intelligence API only once
            if (!function_exists('calculateDynamicIntelligence')) {
                // Capture any output from the include
                ob_start();
                require_once __DIR__ . '/api/dynamic_intelligence.php';
                ob_end_clean(); // Discard any output from the include
            }
            
            // Capture any output from the function call
            ob_start();
            $intelligence_data = calculateDynamicIntelligence($conn, $employee_for_intelligence);
            ob_end_clean(); // Discard any output from the function
            
            // FIX: Use consistent contract recommendation logic instead of dynamic intelligence match_category
            // Generate contract recommendation based on education match score (same as employee detail)
            if ($education_match['score'] >= 3) {
                // Perfect Match - Kontrak 1 (12 months)
                $contract_type = 'Kontrak 1';
                $duration = 12;
                $reason = 'Perfect match pendidikan-role (Score: ' . $education_match['score'] . '). Sangat cocok untuk kontrak 12 bulan.';
            } elseif ($education_match['score'] >= 2) {
                // Good Match - Kontrak 1 (6 months)
                $contract_type = 'Kontrak 1';
                $duration = 6;
                $reason = 'Good match pendidikan-role (Score: ' . $education_match['score'] . '). Cocok untuk kontrak 6 bulan.';
            } else {
                // Basic/No Match - Probation (6 months)
                $contract_type = 'Probation';
                $duration = 6;
                $reason = 'Basic/No match pendidikan-role (Score: ' . $education_match['score'] . '). Probation 6 bulan untuk evaluasi.';
            }
            
            // Convert to old format untuk compatibility
            $contract_recommendation = [
                'recommended_duration' => $duration,
                'risk_level' => 'Medium', // Default
                'success_probability' => $success_probability,
                'category' => $contract_type, // FIX: Use consistent contract type
                'confidence_level' => $intelligence_data['confidence_level'] ?? 'Medium',
                'reasoning' => [$reason],
                'reason' => $reason
            ];
        } catch (Exception $e) {
            // Fallback to old logic if Data Intelligence fails
        $contract_recommendation = getContractRecommendation($row['tenure_months'], $success_probability, $row['current_contract_type']);
        }
        
        // Determine recommendation status for display
        $recommendation_status = 'none';
        
        // Check if employee can be recommended
        $can_be_recommended = true;
        $not_recommended_reason = '';
        
        // Rule 1: Permanent contract employees cannot be recommended
        if ($row['current_contract_type'] === 'permanent') {
            $can_be_recommended = false;
            $not_recommended_reason = 'Sudah Permanent';
            $recommendation_status = 'permanent_not_eligible';
        }
        // Normal recommendation status logic (Unmatch can still be recommended but with "terminate" option)
        else {
        if ($row['pending_recommendations'] > 0) {
            $recommendation_status = 'pending';
        } elseif ($row['latest_processed_status'] === 'approved') {
            $recommendation_status = 'processed_approved';
        } elseif ($row['latest_processed_status'] === 'rejected') {
            $recommendation_status = 'processed_rejected';
            }
        }

        $employees[] = [
            'eid' => $row['eid'],
            'name' => $row['name'],
            'major' => $row['major'],
            'role' => $row['role'],
            'education_level' => $row['education_level'],
            'division_name' => $row['division_name'],
            'tenure_months' => round($row['tenure_months']),
            'education_job_match' => $education_match['match_status'],
            'success_probability' => $success_probability,
            'risk_level' => $risk_level,
            'match_level' => $education_match['level'],
            'contract_status' => $row['current_contract_type'] ?: 'No Contract',
            'current_contract_type' => $row['current_contract_type'],
            'contract_end' => $row['contract_end_date'],
            'days_to_contract_end' => $row['days_to_contract_end'],
            'has_pending_recommendation' => (bool)$row['has_pending_recommendation'],
            'join_date' => $row['join_date'],
            'data_mining_recommendation' => $contract_recommendation,
            'recommendation_stats' => [
                'total' => (int)$row['total_recommendations'],
                'approved' => (int)$row['approved_recommendations'],
                'pending' => (int)$row['pending_recommendations'],
                'latest_status' => $row['latest_processed_status'],
                'display_status' => $recommendation_status,
                'can_be_recommended' => $can_be_recommended,
                'not_recommended_reason' => $not_recommended_reason
            ]
        ];
    }
    
    // Calculate division statistics
    $total_employees = count($employees);
    $match_count = count(array_filter($employees, function($emp) { return $emp['education_job_match'] === 'Match'; }));
    $unmatch_count = count(array_filter($employees, function($emp) { return $emp['education_job_match'] === 'Unmatch'; }));
    $high_risk_count = count(array_filter($employees, function($emp) { return $emp['risk_level'] === 'High Risk'; }));
    
    // Get division name
    $stmt = $conn->prepare("SELECT division_name FROM divisions WHERE division_id = ?");
    $stmt->bind_param("i", $manager_division_id);
    $stmt->execute();
    $division_result = $stmt->get_result();
    $division_name = $division_result->fetch_assoc()['division_name'] ?? 'Unknown Division';
    
    $response = [
        'success' => true,
        'data' => [
            'employees' => $employees,
            'division_stats' => [
                'total_employees' => $total_employees,
                'division_name' => $division_name,
                'match_count' => $match_count,
                'unmatch_count' => $unmatch_count,
                'match_percentage' => $total_employees > 0 ? round(($match_count / $total_employees) * 100, 1) : 0,
                'contract_extensions' => 0,
                'high_risk_count' => $high_risk_count,
                'contract_summary' => [
                    'probation_ready' => count(array_filter($employees, function($emp) { 
                        return $emp['tenure_months'] >= 3 && $emp['contract_status'] === 'probation'; 
                    })),
                    'contracts_ending' => count(array_filter($employees, function($emp) { 
                        return $emp['tenure_months'] >= 12; 
                    })),
                    'permanent_eligible' => count(array_filter($employees, function($emp) { 
                        return $emp['success_probability'] >= 70; 
                    }))
                ],
                'data_mining_summary' => [
                    'match_employees' => $match_count,
                    'unmatch_employees' => $unmatch_count,
                    'match_percentage' => $total_employees > 0 ? round(($match_count / $total_employees) * 100, 1) : 0,
                    'recommended_extensions' => count(array_filter($employees, function($emp) { 
                        return $emp['data_mining_recommendation']['recommended_duration'] > 12; 
                    })),
                    'high_risk_employees' => $high_risk_count
                ]
            ]
        ]
    ];
    
    echo json_encode($response);
    
    // Add cache-busting headers to ensure fresh data
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('Pragma: no-cache');

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Helper functions
// NOTE: calculateEducationMatch has been replaced with analyzeEducationRoleMatch 
// for consistency with the detailed employee view and better accuracy

function calculateManagerSuccessProbability($tenure_months, $education_match_score) {
    $base_probability = 50;
    
    // Tenure factor (longer tenure = higher success probability)
    if ($tenure_months >= 24) $base_probability += 20;
    elseif ($tenure_months >= 12) $base_probability += 15;
    elseif ($tenure_months >= 6) $base_probability += 10;
    elseif ($tenure_months >= 3) $base_probability += 5;
    
    // Education match factor
    if ($education_match_score >= 2) { // Assuming 2 is a good match
        $base_probability += 15;
    } else {
        $base_probability -= 5;
    }
    
    return min($base_probability, 100);
}

function determineRiskLevel($success_probability, $tenure_months) {
    if ($success_probability >= 75) return 'Low Risk';
    elseif ($success_probability >= 60) return 'Medium Risk';
    else return 'High Risk';
}

function analyzeResignBeforeContractEndForManager($db, $major, $role, $education_level, $target_duration_months) {
    try {
        // Query yang sama seperti di employees.php untuk konsistensi
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
        
        return [
            'target_duration_months' => $target_duration_months,
            'early_resign_rate' => round($early_resign_rate, 1),
            'total_early_resigns' => $total_early_resigns,
            'total_similar_contracts' => $total_contracts,
            'sample_size' => $total_contracts,
            'risk_level' => $early_resign_rate > 30 ? 'High' : 
                          ($early_resign_rate > 15 ? 'Medium' : 'Low')
        ];
        
    } catch (Exception $e) {
        return [
            'target_duration_months' => $target_duration_months,
            'early_resign_rate' => 0,
            'total_early_resigns' => 0,
            'total_similar_contracts' => 0,
            'sample_size' => 0,
            'risk_level' => 'Unknown'
        ];
    }
}

function getContractRecommendation($tenure_months, $success_probability, $current_contract) {
    $recommended_duration = 12; // Default
    
    if ($success_probability >= 80) {
        $recommended_duration = 24;
        $risk_level = 'Low';
    } elseif ($success_probability >= 65) {
        $recommended_duration = 18;
        $risk_level = 'Medium';
    } else {
        $recommended_duration = 12;
        $risk_level = 'High';
    }
    
    return [
        'recommended_duration' => $recommended_duration,
        'risk_level' => $risk_level,
        'success_probability' => $success_probability,
        'reason' => $success_probability >= 75 ? 'High performance and good education match' : 
                   ($success_probability >= 60 ? 'Moderate performance, needs monitoring' : 
                    'Performance concerns, short-term contract recommended')
    ];
}

function getIntelligentContractRecommendation($db, $employee_data) {
    $tenure_months = $employee_data['tenure_months'] ?? 0;
    $success_probability = $employee_data['success_probability'] ?? 50;
    $current_contract = $employee_data['current_contract_type'] ?? 'probation';
    
    // Base recommendation
    $base_recommendation = getContractRecommendation($tenure_months, $success_probability, $current_contract);
    $recommended_duration = $base_recommendation['recommended_duration'];
    
    // NEW: Analisis resign sebelum kontrak habis
    $major = $employee_data['major'] ?? '';
    $role = $employee_data['role'] ?? '';
    $education_level = $employee_data['education_level'] ?? '';
    
    if (!empty($major) && !empty($role) && !empty($education_level)) {
        $resign_analysis = analyzeResignBeforeContractEndForManager($db, $major, $role, $education_level, $recommended_duration);
        
        // Adjust recommendation berdasarkan resign intelligence
        if ($resign_analysis['sample_size'] >= 5) {
            if ($resign_analysis['early_resign_rate'] > 30) {
                // High risk: turunkan durasi
                $recommended_duration = max(6, $recommended_duration - 6);
                $base_recommendation['risk_level'] = 'High';
                $base_recommendation['reason'] = sprintf(
                    'Durasi dikurangi karena %.1f%% karyawan serupa resign sebelum habis kontrak %d bulan',
                    $resign_analysis['early_resign_rate'],
                    $base_recommendation['recommended_duration']
                );
            } elseif ($resign_analysis['early_resign_rate'] > 15) {
                // Medium risk: pertahankan durasi tapi tambahkan warning
                $base_recommendation['reason'] .= sprintf(
                    '. Perhatian: %.1f%% resign rate sebelum habis kontrak',
                    $resign_analysis['early_resign_rate']
                );
            } else {
                // Low risk: data positif
                $base_recommendation['reason'] .= sprintf(
                    '. Data positif: hanya %.1f%% resign rate sebelum habis kontrak',
                    $resign_analysis['early_resign_rate']
                );
            }
            
            $base_recommendation['resign_intelligence'] = $resign_analysis;
        }
        
        $base_recommendation['recommended_duration'] = $recommended_duration;
    }
    
    return $base_recommendation;
}

function analyzeEducationRoleMatch($employee) {
    // UNIFIED COMPREHENSIVE EDUCATION KEYWORDS - matching ALL other files
    $it_education_keywords = [
        'INFORMATION TECHNOLOGY', 'TEKNIK INFORMATIKA', 'ILMU KOMPUTER', 'SISTEM INFORMASI',
        'COMPUTER SCIENCE', 'SOFTWARE ENGINEERING', 'INFORMATIKA', 'TEKNIK KOMPUTER',
        'KOMPUTER', 'TEKNOLOGI INFORMASI', 'INFORMATION SYSTEM', 'INFORMATICS ENGINEERING',
        'INFORMATIC ENGINEERING', 'COMPUTER ENGINEERING', 'INFORMATION MANAGEMENT',
        'INFORMATICS MANAGEMENT', 'MANAGEMENT INFORMATIKA', 'SISTEM KOMPUTER',
        'KOMPUTERISASI AKUNTANASI', 'COMPUTERIZED ACCOUNTING', 'COMPUTATIONAL SCIENCE',
        'INFORMATICS', 'INFORMATICS TECHNOLOGY', 'COMPUTER AND INFORMATICS ENGINEERING',
        'ENGINEERING INFORMATIC', 'INDUSTRIAL ENGINEERING_INFORMATIC',
        'STATISTICS', 'TECHNOLOGY MANAGEMENT', 'ELECTRICAL ENGINEERING', 'ELECTRONICS ENGINEERING',
        'MASTER IN INFORMATICS', 'ICT', 'COMPUTER SCIENCE & ELECTRONICS', 'COMPUTER TELECOMMUNICATION',
        'TELECOMMUNICATION ENGINEERING', 'TELECOMMUNICATIONS ENGINEERING', 'TELECOMMUNICATION & MEDIA',
        'TEKNIK ELEKTRO', 'COMPUTER SCINCE', 'COMPUTER SCIENCES & ENGINEERING', 'COMPUTER SYSTEM',
        'COMPUTER SIENCE', 'COMPUTER TECHNOLOGY', 'INFORMASTION SYSTEM',
        'INFORMATICS TECHNIQUE', 'INFORMATIOCS', 'INFORMATICS TECHNOLOGY', 'TEKNIK KOMPUTER & JARINGAN',
        'INFORMATION ENGINEERING', 'SOFTWARE ENGINEERING TECHNOLOGY', 'DIGITAL MEDIA TECHNOLOGY',
        'INFORMATIC ENGINEETING', 'INFORMATION SYTEM', 'MATERIALS ENGINEERING',
        'NETWORK MANAGEMENT', 'INFORMATICS SYSTEM', 'BUSINESS INFORMATION SYSTEM',
        'PHYSICS ENGINEERING', 'ELECTRICAL ENGINEERING_ELECTRONICS', 'ELECTRONIC ENGINEERING',
        'ELECTRONICS & COMPUTER ENGINEERING', 'TELECOMMUNICATION ENGINEERING_ELECTRONICS',
        // CRITICAL FIX: Add missing simple keywords that should be included
        'INFORMATION', 'TECHNOLOGY', 'TECHONOLOGY', 'SYSTEM', 'SISTEM',
        // Additional typo variants found in CSV data
        'TECHONOLOGY INFORMATION', 'TECHNOLOGY INFORMATION', 'INFORMATION TECHONOLOGY',
        // Add more comprehensive education patterns
        'COMPUTER', 'KOMPUTER', 'PROGRAMMING', 'CODING', 'SCIENCE', 'MATH', 'MATHEMATICS', 
        'DATA', 'NETWORK', 'DIGITAL', 'CYBER', 'ELEKTRONIK', 'ELECTRONIC', 'TELEKOMUNIKASI', 'TELECOMMUNICATION'
    ];
    
    // UNIFIED COMPREHENSIVE ROLE KEYWORDS - matching ALL other files
    $it_job_keywords = [
        'DEVELOPER', 'PROGRAMMER', 'SOFTWARE', 'TECHNICAL', 'FRONTEND', 'BACKEND', 
        'FULLSTACK', 'FULL STACK', 'JAVA', 'NET', '.NET', 'API', 'ETL', 'MOBILE',
        'ANDROID', 'WEB', 'UI/UX', 'FRONT END', 'BACK END', 'PEGA', 'RPA',
        'ANALYST', 'BUSINESS ANALYST', 'SYSTEM ANALYST', 'DATA ANALYST', 'DATA SCIENTIST',
        'QUALITY ASSURANCE', 'QA', 'TESTER', 'TEST ENGINEER', 'CONSULTANT',
        'IT CONSULTANT', 'TECHNOLOGY CONSULTANT', 'TECHNICAL CONSULTANT',
        'PRODUCT OWNER', 'SCRUM MASTER', 'PMO', 'IT OPERATION', 'DEVOPS',
        'SYSTEM ADMINISTRATOR', 'DATABASE ADMINISTRATOR', 'NETWORK',
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
        'APPRENTICE TESTER', 'JR TESTER LEVEL 1', 'LEAD QA', 'LEAD BACKEND DEVELOPER', 'MANUAL',
        // Add basic keywords for broader matching
        'ENGINEER', 'ADMIN', 'DESIGNER', 'MANAGER', 'COORDINATOR', 'SPECIALIST', 'TECHNICIAN',
        'ARCHITECT', 'LEAD', 'SENIOR', 'JUNIOR'
    ];

    $major = strtoupper($employee['major'] ?? '');
    $role = strtoupper($employee['role'] ?? '');
    $designation = strtoupper($employee['designation'] ?? '');
    
    // Check if education is IT-related
    $education_match = false;
    foreach ($it_education_keywords as $keyword) {
        if (strpos($major, $keyword) !== false) {
            $education_match = true;
            break;
        }
    }
    
    // Check if role is IT-related
    $role_match = false;
    foreach ($it_job_keywords as $keyword) {
        if (strpos($role, $keyword) !== false || strpos($designation, $keyword) !== false) {
            $role_match = true;
            break;
        }
    }
    
    // UNIFIED SCORING LOGIC - match manager_analytics_simple.php exactly
    $score = 0;
    if ($education_match && $role_match) {
        $score = 3; // Perfect match
        $match_status = 'Match';
        $level = 'High';
        $category = 'Perfect Match';
    } elseif ($education_match || $role_match) {
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
        'education_match' => $education_match,
        'role_match' => $role_match
    ];
}
?> 