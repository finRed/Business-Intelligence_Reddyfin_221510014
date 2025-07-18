<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config/pdo.php';
session_start();

// Fixed session for testing (remove in production)
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manager') {
    $_SESSION['logged_in'] = true;
    $_SESSION['role'] = 'manager';
    $_SESSION['division_id'] = 8; // IT Developer 
    $_SESSION['user_id'] = 16; // MIT1
    $_SESSION['username'] = 'MIT1';
}

try {
    $division_id = $_SESSION['division_id'];
    
    // Get employees data with enhanced analytics
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
              WHERE e.division_id = ? AND e.status = 'active'
              ORDER BY e.join_date DESC";
    
    $stmt = $pdo->prepare($employees_sql);
    $stmt->execute([$division_id]);
    $employees_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($employees_raw)) {
        throw new Exception('No employees found for division ' . $division_id);
    }
    
    // Enhanced analytics calculation
    $employees = [];
    $total_match_score = 0;
    $high_match_count = 0;
    $high_risk_count = 0;
    $contract_extensions = 0;
    $probation_ready = 0;
    $contracts_ending = 0;
    $permanent_eligible = 0;
    
    foreach ($employees_raw as $emp) {
        // Enhanced education-job match calculation
        $major = strtoupper($emp['major'] ?? '');
        $role = strtoupper($emp['role'] ?? '');
        $education_level = strtoupper($emp['education_level'] ?? '');
        
        $match_score = calculateImprovedMatchScore($major, $role, $education_level);
        $match_level = getMatchLevel($match_score);
        
        // Performance prediction
        $tenure_months = $emp['tenure_months'] ?? 0;
        $success_probability = min(($match_score * 0.6) + (min($tenure_months, 12) * 2), 100);
        $risk_level = $success_probability >= 70 ? 'Low Risk' : ($success_probability >= 50 ? 'Medium Risk' : 'High Risk');
        
        // Contract recommendation
        $recommendation = generateSmartContractRecommendation($match_score, $tenure_months, $success_probability);
        
        // Contract status analysis
        $contract_status = 'No Contract';
        if ($emp['current_contract_type']) {
            if ($emp['current_contract_type'] === 'probation') {
                if ($tenure_months >= 3) {
                    $contract_status = 'Ready for Review';
                    $probation_ready++;
                } else {
                    $contract_status = 'In Probation';
                }
            } else {
                $contract_status = ucfirst($emp['current_contract_type']);
                if ($emp['days_to_contract_end'] !== null && $emp['days_to_contract_end'] <= 60) {
                    $contracts_ending++;
                }
            }
        }
        
        if ($tenure_months >= 36 && $match_score >= 60) {
            $permanent_eligible++;
        }
        
        // Enhanced employee data
        $employee_data = array_merge($emp, [
            'education_job_match' => $match_score,
            'match_level' => $match_level,
            'match_category' => getMatchCategory($match_score),
            'match_details' => getMatchDetails($major, $role, $education_level),
            'success_probability' => round($success_probability, 1),
            'risk_level' => $risk_level,
            'contract_status' => $contract_status,
            'data_mining_recommendation' => $recommendation
        ]);
        
        $employees[] = $employee_data;
        
        // Statistics
        $total_match_score += $match_score;
        if ($match_score >= 70) $high_match_count++;
        if ($risk_level === 'High Risk') $high_risk_count++;
        if ($emp['days_to_contract_end'] !== null && $emp['days_to_contract_end'] <= 60) {
            $contract_extensions++;
        }
    }
    
    $total_employees = count($employees);
    $avg_match_score = $total_employees > 0 ? $total_match_score / $total_employees : 0;
    
    // Generate response
    $response = [
        'success' => true,
        'data' => [
            'employees' => $employees,
            'division_stats' => [
                'total_employees' => $total_employees,
                'division_name' => $employees[0]['division_name'] ?? 'Unknown Division',
                'avg_match_score' => round($avg_match_score, 2),
                'high_match_count' => $high_match_count,
                'contract_extensions' => $contract_extensions,
                'high_risk_count' => $high_risk_count,
                'contract_summary' => [
                    'probation_ready' => $probation_ready,
                    'contracts_ending' => $contracts_ending,
                    'permanent_eligible' => $permanent_eligible
                ],
                'data_mining_summary' => [
                    'avg_education_match' => round($avg_match_score, 2),
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
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Helper functions
function calculateImprovedMatchScore($major, $role, $education_level) {
    $score = 15; // Base score
    $match_details = [];
    
    // Enhanced IT keywords
    $it_education_keywords = [
        'SISTEM INFORMASI', 'TEKNIK INFORMATIKA', 'ILMU KOMPUTER', 
        'COMPUTER SCIENCE', 'SOFTWARE ENGINEERING', 'INFORMATIKA',
        'TEKNOLOGI INFORMASI', 'SI', 'TI'
    ];
    
    $it_role_keywords = [
        'DEVELOPER', 'PROGRAMMER', 'SOFTWARE', 'IT', 'TECHNICAL', 
        'FRONTEND', 'BACKEND', 'FULLSTACK', 'WEB', 'MOBILE',
        'DATABASE', 'SYSTEM', 'NETWORK', 'ANALYST'
    ];
    
    $accounting_education = ['ACCOUNTING', 'AKUNTANSI', 'EKONOMI', 'KEUANGAN', 'FINANCE'];
    $accounting_role = ['AKUNTANSI', 'ACCOUNTING', 'FINANCE', 'KEUANGAN', 'AKUNTAN'];
    
    // Check for perfect matches
    $education_it_match = false;
    $education_accounting_match = false;
    $role_it_match = false;
    $role_accounting_match = false;
    
    foreach ($it_education_keywords as $keyword) {
        if (strpos($major, $keyword) !== false) {
            $education_it_match = true;
            break;
        }
    }
    
    foreach ($accounting_education as $keyword) {
        if (strpos($major, $keyword) !== false) {
            $education_accounting_match = true;
            break;
        }
    }
    
    foreach ($it_role_keywords as $keyword) {
        if (strpos($role, $keyword) !== false) {
            $role_it_match = true;
            break;
        }
    }
    
    foreach ($accounting_role as $keyword) {
        if (strpos($role, $keyword) !== false) {
            $role_accounting_match = true;
            break;
        }
    }
    
    // Scoring logic
    if ($education_it_match && $role_it_match) {
        $score = 85; // Perfect IT match
    } elseif ($education_accounting_match && $role_accounting_match) {
        $score = 80; // Perfect accounting match
    } elseif ($education_it_match || $role_it_match) {
        $score = 60; // Partial IT match
    } elseif ($education_accounting_match || $role_accounting_match) {
        $score = 55; // Partial accounting match
    } else {
        $score = 25; // Base professional score
    }
    
    // Education level bonus
    switch ($education_level) {
        case 'S2':
        case 'MASTER':
            $score += 10;
            break;
        case 'S1':
        case 'BACHELOR':
            $score += 8;
            break;
        case 'D3':
        case 'DIPLOMA':
            $score += 5;
            break;
        default:
            $score += 2;
    }
    
    return min($score, 100);
}

function getMatchLevel($score) {
    if ($score >= 70) return 'Tinggi';
    if ($score >= 50) return 'Sedang';
    return 'Rendah';
}

function getMatchCategory($score) {
    if ($score >= 80) return 'Perfect Match';
    if ($score >= 60) return 'Good Match';
    if ($score >= 40) return 'Fair Match';
    return 'Poor Match';
}

function getMatchDetails($major, $role, $education_level) {
    $details = [];
    
    if (strpos($major, 'SISTEM INFORMASI') !== false || strpos($role, 'IT') !== false) {
        $details[] = 'IT alignment detected';
    }
    if (strpos($major, 'AKUNTANSI') !== false || strpos($role, 'AKUNTANSI') !== false) {
        $details[] = 'Accounting alignment detected';
    }
    if (in_array($education_level, ['S1', 'S2', 'BACHELOR', 'MASTER'])) {
        $details[] = 'Higher education background';
    }
    
    return implode(', ', $details) ?: 'Basic professional profile';
}

function generateSmartContractRecommendation($match_score, $tenure_months, $success_probability) {
    $recommendation = [
        'type' => 'probation',
        'recommended_duration' => 3,
        'reason' => 'Default probation period',
        'confidence' => 50,
        'success_probability' => $success_probability,
        'risk_level' => $success_probability >= 70 ? 'Low' : ($success_probability >= 50 ? 'Medium' : 'High')
    ];
    
    if ($match_score >= 80 && $success_probability >= 80) {
        $recommendation = [
            'type' => 'long_term_contract',
            'recommended_duration' => 24,
            'reason' => 'Excellent match with high success probability',
            'confidence' => 95,
            'success_probability' => $success_probability,
            'risk_level' => 'Low'
        ];
    } elseif ($match_score >= 60 && $success_probability >= 65) {
        $recommendation = [
            'type' => 'standard_contract',
            'recommended_duration' => 18,
            'reason' => 'Good match with satisfactory performance potential',
            'confidence' => 80,
            'success_probability' => $success_probability,
            'risk_level' => 'Low'
        ];
    } elseif ($match_score >= 40 && $success_probability >= 50) {
        $recommendation = [
            'type' => 'short_term_contract',
            'recommended_duration' => 12,
            'reason' => 'Moderate match, requires monitoring',
            'confidence' => 65,
            'success_probability' => $success_probability,
            'risk_level' => 'Medium'
        ];
    } else {
        $recommendation = [
            'type' => 'probation_extension',
            'recommended_duration' => 6,
            'reason' => 'Low match score, extended evaluation needed',
            'confidence' => 40,
            'success_probability' => $success_probability,
            'risk_level' => 'High'
        ];
    }
    
    return $recommendation;
}
?> 