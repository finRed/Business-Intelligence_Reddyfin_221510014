<?php
header('Content-Type: application/json');
session_start();

echo json_encode([
    'session_data' => $_SESSION,
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? 'not set',
    'role' => $_SESSION['role'] ?? 'not set',
    'division_id' => $_SESSION['division_id'] ?? 'not set',
    'username' => $_SESSION['username'] ?? 'not set'
]);
?> 