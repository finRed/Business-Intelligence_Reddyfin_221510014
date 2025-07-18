<?php
// PDO Database configuration for APIs
function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=contract_rec_db;charset=utf8', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit();
        }
    }
    return $pdo;
}

// For backward compatibility
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    // Already handled in getDbConnection()
}
?> 