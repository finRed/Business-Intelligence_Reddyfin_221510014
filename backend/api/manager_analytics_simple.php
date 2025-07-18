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
    
    // Include education analysis function for consistency
    function analyzeEducationRoleMatch($employee) {
        // UNIFIED COMPREHENSIVE EDUCATION KEYWORDS - matching employees.php
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
        
        // UNIFIED COMPREHENSIVE ROLE KEYWORDS - matching employees.php
        $it_role_keywords = [
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
        
        $education = strtoupper($employee['major'] ?? '');
        $role = strtoupper($employee['role'] ?? '');
        
        // Check education match
        $education_match = false;
        foreach ($it_education_keywords as $keyword) {
            if (strpos($education, $keyword) !== false) {
                $education_match = true;
                break;
            }
        }
        
        // Check role match
        $role_match = false;
        foreach ($it_role_keywords as $keyword) {
            if (strpos($role, $keyword) !== false) {
                $role_match = true;
                break;
            }
        }
        
        // Calculate match score - SAME LOGIC AS EMPLOYEES.PHP
        $score = 0;
        if ($education_match && $role_match) {
            $score = 3; // Perfect match
        } elseif ($education_match || $role_match) {
            $score = 2; // Good match
        } else {
            $score = 1; // Basic match
        }
        
        return [
            'score' => $score,
            'education_match' => $education_match,
            'role_match' => $role_match,
        ];
    }
    
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
    
    // Get employees from manager's division only with pending recommendation check
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
            c.status as contract_status,
            CASE 
                WHEN c.end_date IS NULL THEN NULL
                ELSE DATEDIFF(c.end_date, CURDATE())
            END as days_to_contract_end,
            CASE 
                WHEN r.recommendation_id IS NOT NULL THEN 1
                ELSE 0
            END as has_pending_recommendation
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
        LEFT JOIN recommendations r ON e.eid = r.eid AND r.status = 'pending' AND r.recommended_by = ?
        WHERE e.division_id = ? AND e.status = 'active'
        ORDER BY e.join_date DESC
    ";
    
    $stmt = $conn->prepare($employees_sql);
    $stmt->bind_param("ii", $manager_user_id, $manager_division_id);
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
            'contract_status' => $row['contract_status'],
            'days_to_contract_end' => $row['days_to_contract_end'],
            'join_date' => $row['join_date'],
            'status' => $row['status'],
            'has_pending_recommendation' => (bool)$row['has_pending_recommendation']
        ];
        
        // Consistent logic with employees.php - education-based classification
        $education_match = analyzeEducationRoleMatch($row);
        
        // IMPROVED contract progression logic - consider current contract and tenure
        $current_contract = $row['current_contract_type'];
        $tenure_months = $row['tenure_months'];
        $education_score = $education_match['score'];
        
        // Determine recommendation based on current contract, tenure, and education match
        if ($current_contract === 'probation') {
            // From Probation
            if ($education_score >= 3 && $tenure_months >= 6) {
                // Perfect match + good tenure -> Kontrak 2 (12 bulan)
                $match_category = 'Kontrak 2';
                $recommended_duration = 12;
            } elseif ($education_score >= 2) {
                // Good match -> Kontrak 2 (6 bulan)
                $match_category = 'Kontrak 2';
                $recommended_duration = 6;
            } else {
                // Poor match -> Kontrak 1 (evaluasi)
                $match_category = 'Kontrak 1 (Evaluasi)';
                $recommended_duration = 6;
            }
        } elseif ($current_contract === '1' || $current_contract === 'kontrak1') {
            // From Kontrak 1
            if ($education_score >= 3 && $tenure_months >= 12) {
                // Perfect match + good tenure -> Kontrak 3
                $match_category = 'Kontrak 3';
                $recommended_duration = 18;
            } elseif ($education_score >= 2) {
                // Good match -> Kontrak 2
                $match_category = 'Kontrak 2';
                $recommended_duration = 12;
            } else {
                // Poor match -> Kontrak 1 lagi (evaluasi)
                $match_category = 'Kontrak 1 (Evaluasi)';
                $recommended_duration = 6;
            }
        } elseif ($current_contract === '2' || $current_contract === 'kontrak2') {
            // From Kontrak 2
            if ($education_score >= 3 && $tenure_months >= 24) {
                // Perfect match + excellent tenure -> Permanent
                $match_category = 'Permanent';
                $recommended_duration = 0;
            } elseif ($education_score >= 2 && $tenure_months >= 18) {
                // Good match + good tenure -> Kontrak 3
                $match_category = 'Kontrak 3';
                $recommended_duration = 24;
            } else {
                // Keep in Kontrak 2
                $match_category = 'Kontrak 2';
                $recommended_duration = 12;
            }
        } elseif ($current_contract === '3' || $current_contract === 'kontrak3') {
            // From Kontrak 3
            if ($education_score >= 3 && $tenure_months >= 36) {
                // Perfect match + excellent tenure -> Permanent
                $match_category = 'Permanent';
                $recommended_duration = 0;
            } else {
                // Keep in Kontrak 3
                $match_category = 'Kontrak 3';
                $recommended_duration = 24;
            }
        } elseif ($current_contract === 'permanent') {
            // Already permanent
            $match_category = 'Permanent';
            $recommended_duration = 0;
        } else {
            // No contract or other cases
            if ($education_score >= 2) {
                $match_category = 'Probation';
                $recommended_duration = 3;
            } else {
                $match_category = 'Evaluasi';
                $recommended_duration = 3;
            }
        }
        
        // Count for statistics
        if ($education_score >= 2) {
            $match_count++;
        } else {
            $extension_count++;
        }
        
        // Add education_job_match field for table compatibility
        $employee['education_job_match'] = $education_match['score'] >= 2 ? 'Match' : 'Unmatch';
        
        // Calculate risk level based on multiple factors
        $risk_score = 0;
        
        // Education mismatch increases risk
        if ($education_score < 2) {
            $risk_score += 0.4;
        } elseif ($education_score < 3) {
            $risk_score += 0.2;
        }
        
        // Low tenure increases risk
        if ($tenure_months < 6) {
            $risk_score += 0.3;
        } elseif ($tenure_months < 12) {
            $risk_score += 0.2;
        }
        
        // Contract type affects risk
        if ($current_contract === 'probation') {
            $risk_score += 0.1;
        }
        
        // Determine risk level
        if ($risk_score >= 0.6) {
            $risk_level = 'High';
            $high_risk_count++;
        } elseif ($risk_score >= 0.3) {
            $risk_level = 'Medium';
        } else {
            $risk_level = 'Low';
        }
        
        // Calculate success probability
        $success_probability = max(30, min(95, 85 - ($risk_score * 50)));
        
        $employee['intelligence_data'] = [
            'match_category' => $match_category,
            'recommended_duration' => $recommended_duration,
            'education_match' => $education_match['education_match'] ? 'Sesuai' : 'Tidak Sesuai',
            'education_score' => $education_match['score'],
            'risk_level' => $risk_level,
            'success_probability' => round($success_probability)
        ];
        
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