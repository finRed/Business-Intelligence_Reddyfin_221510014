<?php
// Test API access from browser
session_start();

// Simulate manager login (like from real login)
if (isset($_GET['login'])) {
    $conn = new mysqli('localhost', 'root', '', 'contract_rec_db');
    $result = $conn->query("SELECT * FROM users WHERE role = 'manager' AND division_id IS NOT NULL LIMIT 1");
    $manager = $result->fetch_assoc();
    
    if ($manager) {
        $_SESSION['user_id'] = $manager['user_id'];
        $_SESSION['username'] = $manager['username'];
        $_SESSION['role'] = $manager['role'];
        $_SESSION['division_id'] = $manager['division_id'];
        
        echo "✅ Manager logged in successfully!<br>";
        echo "Now you can test the API by visiting: <a href='test_browser_api.php?test=1'>Test API</a><br><br>";
        echo "Or test manager analytics: <a href='manager_analytics_by_division.php?division_id=" . $manager['division_id'] . "'>Manager Analytics API</a><br><br>";
        echo "Session data:<br>";
        echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    }
    $conn->close();
    exit;
}

// Test API call
if (isset($_GET['test'])) {
    echo "<h2>🔍 Testing Manager Analytics API</h2>";
    
    if (!isset($_SESSION['user_id'])) {
        echo "❌ No session found. <a href='test_browser_api.php?login=1'>Login first</a>";
        exit;
    }
    
    echo "Session data:<br>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    
    echo "<h3>API Response:</h3>";
    ob_start();
    include 'manager_analytics_by_division.php';
    $output = ob_get_clean();
    
    $response = json_decode($output, true);
    
    if ($response) {
        echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
        
        if ($response['success']) {
            echo "<br><h3>✅ Success Summary:</h3>";
            echo "Total Employees: " . count($response['data']['employees']) . "<br>";
            echo "Division: " . $response['data']['division_stats']['division_name'] . "<br>";
            echo "Match Percentage: " . $response['data']['division_stats']['match_percentage'] . "%<br>";
        }
    } else {
        echo "Raw output:<br><pre>$output</pre>";
    }
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Manager API Test</title>
</head>
<body>
    <h1>🧪 Manager API Browser Test</h1>
    
    <?php if (!isset($_SESSION['user_id'])): ?>
        <p>No active session found.</p>
        <a href="test_browser_api.php?login=1">🔑 Login as Manager</a>
    <?php else: ?>
        <p>✅ Session active for: <?= $_SESSION['username'] ?> (<?= $_SESSION['role'] ?>)</p>
        <a href="test_browser_api.php?test=1">🧪 Test Manager Analytics API</a><br><br>
        <a href="manager_analytics_by_division.php?division_id=<?= $_SESSION['division_id'] ?>">📊 Direct API Call</a><br><br>
        
        <h3>Session Data:</h3>
        <pre><?= print_r($_SESSION, true) ?></pre>
    <?php endif; ?>
</body>
</html> 