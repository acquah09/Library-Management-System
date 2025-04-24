<?php
// Include database config
require_once 'db_config.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Access denied']);
    exit();
}

// Check if ID parameter is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

$user_id = (int)$_GET['id'];

// Get user details (exclude password for security)
$sql = "SELECT id, name, username, email, role, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'User not found']);
    exit();
}

// Get the user data and return as JSON
$user = $result->fetch_assoc();
$stmt->close();

// Set content type to JSON
header('Content-Type: application/json');
echo json_encode($user);