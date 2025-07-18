<?php
// CORS headers for non-credentials mode
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
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
    
    // For now, let's filter by a specific division (IT FrontEnd - division_id = 10)
    // In production, this should come from the manager's session
    $manager_division_id = 10; // IT FrontEnd division
    
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
            END as days_to_contract_end
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
        ORDER BY e.join_date DESC
    ");
    
    $stmt->bind_param("i", $manager_division_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate education-job match using UNIFIED algorithm
        $education_match_result = analyzeEducationRoleMatch($row);
        
        // Calculate success probability based on tenure and education match
        $success_probability = calculateSimpleSuccessProbability($row['tenure_months'], $education_match_result['score']);
        
        // Determine risk level
        $risk_level = determineRiskLevel($success_probability, $row['tenure_months']);
        
        // Determine contract recommendation
        $contract_recommendation = getContractRecommendation($row['tenure_months'], $success_probability, $row['current_contract_type']);
        
        $employees[] = [
            'name' => $row['name'],
            'major' => $row['major'],
            'role' => $row['role'],
            'education_level' => $row['education_level'],
            'tenure_months' => round($row['tenure_months']),
            'education_job_match' => $education_match_result['match_status'],
            'success_probability' => $success_probability,
            'risk_level' => $risk_level,
            'match_level' => $education_match_result['level'],
            'data_mining_recommendation' => $contract_recommendation
        ];
    }
    
    // Calculate division statistics
    $total_employees = count($employees);
    $avg_match_score = $total_employees > 0 ? round(array_sum(array_column($employees, 'education_job_match')) / $total_employees) : 0;
    $high_match_count = count(array_filter($employees, function($emp) { return $emp['education_job_match'] >= 70; }));
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
                'avg_match_score' => $avg_match_score,
                'high_match_count' => $high_match_count,
                'contract_extensions' => 0,
                'high_risk_count' => $high_risk_count,
                'contract_summary' => [
                    'probation_ready' => 0,
                    'contracts_ending' => 0,
                    'permanent_eligible' => $high_match_count
                ],
                'data_mining_summary' => [
                    'avg_education_match' => $avg_match_score,
                    'high_match_employees' => $high_match_count,
                    'recommended_extensions' => count(array_filter($employees, function($emp) { 
                        return $emp['data_mining_recommendation']['recommended_duration'] >= 18; 
                    })),
                    'high_risk_employees' => $high_risk_count
                ]
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Helper functions
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
    
    // UNIFIED SCORING LOGIC - match all other files exactly
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

function calculateSimpleSuccessProbability($tenure_months, $education_match) {
    $base_probability = 50;
    
    // Tenure factor
    if ($tenure_months >= 24) $base_probability += 20;
    elseif ($tenure_months >= 12) $base_probability += 15;
    elseif ($tenure_months >= 6) $base_probability += 10;
    elseif ($tenure_months >= 3) $base_probability += 5;
    
    // Education match factor
    if ($education_match >= 80) $base_probability += 15;
    elseif ($education_match >= 70) $base_probability += 10;
    elseif ($education_match >= 60) $base_probability += 5;
    elseif ($education_match < 40) $base_probability -= 10;
    
    return min($base_probability, 100);
}

function determineRiskLevel($success_probability, $tenure_months) {
    if ($success_probability >= 75) return 'Low Risk';
    elseif ($success_probability >= 60) return 'Medium Risk';
    else return 'High Risk';
}

function getContractRecommendation($tenure_months, $success_probability, $current_contract) {
    $recommended_duration = 12;
    
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
        'success_probability' => $success_probability
    ];
}
?> 