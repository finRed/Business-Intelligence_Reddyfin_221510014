<?php
require_once __DIR__ . '/../config/db.php';

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

// Only execute if accessed directly, not when included
if (basename($_SERVER['PHP_SELF']) === 'dynamic_intelligence.php') {
    // Check if employee ID is provided
    if (!isset($_GET['eid'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Employee ID required'
        ]);
        exit;
    }

    $eid = $_GET['eid'];

    try {
        // Get employee data with latest contract (prioritize active, but show latest if no active)
        $stmt = $conn->prepare("
            SELECT e.*, 
                   DATEDIFF(CURDATE(), e.join_date) / 30 as tenure_months,
                   c.type as current_contract_type,
                   c.start_date as contract_start_date,
                   c.end_date as contract_end_date,
                   c.status as contract_status
            FROM employees e
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
            WHERE e.eid = ?
        ");
        $stmt->bind_param("i", $eid);
        $stmt->execute();
        $result = $stmt->get_result();

        $employee = $result->fetch_assoc();
        if (!$employee) {
            throw new Exception("Employee not found");
        }
        
        // DYNAMIC DATA INTELLIGENCE ANALYSIS
        $intelligence = calculateDynamicIntelligence($conn, $employee);
        
        echo json_encode([
            'success' => true,
            'employee' => $employee,
            'intelligence' => $intelligence
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function calculateDynamicIntelligence($conn, $employee) {
    // *** PRIORITY 1: CHECK FOR NON-IT MAJOR ELIMINATION FIRST ***
    $employeeMajor = strtolower(trim($employee['major'] ?? ''));
    $isNonIT = false;
    
    // First, check if it's a valid IT major (even if contains keywords that might be filtered)
    $validItMajors = [
        'teknik informatika', 'teknik komputer', 'teknik elektro', 'teknik industri',
        'information technology', 'information system', 'computer science', 
        'informatics', 'informatics engineering', 'computer engineering',
        'software engineering', 'system information', 'management informatika'
    ];
    
    $isValidIT = false;
    foreach ($validItMajors as $validMajor) {
        if (strpos($employeeMajor, $validMajor) !== false) {
            $isValidIT = true;
            break;
        }
    }
    
    // Only apply non-IT filter if it's NOT a valid IT major
    if (!$isValidIT) {
        $nonItMajors = [
            'kedokteran', 'farmasi', 'hukum', 'akuntansi', 'manajemen bisnis', 'ekonomi',
            'psikologi', 'bahasa', 'sastra', 'pendidikan', 'sejarah', 'geografi',
            'biologi', 'kimia', 'fisika murni', 'matematika murni', 'pertanian', 'kehutanan',
            'perikanan', 'kedokteran hewan', 'arsitektur', 'sipil', 'mesin',
            'seni', 'desain grafis', 'komunikasi visual', 'jurnalistik', 
            'sosiologi', 'antropologi', 'ilmu politik', 'hubungan internasional', 
            'administrasi negara', 'keperawatan', 'kebidanan', 'gizi', 
            'kesehatan masyarakat', 'olahraga', 'keolahragaan'
        ];
        
        // Check if employee major contains any non-IT keywords
        foreach ($nonItMajors as $nonItMajor) {
            if (strpos($employeeMajor, $nonItMajor) !== false) {
                $isNonIT = true;
                break;
            }
        }
    }
    
    // If Non-IT major detected, eliminate immediately
    if ($isNonIT) {
        return [
            'match_category' => 'Tidak Direkomendasikan',
            'optimal_duration' => 0,
            'confidence_level' => 'Very Low',
            'sample_size' => 0,
            'reasoning' => ["ELIMINASI OTOMATIS: Latar belakang pendidikan Non-IT ({$employee['major']}) tidak sesuai untuk posisi teknologi di perusahaan IT."],
            'historical_patterns' => [
                'total_similar' => 0,
                'success_count' => 0,
                'resign_count' => 0,
                'other_count' => 0,
                'success_rate' => 0,
                'resign_rate' => 0, // Changed from 100 to match eliminated status
                'other_rate' => 0,
                'total_percentage_check' => 0, // Special case for eliminated
                'avg_duration' => 0,
                'profiles' => [],
                'confidence_level' => 'N/A',
                'sample_size' => 0
            ],
            'resign_analysis' => [
                'is_high_risk' => true,
                'analysis_date' => date('Y-m-d H:i:s'),
                'elimination_reason' => 'Non-IT Education Background'
            ]
        ];
    }
    
    // 1. DATA MINING ANALYSIS - Berdasarkan employee_contract_analysis.py
    $education_job_analysis = analyzeEducationJobMatch($employee['major'], $employee['role']);
    $contract_progression_data = getContractProgressionData($conn, $employee);
    
    // 2. HANDLE PERMANENT EMPLOYEES FIRST - No recommendations needed
    if ($contract_progression_data['current_contract'] === 'permanent') {
        return [
            'match_category' => 'Sudah Permanent',
            'optimal_duration' => 0,
            'confidence_level' => 'N/A',
            'sample_size' => 0,
            'reasoning' => ['Karyawan sudah memiliki kontrak permanent'],
            'historical_patterns' => [
                'total_similar' => 0,
                'success_count' => 0,
                'resign_count' => 0,
                'other_count' => 0,
                'success_rate' => 100,
                'resign_rate' => 0,
                'other_rate' => 0,
                'total_percentage_check' => 100,
                'avg_duration' => 0,
                'profiles' => [],
                'confidence_level' => 'N/A',
                'sample_size' => 0
            ],
            'resign_analysis' => [
                'is_high_risk' => false,
                'analysis_date' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    // 3. FOR ALL EMPLOYEES (INCLUDING UNMATCH) - DATA MINING RECOMMENDATION WITH RESIGN INTELLIGENCE
    $resign_analysis = getResignBeforeContractEndAnalysis($conn, $employee);
    $duration_recommendation = getDataMiningDurationRecommendation($conn, $employee, $contract_progression_data, $resign_analysis);
    
    // Get recommended contract type first before filtering profiles
    $recommended_contract_type = getRecommendedContractType($employee, $duration_recommendation);
    
    $historical_patterns = getDataMiningHistoricalPatterns($conn, $employee, $recommended_contract_type);
    $reasoning = generateDataMiningReasoning($employee, $education_job_analysis, $contract_progression_data, $historical_patterns);
    
    // Enhanced resign intelligence integration
    if (isset($resign_analysis['optimal_duration_recommendation'])) {
        $optimal = $resign_analysis['optimal_duration_recommendation'];
        if ($optimal['early_resign_rate'] < 100) {
            $reasoning[] = "🎯 Resign Intelligence: Durasi optimal {$optimal['recommended_duration']} bulan (resign rate: {$optimal['early_resign_rate']}%)";
        }
    }
    
    if (!empty($resign_analysis['high_risk_durations'])) {
        $risky_durations = implode(', ', array_keys($resign_analysis['high_risk_durations']));
        $reasoning[] = "⚠️ High Risk Durations: {$risky_durations} bulan - hindari durasi ini untuk profil serupa";
    }
    
    if (!empty($resign_analysis['safe_durations'])) {
        $safe_durations = implode(', ', array_keys($resign_analysis['safe_durations']));
        $reasoning[] = "✅ Safe Durations: {$safe_durations} bulan - rekomendasi terbaik untuk profil serupa";
    }
    
    if ($resign_analysis['is_high_risk']) {
        $reasoning[] = "🚨 HIGH RISK: Kontrak type saat ini memiliki tingkat resign tinggi untuk profil serupa";
    }
    
    // Determine final match category based on education match and contract progression logic
    $final_match_category = $education_job_analysis['match_category'];
    $current_contract = $contract_progression_data['current_contract'];
    
    // DEBUG: Log the values for troubleshooting (disabled for performance)
    // error_log("=== DYNAMIC INTELLIGENCE DEBUG ===");
    // error_log("Employee: " . ($employee['name'] ?? 'Unknown'));
    // error_log("Education Match Category: " . $education_job_analysis['match_category']);
    // error_log("Duration Recommendation Category: " . $duration_recommendation['category']);
    // error_log("Current Contract: " . $current_contract);
    
    // Special case: If eliminated due to non-IT education, use that category
    if ($duration_recommendation['category'] === 'Tidak Direkomendasikan') {
        $final_match_category = 'Tidak Direkomendasikan';
                    // error_log("Final Category: Tidak Direkomendasikan (Non-IT elimination)");
    }
    // Special case: If already permanent, use that category
    elseif ($duration_recommendation['category'] === 'Sudah Permanent') {
        $final_match_category = 'Sudah Permanent';
                    // error_log("Final Category: Sudah Permanent");
    }
    // For UNMATCH employees - use evaluation contract logic (same as frontend getRecommendationStatusBadge)
    // Use frontend logic: only IT education + IT role = Match, everything else = Unmatch
    $major_upper = strtoupper($employee['major'] ?? '');
    $role_upper = strtoupper($employee['role'] ?? '');
    
    // Frontend IT Education Keywords (same as frontend calculateMatchStatusLocal)
    $frontend_it_education = [
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
        'COMPUTER SIENCE', 'COMPUTER TECHNOLOGY', 'COMPUTER SCINCE', 'INFORMASTION SYSTEM',
        'INFORMATICS TECHNIQUE', 'INFORMATIOCS', 'INFORMATICS TECHNOLOGY', 'TEKNIK KOMPUTER & JARINGAN',
        'INFORMATION ENGINEERING', 'SOFTWARE ENGINEERING TECHNOLOGY', 'DIGITAL MEDIA TECHNOLOGY',
        'INFORMATIC ENGINEETING', 'INFORMATION SYTEM', 'INFORMASTION SYSTEM', 'MATERIALS ENGINEERING',
        'NETWORK MANAGEMENT', 'INFORMATICS SYSTEM', 'BUSINESS INFORMATION SYSTEM', 'PHYSICS ENGINEERING',
        'MANAGEMENT INFORMATION SYSTEM', 'TELECOMUNICATION ENGINEERING'
    ];
    
    // Frontend IT Job Keywords (same as frontend calculateMatchStatusLocal) 
    $frontend_it_roles = [
        'DEVELOPER', 'PROGRAMMER', 'SOFTWARE', 'TECHNICAL', 'FRONTEND', 'BACKEND', 
        'FULLSTACK', 'FULL STACK', 'JAVA', 'NET', '.NET', 'API', 'ETL', 'MOBILE',
        'ANDROID', 'WEB', 'UI/UX', 'FRONT END', 'BACK END', 'PEGA', 'RPA',
        'ANALYST', 'BUSINESS ANALYST', 'SYSTEM ANALYST', 'DATA ANALYST', 'DATA SCIENTIST',
        'QUALITY ASSURANCE', 'QA', 'TESTER', 'TEST ENGINEER', 'CONSULTANT',
        'IT CONSULTANT', 'TECHNOLOGY CONSULTANT', 'TECHNICAL CONSULTANT',
        'PRODUCT OWNER', 'SCRUM MASTER', 'PMO', 'IT OPERATION', 'DEVOPS',
        'SYSTEM ADMINISTRATOR', 'DATABASE ADMINISTRATOR', 'NETWORK',
        'IT QUALITY ASSURANCE', 'TESTING ENGINEER', 'TESTER SUPPORT', 'JR TESTER',
        'IT TESTER', 'TESTING SPECIALIST', 'TESTING COORDINATOR', 'QUALITY TESTER',
        'JR QUALITY ASSURANCE', 'QUALITY ASSURANCE TESTER', 'TESTING ENGINEER SPECIALIST',
        'APPRENTICE TESTER', 'JR TESTER LEVEL 1', 'LEAD QA', 'LEAD BACKEND DEVELOPER',
        // New IT Roles from CSV Analysis
        'PROJECT MANAGER', 'IT PROJECT MANAGER', 'TECHNICAL PROJECT MANAGER', 'ASSISTANT PROJECT MANAGER',
        'BI DEVELOPER', 'BI COGNOS DEVELOPER', 'POWER BI DEVELOPER', 'SR. BI DEVELOPER',
        'DATA MODELER', 'DATABASE REPORT ENGINEER', 'REPORT DESIGNER',
        'MIDDLEWARE DEVELOPER', 'DATASTAGE DEVELOPER', 'ETL DATASTAGE DEVELOPER', 'IBM DATASTAGE',
        'ODOO DEVELOPER', 'SR. ODOO DEVELOPER', 'TALEND CONSULTANT', 'MFT CONSULTANT',
        'CYBER SECURITY OPERATION', 'IT SECURITY OPERATIONS', 'SECURITY ENGINEER', 'SENIOR SECURITY ENGINEER',
        'CYBER SECURITY POLICY', 'IT INFRA & SECURITY OFFICER', 'INFRASTRUCTURE',
        'LEAD BACKEND DEVELOPER', 'LEAD FRONTEND DEVELOPER', 'SR. TECHNOLOGY CONSULTANT',
        'GRADUATE DEVELOPMENT PROGRAM', 'JUNIOR CONSULTANT', 'JR. CONSULTANT', 'JR CONSULTANT',
        'TECHNICAL LEAD', 'TECH LEAD', 'SOLUTION ANALYST', 'DATA ENGINEER',
        'IT SUPPORT', 'SYSTEM SUPPORT', 'PRODUCTION SUPPORT', 'HELPDESK',
        'TECHNOLOGY CONSULTANT', 'SR. ETL DEVELOPER', 'JR. ETL CONSULTANT',
        'TECHNICAL RESEARCH & DEVELOPMENT CONSULTANT', 'PRESALES', 'IT TRAINER',
        'TECHNICAL TRAINER', 'ASSISTANT TRAINER', 'JAVA TRAINER',
        'MOBILE DEVELOPER', 'ANDROID DEVELOPER', 'IOS DEVELOPER', 'FRONT END MOBILE DEVELOPER',
        'WEB FRONT END DEVELOPER', 'FRONETEND DEVELOPER', 'FRONT END DEVELOPER',
        'SR .NET DEVELOPER', 'JR .NET DEVELOPER', '.NET DEVELOPER', 'SR. .NET DEVELOPER',
        'FULL STACK DEVELOPER', 'FULLSTACK DEVELOPER', 'UI/UX DEVELOPER',
        'RPA DEVELOPER', 'RPA TRAINEE', 'PEGA DEVELOPER', 'SR. PEGA DEVELOPER'
    ];
    
    $is_frontend_it_education = false;
    foreach ($frontend_it_education as $keyword) {
        if (strpos($major_upper, $keyword) !== false) {
            $is_frontend_it_education = true;
            break;
        }
    }
    
    $is_frontend_it_role = false;
    foreach ($frontend_it_roles as $keyword) {
        if (strpos($role_upper, $keyword) !== false) {
            $is_frontend_it_role = true;
            break;
        }
    }
    
    $frontend_match_status = ($is_frontend_it_education && $is_frontend_it_role) ? 'Match' : 'Unmatch';
    // error_log("Frontend Match Status: " . $frontend_match_status . " (IT Education: " . ($is_frontend_it_education ? 'Yes' : 'No') . ", IT Role: " . ($is_frontend_it_role ? 'Yes' : 'No') . ")");
    
    if ($frontend_match_status === 'Unmatch') {
        // POLICY: Unmatch employees get evaluation contracts, not permanent
        switch ($current_contract) {
            case 'probation':
            case '1':
                $final_match_category = 'Kontrak 2 (Evaluasi)';
                break;
            case '2':
            case '3':
                $final_match_category = 'Kontrak 3 (Evaluasi)';
                break;
            default:
                $final_match_category = 'Probation (Evaluasi)';
                break;
        }
        // error_log("Final Category: " . $final_match_category . " (Unmatch evaluation)");
    }
    // For MATCH employees - can use duration recommendation category
    elseif ($duration_recommendation['category'] === 'Siap Permanent') {
        $final_match_category = 'Siap Permanent';
        // error_log("Final Category: Siap Permanent (Match)");
    }
    else {
        // error_log("Final Category: " . $final_match_category . " (Default education match)");
    }
    
    return [
        'match_category' => $final_match_category,
        'optimal_duration' => $duration_recommendation['duration'],
        'confidence_level' => $historical_patterns['confidence_level'],
        'sample_size' => $historical_patterns['sample_size'],
        'historical_patterns' => $historical_patterns,
        'resign_analysis' => $resign_analysis,
        'reasoning' => $reasoning,
        'data_insights' => [
            'total_similar_profiles' => $historical_patterns['sample_size'],
            'avg_duration_similar' => $historical_patterns['avg_duration'] ?? 0,
            'last_updated' => date('Y-m-d H:i:s')
        ]
    ];
}

function analyzeEducationJobMatch($major, $role) {
    // Use the same comprehensive IT Education Keywords as the main matching logic
    $it_fields = [
        'INFORMATION TECHNOLOGY', 'TEKNIK INFORMATIKA', 'ILMU KOMPUTER', 'SISTEM INFORMASI',
        'COMPUTER SCIENCE', 'SOFTWARE ENGINEERING', 'INFORMATIKA', 'TEKNIK KOMPUTER',
        'KOMPUTER', 'TEKNOLOGI INFORMASI', 'INFORMATION SYSTEM', 'INFORMATICS ENGINEERING',
        'INFORMATIC ENGINEERING', 'COMPUTER ENGINEERING', 'INFORMATION MANAGEMENT',
        'INFORMATICS MANAGEMENT', 'MANAGEMENT INFORMATIKA', 'SISTEM KOMPUTER',
        'KOMPUTERISASI AKUNTANASI', 'COMPUTERIZED ACCOUNTING', 'COMPUTATIONAL SCIENCE',
        'INFORMATICS', 'INFORMATICS TECHNOLOGY', 'COMPUTER AND INFORMATICS ENGINEERING',
        'ENGINEERING INFORMATIC', 'INDUSTRIAL ENGINEERING_INFORMATIC',
        // New IT Education Majors from CSV Analysis
        'STATISTICS', 'TECHNOLOGY MANAGEMENT', 'ELECTRICAL ENGINEERING', 'ELECTRONICS ENGINEERING',
        'MASTER IN INFORMATICS', 'ICT', 'COMPUTER SCIENCE & ELECTRONICS', 'COMPUTER TELECOMMUNICATION',
        'TELECOMMUNICATION ENGINEERING', 'TELECOMMUNICATIONS ENGINEERING', 'TELECOMMUNICATION & MEDIA',
        'TEKNIK ELEKTRO', 'COMPUTER SCINCE', 'COMPUTER SCIENCES & ENGINEERING', 'COMPUTER SYSTEM',
        'COMPUTER SIENCE', 'COMPUTER TECHNOLOGY', 'COMPUTER SCINCE', 'INFORMASTION SYSTEM',
        'INFORMATICS TECHNIQUE', 'INFORMATIOCS', 'INFORMATICS TECHNOLOGY', 'TEKNIK KOMPUTER & JARINGAN',
        'INFORMATION ENGINEERING', 'SOFTWARE ENGINEERING TECHNOLOGY', 'DIGITAL MEDIA TECHNOLOGY',
        'INFORMATIC ENGINEETING', 'INFORMATION SYTEM', 'INFORMASTION SYSTEM', 'MATERIALS ENGINEERING',
        'NETWORK MANAGEMENT', 'INFORMATICS SYSTEM', 'BUSINESS INFORMATION SYSTEM', 'PHYSICS ENGINEERING',
        'MANAGEMENT INFORMATION SYSTEM', 'TELECOMUNICATION ENGINEERING'
    ];
    
    // Use the same comprehensive IT Job Keywords as the main matching logic
    $it_roles = [
        'DEVELOPER', 'PROGRAMMER', 'SOFTWARE', 'TECHNICAL', 'FRONTEND', 'BACKEND', 
        'FULLSTACK', 'FULL STACK', 'JAVA', 'NET', '.NET', 'API', 'ETL', 'MOBILE',
        'ANDROID', 'WEB', 'UI/UX', 'FRONT END', 'BACK END', 'PEGA', 'RPA',
        'ANALYST', 'BUSINESS ANALYST', 'SYSTEM ANALYST', 'DATA ANALYST', 'DATA SCIENTIST',
        'QUALITY ASSURANCE', 'QA', 'TESTER', 'TEST ENGINEER', 'CONSULTANT',
        'IT CONSULTANT', 'TECHNOLOGY CONSULTANT', 'TECHNICAL CONSULTANT',
        'PRODUCT OWNER', 'SCRUM MASTER', 'PMO', 'IT OPERATION', 'DEVOPS',
        'SYSTEM ADMINISTRATOR', 'DATABASE ADMINISTRATOR', 'NETWORK',
        'IT QUALITY ASSURANCE', 'TESTING ENGINEER', 'TESTER SUPPORT', 'JR TESTER',
        'IT TESTER', 'TESTING SPECIALIST', 'TESTING COORDINATOR', 'QUALITY TESTER',
        'JR QUALITY ASSURANCE', 'QUALITY ASSURANCE TESTER', 'TESTING ENGINEER SPECIALIST',
        'APPRENTICE TESTER', 'JR TESTER LEVEL 1', 'LEAD QA', 'LEAD BACKEND DEVELOPER',
        // New IT Roles from CSV Analysis
        'PROJECT MANAGER', 'IT PROJECT MANAGER', 'TECHNICAL PROJECT MANAGER', 'ASSISTANT PROJECT MANAGER',
        'BI DEVELOPER', 'BI COGNOS DEVELOPER', 'POWER BI DEVELOPER', 'SR. BI DEVELOPER',
        'DATA MODELER', 'DATABASE REPORT ENGINEER', 'REPORT DESIGNER',
        'MIDDLEWARE DEVELOPER', 'DATASTAGE DEVELOPER', 'ETL DATASTAGE DEVELOPER', 'IBM DATASTAGE',
        'ODOO DEVELOPER', 'SR. ODOO DEVELOPER', 'TALEND CONSULTANT', 'MFT CONSULTANT',
        'CYBER SECURITY OPERATION', 'IT SECURITY OPERATIONS', 'SECURITY ENGINEER', 'SENIOR SECURITY ENGINEER',
        'CYBER SECURITY POLICY', 'IT INFRA & SECURITY OFFICER', 'INFRASTRUCTURE',
        'LEAD BACKEND DEVELOPER', 'LEAD FRONTEND DEVELOPER', 'SR. TECHNOLOGY CONSULTANT',
        'GRADUATE DEVELOPMENT PROGRAM', 'JUNIOR CONSULTANT', 'JR. CONSULTANT', 'JR CONSULTANT',
        'TECHNICAL LEAD', 'TECH LEAD', 'SOLUTION ANALYST', 'DATA ENGINEER',
        'IT SUPPORT', 'SYSTEM SUPPORT', 'PRODUCTION SUPPORT', 'HELPDESK',
        'TECHNOLOGY CONSULTANT', 'SR. ETL DEVELOPER', 'JR. ETL CONSULTANT',
        'TECHNICAL RESEARCH & DEVELOPMENT CONSULTANT', 'PRESALES', 'IT TRAINER',
        'TECHNICAL TRAINER', 'ASSISTANT TRAINER', 'JAVA TRAINER',
        'MOBILE DEVELOPER', 'ANDROID DEVELOPER', 'IOS DEVELOPER', 'FRONT END MOBILE DEVELOPER',
        'WEB FRONT END DEVELOPER', 'FRONETEND DEVELOPER', 'FRONT END DEVELOPER',
        'SR .NET DEVELOPER', 'JR .NET DEVELOPER', '.NET DEVELOPER', 'SR. .NET DEVELOPER',
        'FULL STACK DEVELOPER', 'FULLSTACK DEVELOPER', 'UI/UX DEVELOPER',
        'RPA DEVELOPER', 'RPA TRAINEE', 'PEGA DEVELOPER', 'SR. PEGA DEVELOPER'
    ];
    
    $business_fields = [
        'MANAGEMENT', 'BUSINESS ADMINISTRATION', 'ECONOMICS', 'ACCOUNTING',
        'FINANCE', 'MARKETING', 'HUMAN RESOURCES', 'INTERNATIONAL BUSINESS'
    ];
    
    $business_roles = [
        'Manager', 'Business Analyst', 'Account Manager', 'Sales Manager',
        'Marketing Manager', 'HR Manager', 'Finance Manager', 'Operations Manager',
        'Project Manager', 'Product Manager', 'Business Development',
        'RR Business Analyst', 'PMO Officer', 'BI Developer', 'BI Analyst'
    ];
    
    $engineering_fields = [
        'MECHANICAL ENGINEERING', 'ELECTRICAL ENGINEERING', 'CIVIL ENGINEERING',
        'CHEMICAL ENGINEERING', 'INDUSTRIAL ENGINEERING', 'AEROSPACE ENGINEERING'
    ];
    
    $engineering_roles = [
        'Engineer', 'Technical Engineer', 'Design Engineer', 'Process Engineer',
        'Quality Engineer', 'Production Engineer', 'Maintenance Engineer'
    ];
    
    $healthcare_fields = ['MEDICINE', 'NURSING', 'PHARMACY', 'DENTISTRY', 'Kedokteran'];
    $healthcare_roles = ['Doctor', 'Nurse', 'Pharmacist', 'Medical', 'Healthcare'];
    
    // Normalize strings
    $major_upper = strtoupper($major);
    $role_upper = strtoupper($role);
    
    $score = 0;
    $details = [];
    
    // Perfect matches (Score 3)
    if (in_array($major_upper, $it_fields) && 
        array_filter($it_roles, fn($r) => stripos($role_upper, strtoupper($r)) !== false)) {
        $score = 3;
        $details[] = "Perfect IT match: {$major} → {$role}";
    } elseif (in_array($major_upper, $business_fields) && 
              array_filter($business_roles, fn($r) => stripos($role_upper, strtoupper($r)) !== false)) {
        $score = 3;
        $details[] = "Perfect Business match: {$major} → {$role}";
    } elseif (in_array($major_upper, $engineering_fields) && 
              array_filter($engineering_roles, fn($r) => stripos($role_upper, strtoupper($r)) !== false)) {
        $score = 3;
        $details[] = "Perfect Engineering match: {$major} → {$role}";
    } elseif (in_array($major_upper, $healthcare_fields) && 
              array_filter($healthcare_roles, fn($r) => stripos($role_upper, strtoupper($r)) !== false)) {
        $score = 3;
        $details[] = "Perfect Healthcare match: {$major} → {$role}";
    }
    
    // Good cross-field matches (Score 2)
    elseif ((in_array($major_upper, $it_fields) && 
             array_filter($business_roles, fn($r) => stripos($role_upper, strtoupper($r)) !== false)) ||
            (in_array($major_upper, $business_fields) && 
             array_filter($it_roles, fn($r) => stripos($role_upper, strtoupper($r)) !== false))) {
        $score = 2;
        $details[] = "Good cross-field match: {$major} → {$role}";
    }
    
    // Partial matches (Score 1)
    elseif (stripos($major_upper, 'ENGINEERING') !== false || 
            stripos($role_upper, 'DEVELOPER') !== false ||
            stripos($role_upper, 'ANALYST') !== false) {
        $score = 1;
                    $details[] = "Partial match: Basic education compatibility";
    }
    
    // No clear match (Score 0) - but still can get recommendations based on performance
    else {
        $score = 0;
        $details[] = "No direct match - recommendation based on performance & tenure";
    }
    
    // Determine final category - UNMATCH masih bisa dapat rekomendasi
    if ($score >= 3) {
        $match_category = 'Sesuai';
    } elseif ($score >= 2) {
        $match_category = 'Cukup Sesuai';
    } elseif ($score >= 1) {
        $match_category = 'Kurang Sesuai';
    } else {
        $match_category = 'Tidak Sesuai'; // Status tetap unmatch, tapi tetap dapat rekomendasi
    }
    
    return [
        'score' => $score,
        'match_category' => $match_category,
        'details' => $details
    ];
}

function getContractProgressionData($conn, $employee) {
    // Berdasarkan contract progression analysis dari Python
    $current_contract = $employee['current_contract_type'] ?? 'none';
    $tenure_months = $employee['tenure_months'] ?? 0;
    
    // Get contract history untuk progression analysis
    $stmt = $conn->prepare("
        SELECT 
            type,
            start_date,
            end_date,
            status,
            DATEDIFF(COALESCE(end_date, CURDATE()), start_date) / 30 as duration_months
        FROM contracts 
        WHERE eid = ? 
        ORDER BY start_date ASC
    ");
    $stmt->bind_param("i", $employee['eid']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $contract_history = [];
    $total_contracts = 0;
    $current_contract_duration = 0;
    
    while ($row = $result->fetch_assoc()) {
        $contract_history[] = $row;
        $total_contracts++;
        
        if ($row['status'] === 'active') {
            $current_contract_duration = $row['duration_months'];
        }
    }
    
    // Determine progression stage berdasarkan Python analysis
    $progression_stage = '';
    if ($current_contract === 'probation') {
        $progression_stage = 'Probation Stage';
    } elseif ($current_contract === '2' || $current_contract === 'kontrak2') {
        $progression_stage = 'Kontrak 2 Stage';
    } elseif ($current_contract === '3' || $current_contract === 'kontrak3') {
        $progression_stage = 'Kontrak 3 Stage';
    } elseif ($current_contract === 'permanent') {
        $progression_stage = 'Permanent Stage';
    } else {
        $progression_stage = 'Initial Stage';
    }
    
    return [
        'current_contract' => $current_contract,
        'tenure_months' => $tenure_months,
        'current_duration' => $current_contract_duration,
        'total_contracts' => $total_contracts,
        'progression_stage' => $progression_stage,
        'contract_history' => $contract_history
    ];
}

function getDataMiningDurationRecommendation($conn, $employee, $contract_progression_data, $resign_analysis = null) {
    // DYNAMIC DATA MINING - Berubah seiring bertambahnya data resign dan karyawan
    $current_contract = $contract_progression_data['current_contract'];
    $tenure_months = $contract_progression_data['tenure_months'];
    $total_contracts = $contract_progression_data['total_contracts'];
    $major = $employee['major'] ?? '';
    $role = $employee['role'] ?? '';
    
    // *** CHECK FOR NON-IT MAJOR ELIMINATION FIRST ***
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
    
    $employeeMajor = strtolower(trim($major));
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
            'category' => 'Tidak Direkomendasikan',
            'duration' => 0,
            'reasoning' => "ELIMINASI OTOMATIS: Latar belakang pendidikan Non-IT ({$major}) tidak sesuai untuk posisi teknologi di perusahaan IT. Tidak direkomendasikan untuk perpanjangan kontrak."
        ];
    }
    
    // Get education match analysis
    $education_match = analyzeEducationJobMatch($major, $role);
    $is_match = $education_match['match_category'] === 'Sesuai';
    $is_unmatch = $education_match['match_category'] === 'Tidak Sesuai';
    
    // KONTRAK 3 HANYA BISA PERMANENT (sesuai permintaan user)
    if ($current_contract === '3' || $current_contract === 'kontrak3') {
        return [
            'category' => 'Siap Permanent',
            'duration' => 0, // 0 = Permanent
            'reasoning' => 'Kontrak 3 hanya dapat dipromosikan ke Permanent berdasarkan kebijakan perusahaan'
        ];
    }
    
    // DYNAMIC ANALYSIS - Real-time data mining berdasarkan contract outcomes
    $stmt = $conn->prepare("
        SELECT 
            c.type,
            c.start_date,
            c.end_date,
            c.status,
            e.status as employee_status,
            e.resign_date,
            DATEDIFF(COALESCE(e.resign_date, c.end_date, CURDATE()), c.start_date) / 30 as duration_months,
            CASE 
                WHEN e.status = 'active' AND c.status = 'active' THEN 'Success'
                WHEN e.status = 'active' AND c.type = 'permanent' THEN 'Success'
                WHEN e.status = 'resigned' THEN 'Failed'
                WHEN c.status = 'completed' THEN 'Completed'
                ELSE 'Unknown'
            END as outcome_category,
            CASE 
                WHEN e.status = 'resigned' AND e.resign_date IS NOT NULL 
                THEN DATEDIFF(e.resign_date, c.start_date) / 30
                ELSE NULL
            END as resign_timing_months
        FROM contracts c
        JOIN employees e ON c.eid = e.eid
        WHERE c.type = ? OR c.type = 'probation'
        ORDER BY c.start_date DESC
        LIMIT 100
    ");
    
    $stmt->bind_param("s", $current_contract);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $success_count = 0;
    $resign_count = 0;
    $total_count = 0;
    $success_durations = [];
    $resign_patterns = [];
    
    while ($row = $result->fetch_assoc()) {
        $total_count++;
        
        if (strpos($row['outcome_category'], 'Success') === 0) {
            $success_count++;
            if ($row['duration_months'] > 0) {
                $success_durations[] = $row['duration_months'];
            }
        } elseif (strpos($row['outcome_category'], 'Failed') === 0) {
            $resign_count++;
            if ($row['resign_timing_months'] !== null) {
                $resign_patterns[] = $row['resign_timing_months'];
            }
        }
    }
    
    // DYNAMIC SUCCESS RATE CALCULATION
    $success_rate = $total_count > 0 ? ($success_count / $total_count) * 100 : 50;
    $resign_rate = $total_count > 0 ? ($resign_count / $total_count) * 100 : 0;
    
    // RESIGN INTELLIGENCE INTEGRATION - Apply resign patterns to recommendations
    $optimal_duration_from_resign_intelligence = 12; // Default
    $resign_intelligence_reasoning = "";
    
    if ($resign_analysis && isset($resign_analysis['optimal_duration_recommendation'])) {
        $optimal = $resign_analysis['optimal_duration_recommendation'];
        if ($optimal['early_resign_rate'] < 50) { // Only use if reasonable data
            $optimal_duration_from_resign_intelligence = $optimal['recommended_duration'];
            $resign_intelligence_reasoning = " (Resign Intelligence: {$optimal['early_resign_rate']}% risk for {$optimal['recommended_duration']} months)";
        }
    }

    // ADAPTIVE RECOMMENDATION - UNMATCH juga dapat rekomendasi berdasarkan tenure dan performa
    if ($current_contract === 'probation') {
        if ($is_unmatch) {
            // UNMATCH pada probation - kasih kesempatan berdasarkan tenure
            $base_duration = $tenure_months >= 3 ? 6 : 6;
            
            // Apply resign intelligence if available
            if ($resign_analysis && !empty($resign_analysis['safe_durations'])) {
                $safe_options = array_keys($resign_analysis['safe_durations']);
                if (in_array($base_duration, $safe_options) || in_array(6, $safe_options)) {
                    $final_duration = in_array($base_duration, $safe_options) ? $base_duration : 6;
                } else {
                    $final_duration = min($safe_options);
                }
            } else {
                $final_duration = $base_duration;
            }
            
            return [
                'category' => 'Kontrak 2 (Evaluasi)',
                'duration' => $final_duration,
                'reasoning' => "Status Unmatch, tenure probation {$tenure_months} bulan - rekomendasi {$final_duration} bulan" . $resign_intelligence_reasoning
            ];
        } else {
            // MATCH pada probation - gunakan success rate + resign intelligence
            $base_duration = 6; // Conservative default
            
            if ($resign_rate > 40) {
                $base_duration = 6;
            } elseif ($success_rate >= 75) {
                $avg_success_duration = count($success_durations) > 0 ? array_sum($success_durations) / count($success_durations) : 12;
                $standard_durations = [6, 12, 18, 24];
                $base_duration = 12; // default
                foreach ($standard_durations as $duration) {
                    if (abs($avg_success_duration - $duration) < abs($avg_success_duration - $base_duration)) {
                        $base_duration = $duration;
                    }
                }
            }
            
            // Apply resign intelligence
            $final_duration = $base_duration;
            if ($resign_analysis) {
                // Check if base duration is high risk
                if (isset($resign_analysis['high_risk_durations'][$base_duration])) {
                    // Find safer alternative
                    if (!empty($resign_analysis['safe_durations'])) {
                        $safe_options = array_keys($resign_analysis['safe_durations']);
                        $final_duration = min($safe_options);
                        $resign_intelligence_reasoning = " (Adjusted from {$base_duration} months due to high resign risk)";
                    }
                }
            }
            
            return [
                'category' => 'Kontrak 2', 
                'duration' => $final_duration, 
                'reasoning' => "Success rate {$success_rate}% - durasi {$final_duration} bulan" . $resign_intelligence_reasoning
            ];
        }
    } elseif ($current_contract === '2' || $current_contract === 'kontrak2') {
        if ($is_unmatch) {
            // UNMATCH pada Kontrak 2 - lihat perjalanan tenure
            if ($tenure_months >= 18) {
                return [
                    'category' => 'Kontrak 2 (Extended)',
                    'duration' => 12,
                    'reasoning' => "Status Unmatch, namun tenure panjang ({$tenure_months} bulan) menunjukkan komitmen - perpanjang 12 bulan"
                ];
            } elseif ($tenure_months >= 12) {
                return [
                    'category' => 'Kontrak 2',
                    'duration' => 6,
                    'reasoning' => "Status Unmatch dengan tenure cukup ({$tenure_months} bulan) - perpanjang 6 bulan untuk evaluasi"
                ];
            } else {
                return [
                    'category' => 'Evaluasi Lanjutan',
                    'duration' => 6,
                    'reasoning' => "Status Unmatch dengan tenure baru - rekomendasi 6 bulan untuk evaluasi mendalam"
                ];
            }
        } else {
            // MATCH pada Kontrak 2 - gunakan success rate + tenure
            if ($resign_rate > 35) {
                return [
                    'category' => 'Kontrak 2', 
                    'duration' => 12, 
                    'reasoning' => "High resign rate in Kontrak 2 ({$resign_rate}%) - perpanjang dengan hati-hati"
                ];
            } elseif ($tenure_months >= 12 && $success_rate >= 70) {
                $avg_success_duration = count($success_durations) > 0 ? array_sum($success_durations) : 18;
                $calculated_duration = max(18, $avg_success_duration * 1.5);
                // Round to nearest standard duration: 6, 12, 18, 24
                $standard_durations = [6, 12, 18, 24];
                $optimal_duration = 18; // default for Kontrak 3
                foreach ($standard_durations as $duration) {
                    if (abs($calculated_duration - $duration) < abs($calculated_duration - $optimal_duration)) {
                        $optimal_duration = $duration;
                    }
                }
                return [
                    'category' => 'Kontrak 3', 
                    'duration' => $optimal_duration, 
                    'reasoning' => "Excellent pattern (tenure {$tenure_months}mo, success {$success_rate}%) - siap Kontrak 3"
                ];
            } else {
                $avg_success_duration = count($success_durations) > 0 ? array_sum($success_durations) / count($success_durations) : 12;
                // Round to nearest standard duration: 6, 12, 18, 24
                $standard_durations = [6, 12, 18, 24];
                $optimal_duration = 12; // default
                foreach ($standard_durations as $duration) {
                    if (abs($avg_success_duration - $duration) < abs($avg_success_duration - $optimal_duration)) {
                        $optimal_duration = $duration;
                    }
                }
                return [
                    'category' => 'Kontrak 2', 
                    'duration' => $optimal_duration, 
                    'reasoning' => "Continue Kontrak 2 - success rate {$success_rate}%, durasi optimal {$optimal_duration} bulan"
                ];
            }
        }
    } else {
        // New employee - conservative approach
        if ($is_unmatch) {
            return [
                'category' => 'Probation (Extended)',
                'duration' => 6,
                'reasoning' => "Status Unmatch untuk karyawan baru - probation diperpanjang 6 bulan untuk adaptasi dan training"
            ];
        } else {
            if ($resign_rate > 30) {
                return [
                    'category' => 'Probation', 
                    'duration' => 6, 
                    'reasoning' => "High market resign rate ({$resign_rate}%) - start with probation 6 bulan"
                ];
            } else {
                $avg_success_duration = count($success_durations) > 0 ? array_sum($success_durations) / count($success_durations) : 6;
                $calculated_duration = $avg_success_duration * 0.8;
                // Round to nearest standard duration: 6, 12, 18, 24
                $standard_durations = [6, 12, 18, 24];
                $optimal_duration = 6; // default for new employees
                foreach ($standard_durations as $duration) {
                    if (abs($calculated_duration - $duration) < abs($calculated_duration - $optimal_duration)) {
                        $optimal_duration = $duration;
                    }
                }
                return [
                    'category' => 'Kontrak 2', 
                    'duration' => $optimal_duration, 
                    'reasoning' => "Standard start - market success rate {$success_rate}%, durasi optimal {$optimal_duration} bulan"
                ];
            }
        }
    }
}

function getRecommendedContractType($employee, $duration_recommendation) {
    $current_contract = strtolower(trim($employee['current_contract_type'] ?? ''));
    $category = $duration_recommendation['category'] ?? '';
    $optimal_duration = $duration_recommendation['optimal_duration'] ?? 0;
    
    // PRIORITY 1: Use category from duration recommendation if available
    if (!empty($category)) {
        // Parse category to extract contract type
        if (stripos($category, 'Permanent') !== false || stripos($category, 'Siap Permanent') !== false) {
            return 'permanent';
        } elseif (stripos($category, 'Kontrak 3') !== false || stripos($category, 'kontrak3') !== false) {
            return '3';
        } elseif (stripos($category, 'Kontrak 2') !== false || stripos($category, 'kontrak2') !== false) {
            return '2';
        } elseif (stripos($category, 'Kontrak 1') !== false || stripos($category, 'kontrak1') !== false) {
            return '1';
        } elseif (stripos($category, 'Probation') !== false) {
            return 'probation';
        }
    }
    
    // PRIORITY 2: Use optimal duration for mapping (fallback)
    if ($optimal_duration === 0) {
        // Duration 0 typically means permanent or next level progression
        if ($current_contract === '3' || $current_contract === 'kontrak3') {
            return 'permanent';
        } elseif ($current_contract === '2' || $current_contract === 'kontrak2') {
            return '3'; // Usually kontrak2 -> kontrak3, not directly permanent
        } else {
            return '2'; // Default next level
        }
    }
    
    // PRIORITY 3: Standard progression mapping based on current contract
    if ($current_contract === 'probation') {
        if ($optimal_duration >= 18) {
            return '3'; // kontrak3
        } else {
            return '2'; // kontrak2 (most common progression from probation)
        }
    } elseif ($current_contract === '1' || $current_contract === 'kontrak1') {
        if ($optimal_duration >= 18) {
            return '3'; // kontrak3
        } else {
            return '2'; // kontrak2
        }
    } elseif ($current_contract === '2' || $current_contract === 'kontrak2') {
        if ($optimal_duration >= 18) {
            return '3'; // kontrak3
        } else {
            return '3'; // Default progression kontrak2 -> kontrak3
        }
    } elseif ($current_contract === '3' || $current_contract === 'kontrak3') {
        // For kontrak3, only go to permanent if specifically recommended
        if (!empty($category) && (stripos($category, 'Permanent') !== false || stripos($category, 'Siap Permanent') !== false)) {
            return 'permanent';
        } else {
            return '3'; // Stay in kontrak3 for further evaluation
        }
    } elseif ($current_contract === 'permanent') {
        return 'permanent'; // Already permanent
    }
    
    // Default progression
    return '2'; // kontrak2 as default
}

function getDataMiningHistoricalPatterns($conn, $employee, $recommended_contract_type = null) {
    // DYNAMIC HISTORICAL PATTERNS - Berubah seiring bertambahnya data
    $education_analysis = analyzeEducationJobMatch($employee['major'], $employee['role']);
    
    // Get COMPREHENSIVE employee data including ALL contract experiences
    $stmt = $conn->prepare("
        SELECT DISTINCT
            e.eid,
            e.name,
            e.major,
            e.role,
            e.education_level,
            e.status,
            e.join_date,
            e.resign_date,
            DATEDIFF(CURDATE(), e.join_date) / 30 as total_tenure_months,
            
            -- Get current/latest contract
            (SELECT c1.type FROM contracts c1 
             WHERE c1.eid = e.eid 
             ORDER BY CASE WHEN c1.status = 'active' THEN 1 ELSE 2 END, c1.contract_id DESC 
             LIMIT 1) as current_contract_type,
             
            -- Get contract progression info
            (SELECT COUNT(*) FROM contracts c2 WHERE c2.eid = e.eid) as total_contracts,
            (SELECT MAX(c3.type) FROM contracts c3 WHERE c3.eid = e.eid AND c3.type IN ('permanent', '3', '2', '1')) as highest_contract_reached,
            
            -- Outcome status
            CASE 
                WHEN e.status = 'active' AND EXISTS(SELECT 1 FROM contracts c4 WHERE c4.eid = e.eid AND c4.type = 'permanent' AND c4.status = 'active') THEN 'Reached_Permanent'
                WHEN e.status = 'active' THEN 'Currently_Active'
                WHEN e.status = 'resigned' AND e.resign_date IS NOT NULL THEN 'Resigned'
                WHEN e.status = 'terminated' THEN 'Terminated'
                ELSE 'Unknown'
            END as outcome_status,
            
            CASE 
                WHEN e.status = 'resigned' AND e.resign_date IS NOT NULL 
                THEN DATEDIFF(e.resign_date, e.join_date) / 30
                ELSE NULL
            END as resign_after_months,
            
            -- Contract duration info
            (SELECT DATEDIFF(COALESCE(c5.end_date, CURDATE()), c5.start_date) / 30 
             FROM contracts c5 
             WHERE c5.eid = e.eid 
             ORDER BY CASE WHEN c5.status = 'active' THEN 1 ELSE 2 END, c5.contract_id DESC 
             LIMIT 1) as contract_duration_months
        
        FROM employees e
        WHERE e.eid != ?
        AND e.join_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
        AND EXISTS(SELECT 1 FROM contracts c WHERE c.eid = e.eid)
        ORDER BY 
            -- Prioritize employees with relevant contract experiences
            CASE WHEN EXISTS(SELECT 1 FROM contracts c WHERE c.eid = e.eid AND c.type IN ('permanent', '3', '2')) THEN 1 ELSE 2 END,
            e.join_date DESC
        LIMIT 200
    ");
    
    $stmt->bind_param("i", $employee['eid']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $all_profiles = [];
    $similar_profiles = [];
    $success_profiles = [];
    $resign_profiles = [];
    $early_resign_profiles = [];
    $total_duration = 0;
    $duration_count = 0;
    $resign_timing = [];
    $early_resign_timing = [];
    
    while ($row = $result->fetch_assoc()) {
        $all_profiles[] = $row;
        
        // Analyze education-job match untuk setiap profile
        $profile_analysis = analyzeEducationJobMatch($row['major'], $row['role']);
        
        // STRICT SIMILARITY MATCHING - More specific untuk mendapatkan data yang bervariasi
        $is_similar = false;
        $similarity_score = 0;
        
        // LEVEL 0: Contract Type Filtering (CORRECTED) - Find profiles with CURRENT contract = RECOMMENDED contract
        $recommended_contract = strtolower(trim($recommended_contract_type ?? ''));
        $profile_current_contract = strtolower(trim($row['current_contract_type'] ?? ''));
        
                // ENHANCED CONTRACT FILTERING - More flexible to handle data imbalance
        
        // Primary: Exact match with recommended contract (highest priority)
        if ($recommended_contract === $profile_current_contract && !empty($recommended_contract)) {
            $similarity_score += 4; // Very high priority for exact match
        }
        // Secondary: Contract progression patterns (medium-high priority)
        else {
            $contract_progression_map = [
                // For probation -> look for kontrak2, kontrak3, or successful progressions
                'probation' => ['1', 'kontrak1', '2', 'kontrak2', '3', 'kontrak3'],
                '1' => ['probation', '2', 'kontrak1', 'kontrak2', '3', 'kontrak3'],
                'kontrak1' => ['probation', '1', '2', 'kontrak2', '3', 'kontrak3'],
                
                // For kontrak2 -> look for kontrak3, permanent, and similar progression
                '2' => ['1', '3', 'kontrak1', 'kontrak2', 'kontrak3', 'permanent'],
                'kontrak2' => ['1', '2', 'kontrak1', '3', 'kontrak3', 'permanent'],
                
                // For kontrak3 -> look for permanent and successful progressions
                '3' => ['2', 'permanent', 'kontrak2', 'kontrak3'],
                'kontrak3' => ['2', '3', 'kontrak2', 'permanent'],
                
                // For permanent -> look for kontrak3 and successful cases
                'permanent' => ['3', 'kontrak3', 'permanent']
            ];
            
            if (isset($contract_progression_map[$recommended_contract]) && 
                in_array($profile_current_contract, $contract_progression_map[$recommended_contract])) {
                
                // Higher score for closer progressions
                if (($recommended_contract === '3' || $recommended_contract === 'kontrak3') && 
                    ($profile_current_contract === 'permanent')) {
                    $similarity_score += 3; // kontrak3 -> permanent: high relevance
                } elseif (($recommended_contract === '2' || $recommended_contract === 'kontrak2') && 
                         ($profile_current_contract === '3' || $profile_current_contract === 'kontrak3')) {
                    $similarity_score += 3; // kontrak2 -> kontrak3: high relevance
                } elseif (($recommended_contract === 'permanent') && 
                         ($profile_current_contract === '3' || $profile_current_contract === 'kontrak3')) {
                    $similarity_score += 2; // permanent looking at kontrak3: medium-high relevance
                } else {
                    $similarity_score += 1; // Other related contracts: medium relevance
                }
            }
        }
        
        // Tertiary: If we still don't have enough matches, include broader set
        // This handles data imbalance - if very few exact matches, include successful patterns
        if ($similarity_score === 0) {
            // Include any successful outcomes (Currently_Active, Reached_Permanent) for learning
            if (isset($row['outcome_status']) && 
                ($row['outcome_status'] === 'Currently_Active' || $row['outcome_status'] === 'Reached_Permanent')) {
                $similarity_score += 0.5; // Low priority but still relevant for pattern learning
            }
        }
        
        // LEVEL 1: Exact major match (highest priority)
        $employee_major = strtolower(trim($employee['major'] ?? ''));
        $profile_major = strtolower(trim($row['major'] ?? ''));
        if ($employee_major === $profile_major && !empty($employee_major)) {
            $similarity_score += 3;
        }
        
        // LEVEL 2: Major category similarity (if exact match not found)
        else {
            $major_categories = [
                'information_system' => ['information system', 'sistem informasi'],
                'informatics' => ['informatics', 'informatika', 'teknik informatika'],
                'computer_science' => ['computer science', 'ilmu komputer'],
                'informatics_engineering' => ['informatics engineering', 'teknik informatika']
            ];
            
            $employee_category = null;
            $profile_category = null;
            
            foreach ($major_categories as $category => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($employee_major, $keyword) !== false) {
                        $employee_category = $category;
                    }
                    if (strpos($profile_major, $keyword) !== false) {
                        $profile_category = $category;
                    }
                }
            }
            
            if ($employee_category && $employee_category === $profile_category) {
                $similarity_score += 2;
            }
        }
        
        // LEVEL 3: Role similarity (more specific)
        $employee_role = strtolower(trim($employee['role'] ?? ''));
        $profile_role = strtolower(trim($row['role'] ?? ''));
        
        // Exact role match
        if ($employee_role === $profile_role && !empty($employee_role)) {
            $similarity_score += 2;
        }
        // Role category match
        else {
            $role_categories = [
                'tester' => ['tester', 'qa', 'quality assurance'],
                'developer' => ['developer', 'programmer'],
                'analyst' => ['analyst', 'business analyst'],
                'manager' => ['manager', 'project manager'],
                'engineer' => ['engineer'],
                'consultant' => ['consultant']
            ];
            
            $employee_role_category = null;
            $profile_role_category = null;
            
            foreach ($role_categories as $category => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($employee_role, $keyword) !== false) {
                        $employee_role_category = $category;
                    }
                    if (strpos($profile_role, $keyword) !== false) {
                        $profile_role_category = $category;
                    }
                }
            }
            
            if ($employee_role_category && $employee_role_category === $profile_role_category) {
                $similarity_score += 1;
            }
        }
        
        // LEVEL 4: Education level match (Enhanced)
        $employee_education = strtolower(trim($employee['education_level'] ?? ''));
        $profile_education = strtolower(trim($row['education_level'] ?? ''));
        
        if ($employee_education === $profile_education && !empty($employee_education)) {
            $similarity_score += 1;
        } else {
            // Education level proximity scoring
            $education_hierarchy = [
                'sma' => ['smk', 'd3'],
                'smk' => ['sma', 'd3'],
                'd3' => ['sma', 'smk', 's1'],
                's1' => ['d3', 's2'],
                's2' => ['s1', 's3'],
                's3' => ['s2']
            ];
            
            if (isset($education_hierarchy[$employee_education]) && 
                in_array($profile_education, $education_hierarchy[$employee_education])) {
                $similarity_score += 0.5; // Partial score for related education levels
            }
        }
        
        // ADAPTIVE SIMILARITY THRESHOLD: Flexible based on data availability
        $current_similar_count = count($similar_profiles);
        
        if ($similarity_score >= 6) {
            // Excellent match (contract + education + role match)
            $is_similar = true;
        } elseif ($similarity_score >= 4) {
            // High quality match (good contract progression + education/role)
            $is_similar = true;
        } elseif ($similarity_score >= 2) {
            // Good match (contract relevance + some education/role match)
            $is_similar = true;
        } elseif ($similarity_score >= 1 && $current_similar_count < 10) {
            // Medium match - include if we need more profiles
            $is_similar = true;
        } elseif ($similarity_score >= 0.5 && $current_similar_count < 5) {
            // Lower threshold if very few profiles available (handles data imbalance)
            $is_similar = true;
        }
        
        if ($is_similar) {
            // Add similarity metadata for transparency
            $row['similarity_score'] = $similarity_score;
            $row['contract_match'] = ($recommended_contract === $profile_current_contract);
            $row['recommended_contract_type'] = $recommended_contract; // Show what contract we're looking for
            $row['education_match'] = ($employee_education === $profile_education);
            $row['major_match'] = ($employee_major === $profile_major);
            $similar_profiles[] = $row;
            
            // Enhanced tracking with Early Resign detection
            $is_early_resign = false;
            
            // Check if this is an early resign (resign before contract end)
            if ($row['outcome_status'] === 'Resigned' && 
                $row['resign_after_months'] !== null && 
                $row['contract_duration_months'] !== null &&
                $row['resign_after_months'] < $row['contract_duration_months']) {
                $is_early_resign = true;
            }
            
            // Track success vs failure patterns - EXPANDED CLASSIFICATION
            if ($row['outcome_status'] === 'Currently_Active' || 
                $row['outcome_status'] === 'Reached_Permanent' || 
                $row['outcome_status'] === 'Contract_Completed') {
                // SUCCESS: Aktif, Permanent, atau selesai kontrak dengan baik
                $success_profiles[] = $row;
                if ($row['total_tenure_months'] > 0) {
                    $total_duration += $row['total_tenure_months'];
                    $duration_count++;
                }
            } elseif ($is_early_resign) {
                // EARLY RESIGN: Resign sebelum kontrak habis
                $resign_profiles[] = $row;
                $early_resign_profiles[] = $row;
                if ($row['resign_after_months'] !== null) {
                    $resign_timing[] = $row['resign_after_months'];
                    $early_resign_timing[] = $row['resign_after_months'];
                }
            } elseif ($row['outcome_status'] === 'Resigned' || 
                      $row['outcome_status'] === 'Terminated' || 
                      $row['outcome_status'] === 'Unknown') {
                // NORMAL RESIGN/FAILURE: Resign normal, di-terminate, atau status tidak jelas
                $resign_profiles[] = $row;
                if ($row['resign_after_months'] !== null) {
                    $resign_timing[] = $row['resign_after_months'];
                }
            }
        }
    }
    
    $total_similar = count($similar_profiles);
    $success_count = count($success_profiles);
    $resign_count = count($resign_profiles);
    $early_resign_count = count($early_resign_profiles);
    $other_count = $total_similar - $success_count - $resign_count; // Untuk memastikan total 100%
    $avg_duration = $duration_count > 0 ? $total_duration / $duration_count : null;
    $avg_resign_timing = count($resign_timing) > 0 ? array_sum($resign_timing) / count($resign_timing) : null;
    $avg_early_resign_timing = count($early_resign_timing) > 0 ? array_sum($early_resign_timing) / count($early_resign_timing) : null;
    
    // Calculate Early Resign Rate
    $early_resign_rate = $total_similar > 0 ? ($early_resign_count / $total_similar) * 100 : 0;
    
    // DYNAMIC SUCCESS RATE CALCULATION - ENSURE 100% TOTAL
    if ($total_similar > 0) {
        $success_rate = ($success_count / $total_similar) * 100;
        $resign_rate = ($resign_count / $total_similar) * 100;
        $other_rate = ($other_count / $total_similar) * 100;
        
        // Validasi total = 100%
        $total_percentage = $success_rate + $resign_rate + $other_rate;
        if (abs($total_percentage - 100) > 0.1) {
            // Jika tidak 100%, adjust resign_rate untuk memastikan total tepat 100%
            $resign_rate = 100 - $success_rate - $other_rate;
        }
    } else {
        // Default values when no similar profiles found
        $success_rate = 50; // Default
        $resign_rate = 30;  // Default  
        $other_rate = 20;   // Default
        // Ensure total is exactly 100%
        $total_percentage = $success_rate + $resign_rate + $other_rate;
        if (abs($total_percentage - 100) > 0.1) {
            $other_rate = 100 - $success_rate - $resign_rate;
        }
    }
    
    // Sort similar profiles by similarity score (highest first) for better display
    usort($similar_profiles, function($a, $b) {
        return ($b['similarity_score'] ?? 0) <=> ($a['similarity_score'] ?? 0);
    });
    
    // ADAPTIVE CONFIDENCE LEVEL berdasarkan data quality dan recency
    $recent_profiles = array_filter($similar_profiles, function($profile) {
        $join_date = new DateTime($profile['join_date']);
        $cutoff = new DateTime('-1 year');
        return $join_date >= $cutoff;
    });
    $recent_count = count($recent_profiles);
    
    if ($total_similar >= 15 && $recent_count >= 5) {
        $confidence_level = 'High';
    } elseif ($total_similar >= 8 && $recent_count >= 3) {
        $confidence_level = 'Medium';
    } elseif ($total_similar >= 3) {
        $confidence_level = 'Low';
    } else {
        $confidence_level = 'Very Low';
    }
    
    return [
        'total_similar' => $total_similar,
        'success_count' => $success_count,
        'resign_count' => $resign_count,
        'other_count' => $other_count ?? 0,
        'success_rate' => round($success_rate, 1),
        'resign_rate' => round($resign_rate, 1),
        'other_rate' => round($other_rate ?? 0, 1),
        'total_percentage_check' => round(($success_rate + $resign_rate + $other_rate), 1), // Debug info
        'avg_duration' => $avg_duration,
        'avg_resign_timing' => $avg_resign_timing,
        'early_resign_rate' => round($early_resign_rate, 1),
        'early_resign_count' => $early_resign_count,
        'avg_early_resign_timing' => $avg_early_resign_timing,
        'recent_profiles_count' => $recent_count,
        'profiles' => $similar_profiles, // Return all similar profiles
        'confidence_level' => $confidence_level,
        'sample_size' => $total_similar,
        'match_category_filter' => $education_analysis['match_category'],
        'data_insights' => [
            'last_updated' => date('Y-m-d H:i:s'),
            'data_quality' => $confidence_level,
            'pattern_strength' => $total_similar >= 10 ? 'Strong' : ($total_similar >= 5 ? 'Moderate' : 'Weak'),
            'recommended_contract_type' => $recommended_contract_type, // Show what contract type we're looking for
            'employee_current_contract' => $employee['current_contract_type'] ?? 'unknown',
            'matching_criteria' => [
                'contract_matches' => array_sum(array_column($similar_profiles, 'contract_match')),
                'education_matches' => array_sum(array_column($similar_profiles, 'education_match')),
                'major_matches' => array_sum(array_column($similar_profiles, 'major_match')),
                'avg_similarity_score' => !empty($similar_profiles) ? 
                    round(array_sum(array_column($similar_profiles, 'similarity_score')) / count($similar_profiles), 2) : 0
            ],
            'early_resign_analysis' => [
                'rate' => round($early_resign_rate, 1),
                'count' => $early_resign_count,
                'avg_timing' => $avg_early_resign_timing,
                'risk_level' => $early_resign_rate > 30 ? 'High' : ($early_resign_rate > 15 ? 'Medium' : 'Low')
            ]
        ]
    ];
}

function generateDataMiningReasoning($employee, $education_job_analysis, $contract_progression_data, $historical_patterns) {
    $reasoning = [];
    
    // Education-Job Match Analysis - simplified
    $match_desc = implode(", ", $education_job_analysis['details'] ?? ['No specific match data']);
    $reasoning[] = "🎯 Education-Job Match: " . $education_job_analysis['match_category'] . " (" . $match_desc . ")";
    
    // Contract Progression Analysis
    $reasoning[] = "📈 Contract Stage: " . $contract_progression_data['progression_stage'] . 
                   " dengan tenure " . round($contract_progression_data['tenure_months'], 1) . " bulan";
    
    // Historical Patterns - based on actual data
    $sample_size = $historical_patterns['sample_size'] ?? 0;
    if ($sample_size > 10) {
        $reasoning[] = "📋 Strong Data: {$sample_size} profil serupa - rekomendasi berdasarkan pattern analysis";
    } elseif ($sample_size > 3) {
        $reasoning[] = "📋 Limited Data: {$sample_size} profil serupa - menggunakan broader analysis";
    } else {
        $reasoning[] = "📋 Minimal Data: {$sample_size} profil serupa - rekomendasi berdasarkan company policy";
    }
    
    // Contract History Pattern
    $total_contracts = $contract_progression_data['total_contracts'] ?? 0;
    if ($total_contracts > 1) {
        $reasoning[] = "📊 Contract History: {$total_contracts} kontrak sebelumnya menunjukkan progression pattern";
    }
    
    // Confidence Level
    $confidence = $historical_patterns['confidence_level'] ?? 'Low';
    $sample_desc = $sample_size > 0 ? "Sample: {$sample_size}" : "No similar data";
    $reasoning[] = "🔍 Data Confidence: {$confidence} | {$sample_desc} | Pattern: " . 
                   ($sample_size > 10 ? "Strong" : ($sample_size > 3 ? "Moderate" : "Weak"));
    
    // Special rules
    $current_contract = $employee['current_contract_type'] ?? '';
    if ($current_contract === '3' || $current_contract === 'kontrak3') {
        $reasoning[] = "⚡ Kontrak 3 Rule: Hanya dapat dipromosikan ke Permanent (company policy)";
    }
    
    // Timestamp for real-time indicator
    $reasoning[] = "🕒 Analysis Updated: " . date('Y-m-d H:i:s') . " (Real-time data mining)";
    
    return $reasoning;
}

function calculateDynamicRiskBasedOnProfile($conn, $employee) {
    // Calculate dynamic early resign risk based on employee profile characteristics
    $baseRisk = 20; // Start with base risk
    
    $major = strtolower($employee['major'] ?? '');
    $role = strtolower($employee['role'] ?? '');
    $education = $employee['education_level'] ?? '';
    
    // 1. MAJOR TYPE ADJUSTMENT
    $itMajors = ['information technology', 'information system', 'computer science', 
                 'informatics', 'teknik informatika', 'teknik komputer', 'software engineering'];
    
    $isItMajor = false;
    foreach ($itMajors as $itMajor) {
        if (strpos($major, $itMajor) !== false) {
            $isItMajor = true;
            break;
        }
    }
    
    if ($isItMajor) {
        $baseRisk -= 5; // IT majors tend to be more stable
    } else {
        $baseRisk += 3; // Non-IT majors in IT roles might have higher risk
    }
    
    // 2. ROLE LEVEL ADJUSTMENT
    if (strpos($role, 'manager') !== false || strpos($role, 'head') !== false || strpos($role, 'director') !== false) {
        $baseRisk -= 8; // Management roles more stable
    } elseif (strpos($role, 'senior') !== false || strpos($role, 'sr.') !== false) {
        $baseRisk -= 4; // Senior roles more stable
    } elseif (strpos($role, 'junior') !== false || strpos($role, 'jr.') !== false || strpos($role, 'trainee') !== false) {
        $baseRisk += 6; // Junior roles higher risk
    }
    
    // 3. EDUCATION LEVEL ADJUSTMENT
    if ($education === 'S2' || $education === 'S3') {
        $baseRisk -= 3; // Higher education = more stable
    } elseif ($education === 'D3' || $education === 'D4') {
        $baseRisk += 2; // Lower education = slightly higher risk
    }
    
    // 4. ROLE-MAJOR MATCH ADJUSTMENT
    $roleCategories = [
        'developer' => ['developer', 'programmer', 'coding'],
        'analyst' => ['analyst', 'business analyst'],
        'tester' => ['tester', 'qa', 'quality'],
        'consultant' => ['consultant', 'advisor'],
        'management' => ['manager', 'director', 'head']
    ];
    
    $hasRoleMatch = false;
    foreach ($roleCategories as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($role, $keyword) !== false) {
                if (($category === 'developer' && $isItMajor) ||
                    ($category === 'analyst' && $isItMajor) ||
                    ($category === 'management' && strpos($major, 'management') !== false)) {
                    $baseRisk -= 2; // Good role-major match
                    $hasRoleMatch = true;
                    break 2;
                }
            }
        }
    }
    
    if (!$hasRoleMatch && $isItMajor) {
        $baseRisk += 1; // IT major but unclear role match
    }
    
    // 5. ADD SOME PROFILE-SPECIFIC VARIATION
    // Use employee ID to create consistent but varied risk
    $employeeId = $employee['eid'] ?? 1;
    $variation = ($employeeId % 7) - 3; // Creates variation between -3 to +3
    $baseRisk += $variation;
    
    // 6. ENSURE REASONABLE BOUNDS
    $baseRisk = max(5, min(35, $baseRisk)); // Keep between 5% and 35%
    
    return round($baseRisk, 1);
}

function getResignBeforeContractEndAnalysis($conn, $employee) {
    // ENHANCED RESIGN INTELLIGENCE: Analisis resign berdasarkan profil serupa dengan durasi kontrak spesifik
    $major = $employee['major'] ?? '';
    $role = $employee['role'] ?? '';
    $education_level = $employee['education_level'] ?? '';
    
    // Get duration risk analysis untuk berbagai durasi
    $duration_options = [6, 12, 18, 24];
    $duration_risks = [];
    
    foreach ($duration_options as $duration) {
        $risk_analysis = analyzeResignBeforeContractEndForDynamicIntelligence($conn, $major, $role, $education_level, $duration);
        $duration_risks[$duration] = $risk_analysis;
    }
    
    // Find current optimal duration berdasarkan resign patterns
    $current_contract_type = $employee['current_contract_type'] ?? 'probation';
    $recommended_duration = 12; // Default
    $optimal_duration_analysis = null;
    $lowest_risk_rate = null; // Start with null instead of 100
    
    foreach ($duration_risks as $duration => $risk_data) {
        if ($risk_data['sample_size'] >= 3 && ($lowest_risk_rate === null || $risk_data['early_resign_rate'] < $lowest_risk_rate)) {
            $lowest_risk_rate = $risk_data['early_resign_rate'];
            $recommended_duration = $duration;
            $optimal_duration_analysis = $risk_data;
        }
    }
    
    // If no sufficient data found, calculate dynamic risk based on employee characteristics
    if ($lowest_risk_rate === null) {
        $lowest_risk_rate = calculateDynamicRiskBasedOnProfile($conn, $employee);
    }
    
    // Analisis untuk kontrak type saat ini
    $current_type_analysis = null;
    if ($current_contract_type !== 'none') {
        $current_type_analysis = analyzeCurrentContractTypeResignPattern($conn, $major, $role, $education_level, $current_contract_type);
    }
    
    return [
        'current_contract_analysis' => $current_type_analysis,
        'duration_risk_analysis' => $duration_risks,
        'optimal_duration_recommendation' => [
            'recommended_duration' => $recommended_duration,
            'early_resign_rate' => $lowest_risk_rate,
            'reasoning' => $optimal_duration_analysis ? 
                sprintf('Durasi %d bulan memiliki resign rate terendah (%.1f%%) untuk profil serupa', 
                    $recommended_duration, $lowest_risk_rate) :
                'Tidak ada data resign pattern yang cukup untuk rekomendasi spesifik'
        ],
        'high_risk_durations' => array_filter($duration_risks, function($risk) {
            return $risk['early_resign_rate'] > 30 && $risk['sample_size'] >= 3;
        }),
        'safe_durations' => array_filter($duration_risks, function($risk) {
            return $risk['early_resign_rate'] < 15 && $risk['sample_size'] >= 3;
        }),
        'is_high_risk' => $current_type_analysis ? $current_type_analysis['early_resign_rate'] > 30 : false,
        'analysis_date' => date('Y-m-d H:i:s'),
        'intelligence_summary' => generateResignIntelligenceSummary($duration_risks, $current_type_analysis)
    ];
}

function analyzeResignBeforeContractEndForDynamicIntelligence($db, $major, $role, $education_level, $target_duration_months) {
    try {
        // Query sama dengan yang di employees.php untuk konsistensi
        $sql = "SELECT 
                    c.type as contract_type,
                    TIMESTAMPDIFF(MONTH, c.start_date, e.resign_date) as resign_duration_months,
                    TIMESTAMPDIFF(MONTH, c.start_date, c.end_date) as planned_duration_months,
                    COUNT(*) as resign_count,
                    AVG(TIMESTAMPDIFF(MONTH, c.start_date, e.resign_date)) as avg_resign_duration
                FROM employees e
                JOIN contracts c ON e.eid = c.eid
                WHERE e.status = 'resigned' 
                AND e.major LIKE ? 
                AND e.role LIKE ?
                AND e.education_level = ?
                AND e.resign_date IS NOT NULL
                AND c.end_date IS NOT NULL
                AND TIMESTAMPDIFF(MONTH, c.start_date, c.end_date) = ?
                AND TIMESTAMPDIFF(MONTH, c.start_date, e.resign_date) < TIMESTAMPDIFF(MONTH, c.start_date, c.end_date)
                GROUP BY c.type, TIMESTAMPDIFF(MONTH, c.start_date, c.end_date)";
        
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
                     AND c.end_date IS NOT NULL
                     AND TIMESTAMPDIFF(MONTH, c.start_date, c.end_date) = ?";
        
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
                          ($early_resign_rate > 15 ? 'Medium' : 'Low'),
            'confidence' => $total_contracts >= 10 ? 'High' : 
                          ($total_contracts >= 5 ? 'Medium' : 'Low')
        ];
        
    } catch (Exception $e) {
        return [
            'target_duration_months' => $target_duration_months,
            'early_resign_rate' => 0,
            'total_early_resigns' => 0,
            'total_similar_contracts' => 0,
            'sample_size' => 0,
            'risk_level' => 'Unknown',
            'confidence' => 'None'
        ];
    }
}

function analyzeCurrentContractTypeResignPattern($conn, $major, $role, $education_level, $contract_type) {
    // Analisis resign pattern untuk tipe kontrak saat ini
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_employees,
            COUNT(CASE WHEN e.status = 'resigned' THEN 1 END) as total_resigned,
            COUNT(CASE WHEN e.status = 'resigned' AND c.end_date IS NOT NULL 
                  AND TIMESTAMPDIFF(MONTH, c.start_date, e.resign_date) < TIMESTAMPDIFF(MONTH, c.start_date, c.end_date) 
                  THEN 1 END) as resigned_before_end,
            AVG(CASE WHEN e.status = 'resigned' AND c.end_date IS NOT NULL 
                THEN TIMESTAMPDIFF(MONTH, c.start_date, e.resign_date) END) as avg_resign_timing,
            AVG(TIMESTAMPDIFF(MONTH, c.start_date, c.end_date)) as avg_planned_duration
        FROM employees e
        JOIN contracts c ON e.eid = c.eid
        WHERE e.major LIKE ? 
        AND e.role LIKE ?
        AND e.education_level = ?
        AND c.type = ?
    ");
    
    $major_pattern = "%$major%";
    $role_pattern = "%$role%";
    $stmt->bind_param('ssss', $major_pattern, $role_pattern, $education_level, $contract_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    $total_employees = $data['total_employees'] ?? 0;
    $resigned_before_end = $data['resigned_before_end'] ?? 0;
    $early_resign_rate = $total_employees > 0 ? ($resigned_before_end / $total_employees) * 100 : 0;
    
    return [
        'contract_type' => $contract_type,
        'total_employees' => $total_employees,
        'resigned_before_end' => $resigned_before_end,
        'early_resign_rate' => round($early_resign_rate, 1),
        'avg_resign_timing' => round($data['avg_resign_timing'] ?? 0, 1),
        'avg_planned_duration' => round($data['avg_planned_duration'] ?? 0, 1),
        'sample_size' => $total_employees,
        'risk_level' => $early_resign_rate > 30 ? 'High' : 
                      ($early_resign_rate > 15 ? 'Medium' : 'Low'),
        'confidence' => $total_employees >= 10 ? 'High' : 
                      ($total_employees >= 5 ? 'Medium' : 'Low')
    ];
}

function generateResignIntelligenceSummary($duration_risks, $current_type_analysis) {
    $summary = [];
    
    // Analisis pola resign general
    $high_risk_durations = array_filter($duration_risks, function($risk) {
        return $risk['early_resign_rate'] > 30 && $risk['sample_size'] >= 3;
    });
    
    $safe_durations = array_filter($duration_risks, function($risk) {
        return $risk['early_resign_rate'] < 15 && $risk['sample_size'] >= 3;
    });
    
    if (!empty($high_risk_durations)) {
        $risky_list = implode(', ', array_keys($high_risk_durations));
        $summary[] = "⚠️ High Risk Durations: " . $risky_list . " bulan (>30% resign rate)";
    }
    
    if (!empty($safe_durations)) {
        $safe_list = implode(', ', array_keys($safe_durations));
        $summary[] = "✅ Safe Durations: " . $safe_list . " bulan (<15% resign rate)";
    }
    
    // Analisis kontrak current
    if ($current_type_analysis && $current_type_analysis['sample_size'] >= 3) {
        $rate = $current_type_analysis['early_resign_rate'];
        $type = $current_type_analysis['contract_type'];
        
        if ($rate > 30) {
            $summary[] = "🚨 Current contract type '{$type}' has high resign risk ({$rate}%)";
        } elseif ($rate < 15) {
            $summary[] = "👍 Current contract type '{$type}' has low resign risk ({$rate}%)";
        } else {
            $summary[] = "⚡ Current contract type '{$type}' has medium resign risk ({$rate}%)";
        }
    }
    
    if (empty($summary)) {
        $summary[] = "📊 Insufficient data for detailed resign pattern analysis";
    }
    
    return $summary;
}

?> 