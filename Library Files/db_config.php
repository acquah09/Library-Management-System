<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "Library";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define commonly used functions
function redirect($url) {
    header("Location: $url");
    exit();
}

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] == 'admin';
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('index.php?error=access_denied');
    }
}

// Redirect to login if not admin
function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        redirect('index.php?error=access_denied');
    }
}
?>