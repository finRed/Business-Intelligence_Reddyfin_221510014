<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../python_analysis_integration.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only managers can access this
if ($_SESSION['role'] !== 'manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Manager role required.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pythonAnalysis = new PythonAnalysisIntegration();
    $division_id = $_SESSION['division_id'];
    
    switch ($action) {
        case 'run_complete_analysis':
            runCompleteAnalysis($pythonAnalysis, $division_id);
            break;
            
        case 'education_job_match':
            getEducationJobMatch($pythonAnalysis, $division_id);
            break;
            
        case 'contract_recommendations':
            getContractRecommendations($pythonAnalysis, $division_id);
            break;
            
        case 'performance_predictions':
            getPerformancePredictions($pythonAnalysis, $division_id);
            break;
            
        case 'export_data':
            exportDataForPython($pythonAnalysis, $division_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function runCompleteAnalysis($pythonAnalysis, $division_id) {
    try {
        $results = $pythonAnalysis->getCompleteAnalysis($division_id);
        
        echo json_encode([
            'success' => true,
            'data' => $results,
            'analysis_timestamp' => date('Y-m-d H:i:s'),
            'division_id' => $division_id
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error running complete analysis: ' . $e->getMessage());
    }
}

function getEducationJobMatch($pythonAnalysis, $division_id) {
    try {
        $matches = $pythonAnalysis->calculateEducationJobMatchPHP($division_id);
        
        // Generate distribution statistics
        $distribution = ['Tinggi' => 0, 'Sedang' => 0, 'Rendah' => 0];
        $total_scores = [];
        
        foreach ($matches as $match) {
            $distribution[$match['match_level']]++;
            $total_scores[] = $match['match_score'];
        }
        
        $avg_score = count($total_scores) > 0 ? array_sum($total_scores) / count($total_scores) : 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'matches' => $matches,
                'distribution' => $distribution,
                'statistics' => [
                    'total_employees' => count($matches),
                    'average_match_score' => round($avg_score, 2),
                    'high_match_percentage' => count($matches) > 0 ? round(($distribution['Tinggi'] / count($matches)) * 100, 1) : 0
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error analyzing education-job match: ' . $e->getMessage());
    }
}

function getContractRecommendations($pythonAnalysis, $division_id) {
    try {
        $recommendations = $pythonAnalysis->generateContractRecommendationsPHP($division_id);
        
        // Group by priority
        $by_priority = ['High' => [], 'Medium' => [], 'Low' => []];
        foreach ($recommendations as $rec) {
            $by_priority[$rec['priority']][] = $rec;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'recommendations' => $recommendations,
                'by_priority' => $by_priority,
                'urgent_count' => count($by_priority['High']),
                'total_count' => count($recommendations)
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error generating contract recommendations: ' . $e->getMessage());
    }
}

function getPerformancePredictions($pythonAnalysis, $division_id) {
    try {
        $predictions = $pythonAnalysis->generatePerformancePredictionsPHP($division_id);
        
        // Group by risk level
        $by_risk = ['Low' => [], 'Medium' => [], 'High' => []];
        foreach ($predictions as $pred) {
            $by_risk[$pred['risk_level']][] = $pred;
        }
        
        // Calculate division health
        $total = count($predictions);
        $health_score = $total > 0 ? (count($by_risk['Low']) / $total) * 100 : 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'predictions' => $predictions,
                'by_risk_level' => $by_risk,
                'division_health' => [
                    'health_score' => round($health_score, 1),
                    'health_status' => $health_score >= 70 ? 'Excellent' : ($health_score >= 50 ? 'Good' : 'Needs Attention'),
                    'low_risk_count' => count($by_risk['Low']),
                    'medium_risk_count' => count($by_risk['Medium']),
                    'high_risk_count' => count($by_risk['High'])
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error generating performance predictions: ' . $e->getMessage());
    }
}

function exportDataForPython($pythonAnalysis, $division_id) {
    try {
        $csv_file = $pythonAnalysis->exportEmployeeDataForAnalysis($division_id);
        
        // Return file info instead of the file itself for security
        echo json_encode([
            'success' => true,
            'data' => [
                'message' => 'Data exported successfully',
                'file_created' => file_exists($csv_file),
                'export_timestamp' => date('Y-m-d H:i:s'),
                'division_id' => $division_id
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error exporting data: ' . $e->getMessage());
    }
}
?> 