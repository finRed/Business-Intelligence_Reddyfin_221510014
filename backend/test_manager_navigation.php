<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

echo "<h2>Manager Navigation Debug Test</h2>";

echo "<h3>1. Session Status</h3>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session data: " . print_r($_SESSION, true) . "\n";
echo "</pre>";

echo "<h3>2. User Authentication</h3>";
if (isset($_SESSION['user'])) {
    echo "<div style='color: green'>✅ User is logged in</div>";
    echo "<pre>";
    echo "User ID: " . $_SESSION['user']['uid'] . "\n";
    echo "Username: " . $_SESSION['user']['username'] . "\n";
    echo "Role: " . $_SESSION['user']['role'] . "\n";
    echo "Division ID: " . $_SESSION['user']['division_id'] . "\n";
    echo "Division Name: " . ($_SESSION['user']['division_name'] ?? 'Not set') . "\n";
    echo "</pre>";
} else {
    echo "<div style='color: red'>❌ User not logged in</div>";
    echo "<a href='test_login_manager.php'>Login as Manager</a>";
    exit;
}

if ($_SESSION['user']['role'] !== 'manager') {
    echo "<div style='color: red'>❌ User is not a manager (role: " . $_SESSION['user']['role'] . ")</div>";
    exit;
}

echo "<h3>3. Database Connection Test</h3>";
try {
    require_once 'config/db.php';
    echo "<div style='color: green'>✅ Database connection successful</div>";
} catch (Exception $e) {
    echo "<div style='color: red'>❌ Database connection failed: " . $e->getMessage() . "</div>";
    exit;
}

echo "<h3>4. Manager Employee Data Test</h3>";
$division_id = $_SESSION['user']['division_id'];
$query = "SELECT eid, name, role, email FROM employees WHERE division_id = ? AND status = 'active' LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $division_id);
$stmt->execute();
$result = $stmt->get_result();
$employees = $result->fetch_all(MYSQLI_ASSOC);

if (count($employees) > 0) {
    echo "<div style='color: green'>✅ Found " . count($employees) . " employees in division</div>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>EID</th><th>Name</th><th>Role</th><th>Email</th><th>Test Navigation URLs</th></tr>";
    
    foreach ($employees as $emp) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($emp['eid']) . "</td>";
        echo "<td>" . htmlspecialchars($emp['name']) . "</td>";
        echo "<td>" . htmlspecialchars($emp['role']) . "</td>";
        echo "<td>" . htmlspecialchars($emp['email']) . "</td>";
        echo "<td>";
        echo "<strong>Detail URL:</strong> <code>/manager/employee/" . $emp['eid'] . "</code><br>";
        echo "<strong>Recommendation URL:</strong> <code>/manager/recommendation/" . $emp['eid'] . "</code>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div style='color: red'>❌ No employees found in division " . $division_id . "</div>";
}

echo "<h3>5. Frontend Navigation Test</h3>";
echo "<p>To test navigation:</p>";
echo "<ol>";
echo "<li>Open browser developer tools (F12)</li>";
echo "<li>Go to Manager Dashboard in React app</li>";
echo "<li>Click on Detail or Rekomendasi button</li>";
echo "<li>Check console for navigation logs</li>";
echo "<li>Check Network tab for any failed requests</li>";
echo "</ol>";

echo "<h3>6. Expected Behavior</h3>";
echo "<ul>";
echo "<li><strong>Detail button:</strong> Should navigate to <code>/manager/employee/{eid}</code></li>";
echo "<li><strong>Rekomendasi button:</strong> Should navigate to <code>/manager/recommendation/{eid}</code></li>";
echo "<li><strong>Routes in App.js:</strong>";
echo "<ul>";
echo "<li><code>/manager/employee/:eid → EmployeeDetail</code></li>";
echo "<li><code>/manager/recommendation/:eid → ContractRecommendation</code></li>";
echo "</ul>";
echo "</li>";
echo "</ul>";

echo "<h3>7. Troubleshooting Steps</h3>";
echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
echo "<p><strong>If redirecting to Profile instead:</strong></p>";
echo "<ol>";
echo "<li>Check browser console for JavaScript errors</li>";
echo "<li>Verify the navigation functions are being called correctly</li>";
echo "<li>Check if there are any error boundaries redirecting to Profile</li>";
echo "<li>Verify the routes are properly configured in App.js</li>";
echo "<li>Check if EmployeeDetail or ContractRecommendation components have redirect logic</li>";
echo "</ol>";
echo "</div>";

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; }
table { border-collapse: collapse; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style> 