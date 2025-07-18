<?php
session_start();
require_once 'config/db.php';

echo "<h2>Session Test After Login</h2>";

// Check current session
echo "<h3>Current Session:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    echo "<div style='color: green;'>✓ Session is ACTIVE</div>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Role: " . $_SESSION['role'] . "</p>";
    echo "<p>Username: " . $_SESSION['username'] . "</p>";
    
    // Test add user function
    echo "<h3>Test Add User Function:</h3>";
    if ($_SESSION['role'] === 'admin') {
        echo "<div style='color: green;'>✓ Admin role confirmed - can add users</div>";
        
        // Show form to test add user
        if ($_POST && isset($_POST['test_add_user'])) {
            try {
                $db = new Database();
                
                $username = 'test_' . time();
                $email = 'test_' . time() . '@example.com';
                $password = password_hash('password123', PASSWORD_DEFAULT);
                $role = 'hr';
                $division_id = 1;
                
                // Insert new user
                $stmt = $db->prepare("INSERT INTO users (username, email, password, role, division_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssi', $username, $email, $password, $role, $division_id);
                
                if ($stmt->execute()) {
                    echo "<div style='color: green;'>✓ SUCCESS: Test user added with ID: " . $db->lastInsertId() . "</div>";
                } else {
                    echo "<div style='color: red;'>✗ FAILED: Could not add test user</div>";
                }
                
            } catch (Exception $e) {
                echo "<div style='color: red;'>✗ ERROR: " . $e->getMessage() . "</div>";
            }
        } else {
            echo "<form method='POST'>";
            echo "<button type='submit' name='test_add_user' value='1'>Test Add User</button>";
            echo "</form>";
        }
    } else {
        echo "<div style='color: orange;'>⚠ Not admin role - cannot add users</div>";
    }
    
} else {
    echo "<div style='color: red;'>✗ Session is NOT ACTIVE</div>";
    echo "<p>Please login first via the web interface</p>";
    echo "<p><a href='http://localhost:3000/login'>Go to Login Page</a></p>";
}

echo "<hr>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";
?> 