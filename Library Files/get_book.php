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
    echo json_encode(['error' => 'Invalid book ID']);
    exit();
}

$book_id = (int)$_GET['id'];

// Get book details
$sql = "SELECT * FROM books WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Book not found']);
    exit();
}

// Get the book data and return as JSON
$book = $result->fetch_assoc();
$stmt->close();

// Set content type to JSON
header('Content-Type: application/json');
echo json_encode($book);