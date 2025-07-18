<?php
$conn = new mysqli('localhost', 'root', '', 'contract_rec_db');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "CHECKING USERS:\n";
echo "===============\n\n";

// Check all users
$result = $conn->query("SELECT user_id, username, role, division_id FROM users ORDER BY user_id");
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['user_id']}, Username: {$row['username']}, Role: {$row['role']}, Division: {$row['division_id']}\n";
}

echo "\nCHECKING DIVISIONS:\n";
echo "==================\n\n";

// Check divisions
$result = $conn->query("SELECT division_id, division_name FROM divisions ORDER BY division_id");
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['division_id']}, Name: {$row['division_name']}\n";
}

echo "\nCHECKING EMPLOYEES:\n";
echo "==================\n\n";

// Check employees count by division
$result = $conn->query("
    SELECT d.division_name, COUNT(e.eid) as employee_count 
    FROM divisions d 
    LEFT JOIN employees e ON d.division_id = e.division_id AND e.status = 'active'
    GROUP BY d.division_id, d.division_name
    ORDER BY d.division_id
");

while ($row = $result->fetch_assoc()) {
    echo "Division: {$row['division_name']}, Employees: {$row['employee_count']}\n";
}

$conn->close();
?> 