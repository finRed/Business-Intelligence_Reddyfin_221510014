<?php
// Configure session before starting
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0'); 
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_lifetime', 86400);

session_start();
require_once __DIR__ . '/../config/db.php';

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'];

// Set CORS headers
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight requests
if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    switch ($method) {
        case 'GET':
            getDivisions($db);
            break;
        
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input) {
                addDivision($db, $input);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
            }
            break;
        
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input) {
                updateDivision($db, $input);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
            }
            break;
        
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input) {
                deleteDivision($db, $input);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
            }
            break;
        
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function getDivisions($db) {
    try {
        $stmt = $db->prepare("
            SELECT d.division_id, d.division_name, d.description, d.created_at,
                   u.username as manager_name
            FROM divisions d 
            LEFT JOIN users u ON d.manager_id = u.user_id 
            ORDER BY d.division_name ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $divisions = [];
        while ($row = $result->fetch_assoc()) {
            $divisions[] = [
                'division_id' => $row['division_id'],
                'division_name' => $row['division_name'],
                'description' => $row['description'],
                'manager_name' => $row['manager_name'],
                'created_at' => $row['created_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $divisions
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function addDivision($db, $input) {
    try {
        if (!isset($input['name']) || empty(trim($input['name']))) {
            throw new Exception('Nama divisi harus diisi');
        }
        
        $name = trim($input['name']);
        $description = isset($input['description']) ? trim($input['description']) : '';
        
        // Check if division name already exists
        $check_stmt = $db->prepare("SELECT division_id FROM divisions WHERE division_name = ?");
        $check_stmt->bind_param('s', $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception('Nama divisi sudah ada');
        }
        
        // Insert new division
        $stmt = $db->prepare("INSERT INTO divisions (division_name, description) VALUES (?, ?)");
        $stmt->bind_param('ss', $name, $description);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Divisi berhasil ditambahkan',
                'division_id' => $db->lastInsertId()
            ]);
        } else {
            throw new Exception('Gagal menambahkan divisi');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function updateDivision($db, $input) {
    try {
        if (!isset($input['division_id']) || !isset($input['name'])) {
            throw new Exception('Data divisi tidak lengkap');
        }
        
        $division_id = $input['division_id'];
        $name = trim($input['name']);
        $description = isset($input['description']) ? trim($input['description']) : '';
        
        // Check if division name already exists for other divisions
        $check_stmt = $db->prepare("SELECT division_id FROM divisions WHERE division_name = ? AND division_id != ?");
        $check_stmt->bind_param('si', $name, $division_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception('Nama divisi sudah digunakan oleh divisi lain');
        }
        
        // Update division
        $stmt = $db->prepare("UPDATE divisions SET division_name = ?, description = ? WHERE division_id = ?");
        $stmt->bind_param('ssi', $name, $description, $division_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Divisi berhasil diupdate'
            ]);
        } else {
            throw new Exception('Gagal mengupdate divisi');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function deleteDivision($db, $input) {
    try {
        if (!isset($input['division_id'])) {
            throw new Exception('Division ID tidak ditemukan');
        }
        
        $division_id = $input['division_id'];
        
        // Check if division has employees
        $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM employees WHERE division_id = ? AND status = 'active'");
        $check_stmt->bind_param('i', $division_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $count = $check_result->fetch_assoc()['count'];
        
        if ($count > 0) {
            throw new Exception('Tidak dapat menghapus divisi yang masih memiliki karyawan aktif');
        }
        
        // Delete division
        $stmt = $db->prepare("DELETE FROM divisions WHERE division_id = ?");
        $stmt->bind_param('i', $division_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Divisi berhasil dihapus'
            ]);
        } else {
            throw new Exception('Gagal menghapus divisi');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?> 