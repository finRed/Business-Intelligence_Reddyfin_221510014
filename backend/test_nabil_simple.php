<?php
echo "🔍 Testing Nabil Hamdy Education Keywords\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Test data
$education = 'TECHONOLOGY INFORMATION';  // Note: typo in original
$role = 'Quality Assurance Manual';

echo "📚 Education: '{$education}'\n";
echo "👔 Role: '{$role}'\n\n";

// Education keywords
$it_education_keywords = [
    'COMPUTER', 'KOMPUTER', 'INFORMATIKA', 'INFORMATICS', 'INFORMATION', 'SISTEM', 'SYSTEM',
    'SOFTWARE', 'TECHNOLOGY', 'TEKNOLOGI', 'PROGRAMMING', 'CODING', 'ENGINEERING',
    'SCIENCE', 'MATH', 'MATHEMATICS', 'STATISTICS', 'DATA', 'NETWORK', 'DIGITAL',
    'CYBER', 'ELEKTRONIK', 'ELECTRONIC', 'TELEKOMUNIKASI', 'TELECOMMUNICATION'
];

// Role keywords
$it_role_keywords = [
    'DEVELOPER', 'PROGRAMMER', 'ENGINEER', 'ANALYST', 'ADMIN', 'DESIGNER',
    'MANAGER', 'COORDINATOR', 'SPECIALIST', 'TECHNICIAN', 'CONSULTANT',
    'ARCHITECT', 'LEAD', 'SENIOR', 'JUNIOR', 'FULL STACK', 'BACKEND', 'FRONTEND'
];

// Check education match
echo "🔍 Checking education keywords in '{$education}':\n";
$education_match = false;
$found_edu_keywords = [];
foreach ($it_education_keywords as $keyword) {
    if (strpos($education, $keyword) !== false) {
        $education_match = true;
        $found_edu_keywords[] = $keyword;
        echo "   ✅ Found: {$keyword}\n";
    }
}

if (!$education_match) {
    echo "   ❌ No IT education keywords found\n";
}

// Check role match
echo "\n🔍 Checking role keywords in '{$role}':\n";
$role_match = false;
$found_role_keywords = [];
foreach ($it_role_keywords as $keyword) {
    if (strpos($role, $keyword) !== false) {
        $role_match = true;
        $found_role_keywords[] = $keyword;
        echo "   ✅ Found: {$keyword}\n";
    }
}

if (!$role_match) {
    echo "   ❌ No IT role keywords found\n";
}

// Calculate score
$score = 0;
if ($education_match && $role_match) {
    $score = 3; // Perfect match
} elseif ($education_match || $role_match) {
    $score = 2; // Good match
} else {
    $score = 1; // Basic match
}

echo "\n📊 Results:\n";
echo "   Education Match: " . ($education_match ? 'YES' : 'NO') . "\n";
echo "   Role Match: " . ($role_match ? 'YES' : 'NO') . "\n";
echo "   Score: {$score}\n";
echo "   Classification: " . ($score >= 2 ? 'Match' : 'Unmatch') . "\n";

// Test corrected spelling
echo "\n🔄 Testing with corrected spelling:\n";
$corrected_education = 'TECHNOLOGY INFORMATION';
echo "📚 Corrected Education: '{$corrected_education}'\n";

$corrected_match = false;
foreach ($it_education_keywords as $keyword) {
    if (strpos($corrected_education, $keyword) !== false) {
        $corrected_match = true;
        echo "   ✅ Found: {$keyword}\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Analysis completed!\n";
?> 