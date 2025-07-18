<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'contract_rec_db';

try {
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Database class for backward compatibility
class Database {
    private $host = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'contract_rec_db';
    private $connection;

    public function __construct() {
        global $conn;
        $this->connection = $conn;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function prepare($query) {
        return $this->connection->prepare($query);
    }

    public function query($query) {
        return $this->connection->query($query);
    }

    public function lastInsertId() {
        return $this->connection->insert_id;
    }

    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// Set CORS headers for API requests only if not in CLI mode and headers not already sent
if (php_sapi_name() !== 'cli' && !headers_sent()) {
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
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Set content type for API responses only for web requests
    if (isset($_SERVER['REQUEST_METHOD'])) {
        header('Content-Type: application/json; charset=utf-8');
    }
}
?>