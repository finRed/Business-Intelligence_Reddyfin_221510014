<?php
// Suppress output that corrupts JSON but keep error logging
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'Unauthorized - not logged in',
        'debug' => [
            'session_id' => session_id(),
            'logged_in' => $_SESSION['logged_in'] ?? 'not set',
            'session_keys' => array_keys($_SESSION ?? [])
        ]
    ]);
    exit;
}

// Check if user is HR (only HR can upload CSV)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin')) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'error' => 'Only HR can upload CSV files', 
        'debug' => [
            'current_role' => $_SESSION['role'] ?? 'not set',
            'session_id' => session_id(),
            'all_session_data' => $_SESSION ?? []
        ]
    ]);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'No file uploaded or upload error',
        'debug' => [
            'files' => $_FILES ?? [],
            'post' => $_POST ?? [],
            'upload_error' => $_FILES['csv_file']['error'] ?? 'no file'
        ]
    ]);
    exit;
}

try {
    $file = $_FILES['csv_file'];
    
    // Validate file
    if ($file['type'] !== 'text/csv' && !str_ends_with($file['name'], '.csv')) {
        throw new Exception('File must be CSV format');
    }
    
    if ($file['size'] > 50 * 1024 * 1024) { // 50MB limit
        throw new Exception('File size too large (max 50MB)');
    }
    
    // Include the CSV processor
    require_once __DIR__ . '/../config/pdo.php';
    require_once __DIR__ . '/csv_upload.php';
    
    // Process the CSV
    $processor = new CSVUploadProcessor();
    $results = $processor->processCSV($file['tmp_name']);
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    error_log("CSV Upload Error: " . $e->getMessage());
    error_log("CSV Upload Stack Trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?> 