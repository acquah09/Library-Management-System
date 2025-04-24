<?php
// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Include database configuration
include 'db_config.php';

// Define the redirect function if it doesn't exist
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $selected_role = isset($_POST['role']) ? trim($_POST['role']) : 'user';
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        redirect('index.php?error=invalid_credentials');
    }
    
    try {
        // Check if connection is valid
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }
        
        // Secure against SQL injection
        $stmt = $conn->prepare("SELECT id, username, password, role, name FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if user selected admin role but isn't an admin
                if ($selected_role == 'admin' && $user['role'] != 'admin') {
                    redirect('index.php?error=invalid_admin');
                }
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'] ?? $user['username'];
                
                // Redirect based on role
                if ($user['role'] == 'admin') {
                    // Check if admin.php exists
                    if (file_exists('admin.php')) {
                        redirect('admin.php');
                    } else {
                        throw new Exception("Admin file does not exist");
                    }
                } else {
                    // Check if user.php exists
                    if (file_exists('user.php')) {
                        redirect('user.php');
                    } else {
                        throw new Exception("User file does not exist");
                    }
                }
            } else {
                // Password doesn't match
                redirect('index.php?error=invalid_credentials');
            }
        } else {
            // User not found
            redirect('index.php?error=not_found');
        }
        
        $stmt->close();
    } catch (Exception $e) {
        // Log the error
        error_log("Authentication error: " . $e->getMessage());
        
        // Redirect with generic error (avoid exposing system details)
        redirect('index.php?error=authentication_failed');
    }
} else {
    // If not POST request, redirect to login page
    redirect('index.php');
}

// Close the database connection
$conn->close();
?>