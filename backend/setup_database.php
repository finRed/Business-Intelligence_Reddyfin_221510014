<?php
// Script untuk setup database dan user admin
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>ContractRec Database Setup</h2>";

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'contract_rec_db';

try {
    // Step 1: Connect to MySQL server (without database)
    echo "<p>Step 1: Connecting to MySQL server...</p>";
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✓ Connected to MySQL server</p>";

    // Step 2: Create database if not exists
    echo "<p>Step 2: Creating database...</p>";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $database");
    echo "<p style='color: green;'>✓ Database '$database' created/exists</p>";

    // Step 3: Use the database
    $pdo->exec("USE $database");
    echo "<p style='color: green;'>✓ Using database '$database'</p>";

    // Step 4: Create tables
    echo "<p>Step 3: Creating tables...</p>";
    
    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            user_id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'hr', 'manager') NOT NULL,
            division_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status ENUM('active', 'inactive') DEFAULT 'active'
        )
    ");
    echo "<p style='color: green;'>✓ Users table created</p>";

    // Divisions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS divisions (
            division_id INT PRIMARY KEY AUTO_INCREMENT,
            division_name VARCHAR(100) NOT NULL,
            description TEXT,
            manager_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "<p style='color: green;'>✓ Divisions table created</p>";

    // Employees table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employees (
            eid INT PRIMARY KEY AUTO_INCREMENT,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE,
            phone VARCHAR(20),
            birth_date DATE,
            address TEXT,
            status ENUM('Active', 'Inactive', 'Probation') DEFAULT 'Active',
            join_date DATE NOT NULL,
            resign_date DATE NULL,
            education_level ENUM('D3', 'S1', 'S2', 'S3') NOT NULL,
            major VARCHAR(100) NOT NULL,
            last_education_place VARCHAR(200),
            designation VARCHAR(100),
            role VARCHAR(100) NOT NULL,
            division_id INT,
            resign_reason TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (division_id) REFERENCES divisions(division_id)
        )
    ");
    echo "<p style='color: green;'>✓ Employees table created</p>";

    // Contracts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contracts (
            contract_id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id INT NOT NULL,
            contract_type ENUM('Probation', 'Kontrak 1', 'Kontrak 2', 'Kontrak 3', 'Permanent') NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE,
            status ENUM('active', 'completed', 'terminated', 'extended') DEFAULT 'active',
            review_date DATE,
            permanent_date DATE NULL,
            salary DECIMAL(15,2),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(eid) ON DELETE CASCADE
        )
    ");
    echo "<p style='color: green;'>✓ Contracts table created</p>";

    // Step 5: Insert default divisions
    echo "<p>Step 4: Inserting default divisions...</p>";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM divisions");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $divisions = [
            ['IT', 'Information Technology Department'],
            ['HR', 'Human Resources Department'],
            ['Finance', 'Finance Department'],
            ['Marketing', 'Marketing Department'],
            ['Operations', 'Operations Department']
        ];
        
        foreach ($divisions as $div) {
            $stmt = $pdo->prepare("INSERT INTO divisions (division_name, description) VALUES (?, ?)");
            $stmt->execute($div);
        }
        echo "<p style='color: green;'>✓ Default divisions inserted</p>";
    } else {
        echo "<p style='color: orange;'>✓ Divisions already exist</p>";
    }

    // Step 6: Create admin user
    echo "<p>Step 5: Creating admin user...</p>";
    $adminPassword = password_hash('password', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn();
    
    if ($adminExists == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@contractrec.com', $adminPassword, 'admin']);
        echo "<p style='color: green;'>✓ Admin user created</p>";
    } else {
        // Update existing admin password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $stmt->execute([$adminPassword]);
        echo "<p style='color: orange;'>✓ Admin password updated</p>";
    }

    // Step 7: Create demo users
    echo "<p>Step 6: Creating demo users...</p>";
    $demoUsers = [
        ['hr@company.com', password_hash('hr123', PASSWORD_DEFAULT), 'hr'],
        ['manager@company.com', password_hash('manager123', PASSWORD_DEFAULT), 'manager']
    ];
    
    foreach ($demoUsers as $user) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$user[0]]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user[0], $user[0], $user[1], $user[2]]);
        }
    }
    echo "<p style='color: green;'>✓ Demo users created</p>";

    // Step 8: Test login
    echo "<p>Step 7: Testing login...</p>";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify('password', $admin['password'])) {
        echo "<p style='color: green;'>✓ Admin login test successful</p>";
    } else {
        echo "<p style='color: red;'>✗ Admin login test failed</p>";
    }

    echo "<h3 style='color: green;'>Database setup completed successfully!</h3>";
    echo "<h4>Login Credentials:</h4>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> username=admin, password=password</li>";
    echo "<li><strong>HR:</strong> username=hr@company.com, password=hr123</li>";
    echo "<li><strong>Manager:</strong> username=manager@company.com, password=manager123</li>";
    echo "</ul>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 