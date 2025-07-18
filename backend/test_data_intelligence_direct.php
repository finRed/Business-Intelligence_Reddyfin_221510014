<?php
require_once 'config/db.php';
require_once 'api/dynamic_intelligence.php';

echo "🧠 DIRECT DATA INTELLIGENCE TEST\n";
echo "================================\n\n";

// Test employee: Henry Triansa Rambakila (Kontrak 3)
$henry = [
    'eid' => 1,
    'name' => 'Henry Triansa Rambakila',
    'major' => 'INFORMATICS ENGINEERING',
    'role' => 'Data Developer',
    'education_level' => 'S1',
    'current_contract_type' => 'kontrak3',
    'tenure_months' => 26.4
];

echo "🔍 TESTING HENRY (KONTRAK 3):\n";
echo "Education: {$henry['major']}\n";
echo "Role: {$henry['role']}\n";
echo "Contract: {$henry['current_contract_type']}\n\n";

// Test education-job match analysis
echo "📊 EDUCATION-JOB MATCH ANALYSIS:\n";
$education_analysis = analyzeEducationJobMatch($henry['major'], $henry['role']);
echo "Match Category: {$education_analysis['match_category']}\n";
echo "Match Score: {$education_analysis['match_score']}\n";
echo "Has IT Education: " . ($education_analysis['has_it_education'] ? 'Yes' : 'No') . "\n";
echo "Details: " . implode(', ', $education_analysis['details']) . "\n\n";

// Test contract progression data
echo "📈 CONTRACT PROGRESSION DATA:\n";
$progression_data = getContractProgressionData($conn, $henry);
echo "Current Contract: {$progression_data['current_contract']}\n";
echo "Tenure: {$progression_data['tenure_months']} bulan\n";
echo "Progression Stage: {$progression_data['progression_stage']}\n";
echo "Total Contracts: {$progression_data['total_contracts']}\n\n";

// Test duration recommendation
echo "🎯 DURATION RECOMMENDATION:\n";
$duration_rec = getDataMiningDurationRecommendation($conn, $henry, $progression_data);
echo "Category: {$duration_rec['category']}\n";
echo "Duration: {$duration_rec['duration']} bulan\n";
echo "Reasoning: {$duration_rec['reasoning']}\n\n";

// Test full Data Intelligence
echo "🧠 FULL DATA INTELLIGENCE:\n";
$intelligence = calculateDynamicIntelligence($conn, $henry);
echo "Match Category: {$intelligence['match_category']}\n";
echo "Optimal Duration: " . ($intelligence['optimal_duration'] ?? 'N/A') . " bulan\n";
echo "Confidence Level: {$intelligence['confidence_level']}\n";
echo "Sample Size: {$intelligence['sample_size']}\n\n";

echo "🔍 REASONING:\n";
foreach ($intelligence['reasoning'] as $reason) {
    echo "• $reason\n";
}

echo "\n" . str_repeat("=", 60) . "\n";

// Test Nicholas (Non-IT education + IT job)
$nicholas = [
    'eid' => 2,
    'name' => 'Nicholas Sudianto',
    'major' => 'KEDOKTERAN',
    'role' => 'Fullstack Developer',
    'education_level' => 'S1',
    'current_contract_type' => 'kontrak2',
    'tenure_months' => 18.2
];

echo "\n🔍 TESTING NICHOLAS (MISMATCH CASE):\n";
echo "Education: {$nicholas['major']}\n";
echo "Role: {$nicholas['role']}\n";
echo "Contract: {$nicholas['current_contract_type']}\n\n";

$nicholas_analysis = analyzeEducationJobMatch($nicholas['major'], $nicholas['role']);
echo "📊 EDUCATION-JOB MATCH:\n";
echo "Match Category: {$nicholas_analysis['match_category']}\n";
echo "Match Score: {$nicholas_analysis['match_score']}\n";
echo "Has IT Education: " . ($nicholas_analysis['has_it_education'] ? 'Yes' : 'No') . "\n";

$nicholas_intelligence = calculateDynamicIntelligence($conn, $nicholas);
echo "\n🧠 DATA INTELLIGENCE:\n";
echo "Match Category: {$nicholas_intelligence['match_category']}\n";
echo "Optimal Duration: " . ($nicholas_intelligence['optimal_duration'] ?? 'N/A') . " bulan\n";

echo "\n✅ TESTING COMPLETED\n";
?> 