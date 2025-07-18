<?php
// Test API directly to see the 400 error
session_start();
require_once('config/db.php');

echo "🔬 Testing API Direct Response\n";
echo "=" . str_repeat("=", 40) . "\n\n";

// Set up manager session
$sql = "SELECT user_id, username, role, division_id FROM users WHERE role = 'manager' LIMIT 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $manager = $result->fetch_assoc();
    $_SESSION['user_id'] = $manager['user_id'];
    $_SESSION['user_role'] = $manager['role'];
    $_SESSION['division_id'] = $manager['division_id'];
    $_SESSION['logged_in'] = true;
    
    echo "✅ Manager session set: {$manager['username']} (Division {$manager['division_id']})\n\n";
} else {
    echo "❌ No manager found\n";
    exit;
}

echo "📡 Testing API call...\n";

// Test using cURL to simulate exact request
$url = 'http://localhost/web_srk_BI/backend/api/manager_analytics.php?t=' . time();

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Origin: http://localhost:3000',
    'Cookie: ' . session_name() . '=' . session_id()
]);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

curl_close($ch);

$headers = substr($response, 0, $header_size);
$body = substr($response, $header_size);

echo "📊 Response Details:\n";
echo "HTTP Code: {$http_code}\n";
echo "Response Length: " . strlen($body) . " bytes\n\n";

echo "📋 Response Headers:\n";
echo $headers . "\n";

echo "📄 Response Body:\n";
echo $body . "\n";

// Try to decode JSON
if (!empty($body)) {
    $json_data = json_decode($body, true);
    if ($json_data) {
        echo "\n🔍 Parsed JSON:\n";
        echo "Success: " . ($json_data['success'] ? 'true' : 'false') . "\n";
        if (isset($json_data['error'])) {
            echo "Error: " . $json_data['error'] . "\n";
        }
        if (isset($json_data['data'])) {
            echo "Data structure present: yes\n";
        }
    } else {
        echo "\n❌ Invalid JSON response\n";
        echo "JSON Error: " . json_last_error_msg() . "\n";
    }
}
?> 