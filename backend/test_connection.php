<?php
require_once 'config/db.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    
    $tests = [];
    
    // Test 1: Database connection
    $tests['database_connection'] = 'OK';
    
    // Test 2: Check if admin user exists
    $stmt = $db->prepare("SELECT user_id, username, role, status FROM users WHERE username = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $tests['admin_user_exists'] = 'OK';
        $tests['admin_user_data'] = $user;
    } else {
        $tests['admin_user_exists'] = 'FAILED - User not found';
    }
    
    // Test 3: Test password verification
    if ($result->num_rows > 0) {
        $stmt2 = $db->prepare("SELECT password FROM users WHERE username = 'admin'");
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $user_data = $result2->fetch_assoc();
        
        if (password_verify('password', $user_data['password'])) {
            $tests['password_verification'] = 'OK';
        } else {
            $tests['password_verification'] = 'FAILED - Password mismatch';
        }
    }
    
    // Test 4: Check if divisions exist
    $div_stmt = $db->prepare("SELECT COUNT(*) as count FROM divisions");
    $div_stmt->execute();
    $div_result = $div_stmt->get_result();
    $div_count = $div_result->fetch_assoc();
    $tests['divisions_count'] = $div_count['count'];
    
    echo json_encode([
        'success' => true,
        'tests' => $tests,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?> 