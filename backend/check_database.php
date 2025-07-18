<?php
require_once('config/db.php');

echo "🔍 Database Structure and Anton Wijaya Data Check\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// Check employees table structure
echo "📋 Employees table structure:\n";
$result = $conn->query("DESCRIBE employees");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "   {$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']}\n";
    }
} else {
    echo "❌ Failed to get table structure\n";
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Find Anton Wijaya
echo "🔍 Anton Wijaya data:\n";
$stmt = $conn->prepare("SELECT eid, name, role, major, education_level FROM employees WHERE name LIKE ?");
$search_name = "%Anton Wijaya%";
$stmt->bind_param("s", $search_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $anton = $result->fetch_assoc();
    echo "   EID: {$anton['eid']}\n";
    echo "   Name: {$anton['name']}\n";
    echo "   Role: '{$anton['role']}'\n";
    echo "   Major: '{$anton['major']}'\n";
    echo "   Education Level: '{$anton['education_level']}'\n";
    echo "   NOTE: education_job_match is calculated dynamically, not stored\n";
} else {
    echo "❌ Anton Wijaya not found\n";
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Check all employees with MANAGEMENT education to see patterns
echo "🔍 All employees with MANAGEMENT education:\n";
$result = $conn->query("SELECT name, role, major FROM employees WHERE major LIKE '%MANAGEMENT%' AND status = 'active'");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "   {$row['name']} | {$row['role']} | {$row['major']}\n";
    }
} else {
    echo "   No employees with MANAGEMENT education found\n";
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Check employees with developer roles to see their education match pattern
echo "🔍 Developer roles and their education:\n";
$result = $conn->query("SELECT name, role, major FROM employees WHERE role LIKE '%Developer%' AND status = 'active' LIMIT 10");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "   {$row['name']} | {$row['role']} | {$row['major']}\n";
    }
} else {
    echo "   No developer roles found\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Database check completed!\n";
?> 