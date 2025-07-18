<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: text/html');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

echo "<h2>✅ Navigation Fixed - Final Test</h2>";

echo "<h3>1. Current Status</h3>";
if (isset($_SESSION['user'])) {
    echo "<div style='color: green'>✅ User logged in: " . $_SESSION['user']['username'] . " (Role: " . $_SESSION['user']['role'] . ")</div>";
    echo "<div style='color: green'>✅ Division: " . $_SESSION['user']['division_id'] . " - " . ($_SESSION['user']['division_name'] ?? 'Unknown') . "</div>";
} else {
    echo "<div style='color: red'>❌ User not logged in - <a href='test_login_manager.php'>Login as Manager</a></div>";
    exit;
}

echo "<h3>2. Navigation Routes Fixed</h3>";
echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h4>✅ Routes Configuration:</h4>";
echo "<ul>";
echo "<li><strong>Manager Dashboard:</strong> <code>/dashboard</code> → ManagerDashboard.js</li>";
echo "<li><strong>Employee Detail:</strong> <code>/manager/employee/{eid}</code> → EmployeeDetail.js</li>";
echo "<li><strong>Contract Recommendation:</strong> <code>/manager/recommendation/{eid}</code> → ContractRecommendation.js</li>";
echo "</ul>";
echo "</div>";

echo "<h3>3. Smart Navigation Features</h3>";
echo "<div style='background: #f8fff8; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h4>✅ Navigation Improvements:</h4>";
echo "<ul>";
echo "<li><strong>Detail Button:</strong> Goes to EmployeeDetail.js with manager context</li>";
echo "<li><strong>Rekomendasi Button:</strong> Goes to ContractRecommendation.js with smart back navigation</li>";
echo "<li><strong>Back Navigation:</strong> Context-aware (Manager → Dashboard, HR → Employees)</li>";
echo "<li><strong>Role-based UI:</strong> Different buttons/actions based on user role</li>";
echo "</ul>";
echo "</div>";

echo "<h3>4. Test Your Navigation Now</h3>";
echo "<div style='background: #fff8f0; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h4>🧪 Testing Steps:</h4>";
echo "<ol>";
echo "<li><strong>Go to Manager Dashboard:</strong> <a href='http://localhost:3000/dashboard' target='_blank'>http://localhost:3000/dashboard</a></li>";
echo "<li><strong>Click Detail button</strong> on any employee → Should go to EmployeeDetail.js</li>";
echo "<li><strong>Click Rekomendasi button</strong> on any employee → Should go to ContractRecommendation.js</li>";
echo "<li><strong>Test back navigation</strong> → Should return to Dashboard</li>";
echo "</ol>";
echo "</div>";

echo "<h3>5. Debugging Console</h3>";
echo "<div style='background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h4>📝 Console Logs to Check:</h4>";
echo "<pre>";
echo "=== DETAIL BUTTON CLICKED ===
Employee ID (eid): E001
Target URL will be: /manager/employee/E001
Navigation called successfully

=== REKOMENDASI BUTTON CLICKED ===
Employee ID (eid): E001  
Target URL will be: /manager/recommendation/E001
Navigation called successfully";
echo "</pre>";
echo "</div>";

echo "<h3>6. Success Indicators</h3>";
echo "<div style='background: #f0fff0; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h4>✅ Navigation Should Work If:</h4>";
echo "<ul>";
echo "<li>Detail button shows employee information page (EmployeeDetail.js)</li>";
echo "<li>Rekomendasi button shows contract recommendation form (ContractRecommendation.js)</li>";
echo "<li>Back buttons return to Manager Dashboard</li>";
echo "<li>No more redirects to Profile page</li>";
echo "<li>Console shows proper navigation logs</li>";
echo "</ul>";
echo "</div>";

// Test employee data for reference
require_once 'config/db.php';
$division_id = $_SESSION['user']['division_id'];
$query = "SELECT eid, name, role FROM employees WHERE division_id = ? AND status = 'active' LIMIT 3";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $division_id);
$stmt->execute();
$result = $stmt->get_result();
$employees = $result->fetch_all(MYSQLI_ASSOC);

if (count($employees) > 0) {
    echo "<h3>7. Test URLs for Your Employees</h3>";
    echo "<div style='background: #fff5f5; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Employee</th><th>Detail URL</th><th>Recommendation URL</th></tr>";
    
    foreach ($employees as $emp) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($emp['name']) . " (" . htmlspecialchars($emp['eid']) . ")</td>";
        echo "<td><a href='http://localhost:3000/manager/employee/" . htmlspecialchars($emp['eid']) . "' target='_blank'>Detail Page</a></td>";
        echo "<td><a href='http://localhost:3000/manager/recommendation/" . htmlspecialchars($emp['eid']) . "' target='_blank'>Recommendation Page</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3, h4 { color: #333; }
pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style> 