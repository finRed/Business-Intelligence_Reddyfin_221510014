<?php
// Configure session before starting
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0'); 
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_lifetime', 86400);

session_start();
require_once 'config/db.php';

echo "<h2>Debug Login Test</h2>";

if ($_POST) {
    echo "<h3>POST Data Received:</h3>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        echo "<h3>Testing Login for: $username</h3>";
        
        $db = new Database();
        
        try {
            $stmt = $db->prepare("SELECT user_id, username, email, password, role, division_id, status FROM users WHERE username = ? AND status = 'active'");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo "<p style='color: red;'>❌ User tidak ditemukan atau tidak aktif</p>";
            } else {
                $user = $result->fetch_assoc();
                echo "<h4>User Data Found:</h4>";
                echo "<pre>" . print_r($user, true) . "</pre>";
                
                if (password_verify($password, $user['password'])) {
                    echo "<p style='color: green;'>✅ Password BENAR!</p>";
                    
                    // Set session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['division_id'] = $user['division_id'];
                    $_SESSION['logged_in'] = true;
                    
                    echo "<h4>Session Set:</h4>";
                    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
                    
                    echo "<p style='color: green;'>✅ LOGIN BERHASIL!</p>";
                } else {
                    echo "<p style='color: red;'>❌ Password SALAH!</p>";
                    echo "<p>Hash in DB: " . $user['password'] . "</p>";
                    echo "<p>Input Password: " . $password . "</p>";
                    echo "<p>Verify Result: " . (password_verify($password, $user['password']) ? 'TRUE' : 'FALSE') . "</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
        }
    }
} else {
    echo "<p>No POST data received. Use the form below to test:</p>";
}
?>

<form method="POST">
    <div style="margin: 10px 0;">
        <label>Username:</label><br>
        <input type="text" name="username" value="admin" style="padding: 5px; width: 200px;">
    </div>
    <div style="margin: 10px 0;">
        <label>Password:</label><br>
        <input type="password" name="password" value="password" style="padding: 5px; width: 200px;">
    </div>
    <div style="margin: 10px 0;">
        <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer;">Test Login</button>
    </div>
</form>

<h3>Current Session:</h3>
<pre><?php print_r($_SESSION); ?></pre>

<h3>Database Connection Test:</h3>
<?php
try {
    $db = new Database();
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    echo "<p>Admin user count: $count</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}
?> 