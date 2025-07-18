<?php
// Configure session before starting
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0'); 
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_lifetime', 86400);

session_start();
require_once 'config/db.php';

echo "<h2>Test Divisions Flow</h2>";

// Step 1: Login as admin
echo "<h3>Step 1: Login as Admin</h3>";
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['email'] = 'admin@contractrec.com';
$_SESSION['role'] = 'admin';
$_SESSION['division_id'] = null;
$_SESSION['logged_in'] = true;

echo "<p style='color: green;'>✅ Admin logged in</p>";
echo "<pre>Session: " . print_r($_SESSION, true) . "</pre>";

// Step 2: Get current divisions
echo "<h3>Step 2: Get Current Divisions</h3>";
try {
    $db = new Database();
    $stmt = $db->prepare("SELECT division_id, division_name, description FROM divisions ORDER BY division_name");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $divisions = [];
    while ($row = $result->fetch_assoc()) {
        $divisions[] = $row;
    }
    
    echo "<p>Current divisions count: " . count($divisions) . "</p>";
    echo "<pre>" . print_r($divisions, true) . "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Step 3: Test adding new division
echo "<h3>Step 3: Test Adding New Division</h3>";
if (isset($_POST['add_division'])) {
    $divisionName = $_POST['division_name'];
    $description = $_POST['description'];
    
    try {
        // Check if division exists
        $checkStmt = $db->prepare("SELECT division_id FROM divisions WHERE division_name = ?");
        $checkStmt->bind_param('s', $divisionName);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            echo "<p style='color: orange;'>⚠️ Division '$divisionName' already exists</p>";
        } else {
            // Add new division
            $stmt = $db->prepare("INSERT INTO divisions (division_name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $divisionName, $description);
            
            if ($stmt->execute()) {
                $newId = $db->lastInsertId();
                echo "<p style='color: green;'>✅ Division '$divisionName' added successfully with ID: $newId</p>";
                
                // Get updated divisions list
                $stmt = $db->prepare("SELECT division_id, division_name, description FROM divisions ORDER BY division_name");
                $stmt->execute();
                $result = $stmt->get_result();
                
                $updatedDivisions = [];
                while ($row = $result->fetch_assoc()) {
                    $updatedDivisions[] = $row;
                }
                
                echo "<h4>Updated Divisions List:</h4>";
                echo "<pre>" . print_r($updatedDivisions, true) . "</pre>";
                
            } else {
                echo "<p style='color: red;'>❌ Failed to add division</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
}
?>

<form method="POST">
    <div style="margin: 10px 0;">
        <label><strong>Nama Divisi:</strong></label><br>
        <input type="text" name="division_name" value="IT Developer" style="padding: 5px; width: 300px;" required>
    </div>
    <div style="margin: 10px 0;">
        <label><strong>Deskripsi:</strong></label><br>
        <textarea name="description" style="padding: 5px; width: 300px; height: 60px;" placeholder="Opsional - kosongkan jika tidak ada deskripsi"></textarea>
    </div>
    <div style="margin: 10px 0;">
        <button type="submit" name="add_division" style="padding: 10px 20px; background: #28a745; color: white; border: none; cursor: pointer;">
            Tambah Divisi
        </button>
    </div>
</form>

<h3>Step 4: Test API Endpoint</h3>
<?php
// Test divisions API endpoint
$apiUrl = 'http://localhost/web_srk_BI/backend/api/divisions.php';

try {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Cookie: " . session_name() . "=" . session_id()
        ]
    ]);
    
    $response = file_get_contents($apiUrl, false, $context);
    
    if ($response !== false) {
        echo "<p style='color: green;'>✅ API endpoint accessible</p>";
        echo "<h4>API Response:</h4>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ Failed to access API endpoint</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ API Error: " . $e->getMessage() . "</p>";
}
?> 