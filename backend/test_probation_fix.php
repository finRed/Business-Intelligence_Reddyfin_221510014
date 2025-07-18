<?php
require_once 'config/pdo.php';
require_once 'api/dynamic_intelligence.php';

echo "🧠 TESTING CORRECTED CONTRACT FILTERING\n";
echo "========================================\n\n";

try {
    // Get MySQLi connection
    $mysqli = new mysqli('localhost', 'root', '', 'contract_rec_db');
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    // Find a probation employee
    $stmt = $mysqli->prepare("
        SELECT e.*, c.type as current_contract_type
        FROM employees e 
        JOIN contracts c ON e.eid = c.eid AND c.status = 'active'
        WHERE c.type = 'probation' 
        AND e.status = 'active'
        AND e.major LIKE '%INFORMATICS%'
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $probation_employee = $result->fetch_assoc();
    
    if (!$probation_employee) {
        echo "❌ No probation employee found\n";
        exit;
    }
    
    echo "🔍 TESTING PROBATION EMPLOYEE:\n";
    echo "Name: {$probation_employee['name']}\n";
    echo "Current Contract: {$probation_employee['current_contract_type']}\n";
    echo "Major: {$probation_employee['major']}\n";
    echo "Role: {$probation_employee['role']}\n\n";
    
    // Test dynamic intelligence
    $intelligence = calculateDynamicIntelligence($mysqli, $probation_employee);
    
    echo "📊 INTELLIGENCE RESULTS:\n";
    echo "Match Category: {$intelligence['match_category']}\n";
    echo "Optimal Duration: {$intelligence['optimal_duration']} bulan\n";
    echo "Sample Size: " . ($intelligence['historical_patterns']['sample_size'] ?? 'N/A') . "\n";
    echo "Recommended Contract: " . ($intelligence['historical_patterns']['data_insights']['recommended_contract_type'] ?? 'N/A') . "\n";
    echo "Employee Current: " . ($intelligence['historical_patterns']['data_insights']['employee_current_contract'] ?? 'N/A') . "\n";
    echo "Contract Matches: " . ($intelligence['historical_patterns']['data_insights']['matching_criteria']['contract_matches'] ?? 'N/A') . "\n\n";
    
    // Check profile distribution
    $profiles = $intelligence['historical_patterns']['profiles'] ?? [];
    $contract_distribution = [];
    $recommended_matches = 0;
    $total_profiles = count($profiles);
    $recommended_contract = $intelligence['historical_patterns']['data_insights']['recommended_contract_type'] ?? 'unknown';
    
    echo "📈 PROFILE CONTRACT DISTRIBUTION:\n";
    foreach ($profiles as $profile) {
        $contract_type = $profile['current_contract_type'] ?? 'unknown';
        $contract_distribution[$contract_type] = ($contract_distribution[$contract_type] ?? 0) + 1;
        
        if ($profile['contract_match'] ?? false) {
            $recommended_matches++;
        }
    }
    
    foreach ($contract_distribution as $type => $count) {
        $percentage = round(($count / $total_profiles) * 100, 1);
        $indicator = ($type === $recommended_contract) ? ' 🎯' : '';
        echo "  {$type}: {$count} ({$percentage}%){$indicator}\n";
    }
    
    echo "\n🎯 RECOMMENDED CONTRACT MATCHING:\n";
    echo "Looking for: {$recommended_contract} contract profiles\n";
    echo "Contract Matches: {$recommended_matches}/{$total_profiles}\n";
    $match_percentage = $total_profiles > 0 ? round(($recommended_matches / $total_profiles) * 100, 1) : 0;
    echo "Match Percentage: {$match_percentage}%\n";
    
    // Show top 5 profiles with their contract match status
    echo "\n🔍 TOP 5 PROFILES:\n";
    $top_profiles = array_slice($profiles, 0, 5);
    foreach ($top_profiles as $i => $profile) {
        $score = $profile['similarity_score'] ?? 'N/A';
        $contract_match = ($profile['contract_match'] ?? false) ? '✅' : '❌';
        $contract_type = $profile['current_contract_type'] ?? 'N/A';
        $recommended_type = $profile['recommended_contract_type'] ?? 'N/A';
        
        echo "  " . ($i + 1) . ". {$profile['name']} (Score: {$score})\n";
        echo "     Current Contract: {$contract_type} | Recommended: {$recommended_type} {$contract_match}\n";
        echo "     Status: {$profile['outcome_status']}\n\n";
    }
    
    // Success criteria check
    echo "🏆 SUCCESS CRITERIA:\n";
    if ($recommended_contract === 'permanent' && $match_percentage > 50) {
        echo "✅ SUCCESS: Probation employee correctly getting permanent contract profiles ({$match_percentage}%)\n";
    } elseif ($recommended_contract === 'permanent' && $match_percentage > 0) {
        echo "⚠️ PARTIAL: Some permanent profiles found but low percentage ({$match_percentage}%)\n";
    } elseif ($recommended_contract !== 'permanent') {
        echo "❌ UNEXPECTED: Probation employee not recommended for permanent (got: {$recommended_contract})\n";
    } else {
        echo "❌ FAILED: No permanent contract profiles found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 