<?php
require_once 'config/pdo.php';

try {
    // Check if resign_reason column exists
    $check_column = $pdo->query("SHOW COLUMNS FROM employees LIKE 'resign_reason'");
    
    if ($check_column->rowCount() == 0) {
        // Add resign_reason column
        $pdo->exec("ALTER TABLE employees ADD COLUMN resign_reason TEXT NULL AFTER resign_date");
        echo "✅ Successfully added resign_reason column to employees table\n";
    } else {
        echo "✅ resign_reason column already exists\n";
    }
    
    // Verify the column exists
    $verify = $pdo->query("SHOW COLUMNS FROM employees LIKE 'resign_reason'")->fetch();
    if ($verify) {
        echo "📋 Column details: {$verify['Field']} ({$verify['Type']})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 