<?php
// Configure session before starting
if (session_status() === PHP_SESSION_NONE) {
    // Set session configuration
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '0'); 
    ini_set('session.cookie_httponly', '0'); // Allow JavaScript access for cross-origin
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.cookie_domain', 'localhost');
    
    session_start();
}

// Set CORS headers for API requests
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
} else {
    header("Access-Control-Allow-Origin: http://localhost:3000");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set content type for API responses
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

// Debug session info
error_log("Session data: " . print_r($_SESSION, true));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        $action = $_POST['action'] ?? '';
        
        if ($action === 'login') {
            login($db);
        } elseif ($action === 'logout') {
            logout();
        } elseif ($action === 'register') {
            register($db);
        } elseif ($action === 'update_user') {
            updateUser($db);
        } elseif ($action === 'delete_user') {
            deleteUser($db);
        } elseif ($action === 'update_profile') {
            updateProfile($db);
        }
        break;
        
    case 'GET':
        $action = $_GET['action'] ?? '';
        if ($action === 'users') {
            getUsers($db);
        } else {
            checkSession();
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function login($db) {
    try {
        if (!isset($_POST['username']) || !isset($_POST['password'])) {
            throw new Exception('Username dan password harus diisi');
        }
        
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        $stmt = $db->prepare("SELECT user_id, username, email, password, role, division_id, status FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Username tidak ditemukan atau akun tidak aktif');
        }
        
        $user = $result->fetch_assoc();
        
        // Verify password (default password is 'password' for admin)
        if (!password_verify($password, $user['password'])) {
            throw new Exception('Password salah');
        }
        
        // Set session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['division_id'] = $user['division_id'];
        $_SESSION['logged_in'] = true;
        
        // Get division info if exists
        $division_name = null;
        if ($user['division_id']) {
            $div_stmt = $db->prepare("SELECT division_name FROM divisions WHERE division_id = ?");
            $div_stmt->bind_param('i', $user['division_id']);
            $div_stmt->execute();
            $div_result = $div_stmt->get_result();
            if ($div_result->num_rows > 0) {
                $division = $div_result->fetch_assoc();
                $division_name = $division['division_name'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Login berhasil',
            'user' => [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'division_id' => $user['division_id'],
                'division_name' => $division_name
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function logout() {
    session_destroy();
    echo json_encode([
        'success' => true,
        'message' => 'Logout berhasil'
    ]);
}

function register($db) {
    try {
        // Debug session
        error_log("Register function - Session: " . print_r($_SESSION, true));
        error_log("Register function - POST: " . print_r($_POST, true));
        
        // Check if session exists
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            throw new Exception('Session tidak aktif. Silakan login kembali.');
        }
        
        // Only admin can register new users
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            throw new Exception('Hanya admin yang dapat mendaftarkan user baru');
        }
        
        if (!isset($_POST['username']) || !isset($_POST['email']) || !isset($_POST['password']) || !isset($_POST['role'])) {
            throw new Exception('Semua field harus diisi');
        }
        
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        
        // Handle division_id properly - convert empty string to NULL
        $division_id = null;
        if (isset($_POST['division_id']) && !empty($_POST['division_id']) && $_POST['division_id'] !== '') {
            $division_id = (int)$_POST['division_id'];
        }
        
        error_log("Processing user registration: username=$username, email=$email, role=$role, division_id=" . ($division_id ? $division_id : 'NULL'));
        
        // Validate role
        if (!in_array($role, ['admin', 'hr', 'manager'])) {
            throw new Exception('Role tidak valid');
        }
        
        // Check if username or email already exists among active users
        $check_stmt = $db->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
        $check_stmt->bind_param('ss', $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception('Username atau email sudah digunakan');
        }
        
        // Insert new user
        $stmt = $db->prepare("INSERT INTO users (username, email, password, role, division_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssi', $username, $email, $password, $role, $division_id);
        
        if ($stmt->execute()) {
            $newUserId = $db->lastInsertId();
            error_log("User successfully created with ID: $newUserId");
            
            echo json_encode([
                'success' => true,
                'message' => 'User berhasil didaftarkan',
                'user_id' => $newUserId
            ]);
        } else {
            error_log("Failed to insert user: " . $stmt->error);
            throw new Exception('Gagal mendaftarkan user: ' . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Register error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getUsers($db) {
    try {
        // Check if user is logged in and has admin role
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            throw new Exception('Session tidak aktif');
        }
        
        if ($_SESSION['role'] !== 'admin') {
            throw new Exception('Hanya admin yang dapat mengakses data users');
        }
        
        $stmt = $db->prepare("
            SELECT u.user_id, u.username, u.email, u.role, u.status, 
                   u.created_at, u.division_id, d.division_name 
            FROM users u 
            LEFT JOIN divisions d ON u.division_id = d.division_id 
            WHERE u.status = 'active'
            ORDER BY u.created_at DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'id' => $row['user_id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'role' => $row['role'],
                'status' => $row['status'],
                'division_id' => $row['division_id'],
                'division_name' => $row['division_name'],
                'created_at' => $row['created_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $users
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function updateUser($db) {
    try {
        // Check if user is logged in and has admin role
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            throw new Exception('Session tidak aktif');
        }
        
        if ($_SESSION['role'] !== 'admin') {
            throw new Exception('Hanya admin yang dapat mengupdate user');
        }
        
        if (!isset($_POST['user_id']) || !isset($_POST['username']) || !isset($_POST['email']) || !isset($_POST['role'])) {
            throw new Exception('Data user tidak lengkap');
        }
        
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $status = $_POST['status'] ?? 'active';
        
        // Handle division_id properly - convert empty string to NULL
        $division_id = null;
        if (isset($_POST['division_id']) && !empty($_POST['division_id']) && $_POST['division_id'] !== '') {
            $division_id = (int)$_POST['division_id'];
        }
        
        // Validate role
        if (!in_array($role, ['admin', 'hr', 'manager'])) {
            throw new Exception('Role tidak valid');
        }
        
        // Check if username or email already exists for other active users
        $check_stmt = $db->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ? AND status = 'active'");
        $check_stmt->bind_param('ssi', $username, $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception('Username atau email sudah digunakan oleh user lain');
        }
        
        // Update user
        $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, role = ?, division_id = ?, status = ? WHERE user_id = ?");
        $stmt->bind_param('sssisi', $username, $email, $role, $division_id, $status, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'User berhasil diupdate'
            ]);
        } else {
            throw new Exception('Gagal mengupdate user');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function deleteUser($db) {
    try {
        // Debug session
        error_log("Delete function - Session: " . print_r($_SESSION, true));
        error_log("Delete function - POST: " . print_r($_POST, true));
        
        // Check if user is logged in and has admin role
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            throw new Exception('Session tidak aktif. Silakan login kembali.');
        }
        
        if ($_SESSION['role'] !== 'admin') {
            throw new Exception('Hanya admin yang dapat menghapus user');
        }
        
        if (!isset($_POST['user_id'])) {
            throw new Exception('User ID tidak ditemukan');
        }
        
        $user_id = $_POST['user_id'];
        
        // Prevent admin from deleting themselves
        if ($user_id == $_SESSION['user_id']) {
            throw new Exception('Anda tidak dapat menghapus akun sendiri');
        }
        
        // Delete user
        $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'User berhasil dihapus'
            ]);
        } else {
            throw new Exception('Gagal menghapus user');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function updateProfile($db) {
    try {
        // Check if user is logged in
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            throw new Exception('Session tidak aktif');
        }
        
        if (!isset($_POST['username']) || !isset($_POST['email'])) {
            throw new Exception('Username dan email harus diisi');
        }
        
        $user_id = $_SESSION['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        // Check if username or email already exists for other active users
        $check_stmt = $db->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ? AND status = 'active'");
        $check_stmt->bind_param('ssi', $username, $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception('Username atau email sudah digunakan oleh user lain');
        }
        
        // If password change is requested, verify current password
        if ($new_password) {
            if (!$current_password) {
                throw new Exception('Password saat ini harus diisi untuk mengubah password');
            }
            
            // Get current password hash
            $pass_stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
            $pass_stmt->bind_param('i', $user_id);
            $pass_stmt->execute();
            $pass_result = $pass_stmt->get_result();
            
            if ($pass_result->num_rows === 0) {
                throw new Exception('User tidak ditemukan');
            }
            
            $current_hash = $pass_result->fetch_assoc()['password'];
            
            if (!password_verify($current_password, $current_hash)) {
                throw new Exception('Password saat ini salah');
            }
            
            // Update with new password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE user_id = ?");
            $stmt->bind_param('sssi', $username, $email, $new_password_hash, $user_id);
        } else {
            // Update without password change
            $stmt = $db->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
            $stmt->bind_param('ssi', $username, $email, $user_id);
        }
        
        if ($stmt->execute()) {
            // Update session data
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            
            echo json_encode([
                'success' => true,
                'message' => 'Profile berhasil diupdate'
            ]);
        } else {
            throw new Exception('Gagal mengupdate profile');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function checkSession() {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        echo json_encode([
            'success' => true,
            'user' => [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'role' => $_SESSION['role'],
                'division_id' => $_SESSION['division_id']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Tidak ada session aktif'
        ]);
    }
}
?> 