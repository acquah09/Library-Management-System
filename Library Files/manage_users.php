<?php
// Enable detailed error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once 'db_config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    // Redirect to login page
    header("Location: index.php?error=access_denied");
    exit();
}

// Process user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add a new user
        if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $role = isset($_POST['role']) ? trim($_POST['role']) : 'user';
            
            // Validate inputs
            if (empty($name) || empty($username) || empty($email) || empty($password)) {
                throw new Exception("All fields are required.");
            }
            
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Username or email already exists.");
            }
            
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert the new user
            $stmt = $conn->prepare("INSERT INTO users (name, username, email, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $username, $email, $hashed_password, $role);
            
            if (!$stmt->execute()) {
                throw new Exception("Error adding user: " . $stmt->error);
            }
            
            $_SESSION['success'] = "User added successfully!";
            header("Location: manage_users.php");
            exit();
        }
        
        // Update an existing user
        if (isset($_POST['action']) && $_POST['action'] === 'update_user') {
            $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $role = isset($_POST['role']) ? trim($_POST['role']) : 'user';
            
            // Validate inputs
            if (empty($name) || empty($username) || empty($email) || empty($user_id)) {
                throw new Exception("Required fields are missing.");
            }
            
            // Check if username or email already exists for other users
            $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->bind_param("ssi", $username, $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Username or email already exists for another user.");
            }
            
            // Update user with or without password
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, email = ?, password = ?, role = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $name, $username, $email, $hashed_password, $role, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $name, $username, $email, $role, $user_id);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating user: " . $stmt->error);
            }
            
            $_SESSION['success'] = "User updated successfully!";
            header("Location: manage_users.php");
            exit();
        }
        
        // Delete a user
        if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
            $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            
            // Don't allow admin to delete themselves
            if ($user_id == $_SESSION['user_id']) {
                throw new Exception("You cannot delete your own account.");
            }
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("User not found.");
            }
            
            // Delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error deleting user: " . $stmt->error);
            }
            
            $_SESSION['success'] = "User deleted successfully!";
            header("Location: manage_users.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: manage_users.php");
        exit();
    }
}

// Get all users for display
try {
    $sql = "SELECT id, name, username, email, role, created_at FROM users ORDER BY name";
    $users_result = $conn->query($sql);
    
    if (!$users_result) {
        throw new Exception("Error fetching users: " . $conn->error);
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    $users_result = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/bootstrap.css">
    <title>Manage Users - Honest Preparatory Library</title>
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .card-body {
            padding: 20px;
        }
        .table {
            margin-bottom: 0;
        }
        .user-actions {
            display: flex;
            gap: 5px;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage Users</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</p>
        
        <!-- Navigation Links -->
        <div class="mb-4">
            <a href="admin.php" class="btn btn-secondary">Dashboard</a>
            <a href="manage_books.php" class="btn btn-secondary">Manage Books</a>
            <a href="manage_users.php" class="btn btn-primary">Manage Users</a>
            <a href="manage_loans.php" class="btn btn-secondary">Manage Loans</a>
            <a href="logout.php" class="btn btn-danger float-end">Logout</a>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Add User Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h2>Add New User</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="manage_users.php">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add User</button>
                </form>
            </div>
        </div>
        
        <!-- Users List -->
        <div class="card">
            <div class="card-header">
                <h2>Users List</h2>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users_result && $users_result->num_rows > 0): ?>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php if ($user['role'] == 'admin'): ?>
                                            <span class="badge bg-danger">Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td class="user-actions">
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editUserModal"
                                                data-user-id="<?php echo $user['id']; ?>"
                                                data-user-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                data-user-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                data-user-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                data-user-role="<?php echo htmlspecialchars($user['role']); ?>">
                                            Edit
                                        </button>
                                        
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" action="manage_users.php" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm" method="POST" action="manage_users.php">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Password (Leave blank to keep current)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editUserForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate edit modal with user data
        const editModal = document.getElementById('editUserModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                
                // Extract user data from button attributes
                const userId = button.getAttribute('data-user-id');
                const userName = button.getAttribute('data-user-name');
                const userUsername = button.getAttribute('data-user-username');
                const userEmail = button.getAttribute('data-user-email');
                const userRole = button.getAttribute('data-user-role');
                
                // Populate form fields
                document.getElementById('edit_user_id').value = userId;
                document.getElementById('edit_name').value = userName;
                document.getElementById('edit_username').value = userUsername;
                document.getElementById('edit_email').value = userEmail;
                document.getElementById('edit_role').value = userRole;
                
                // Clear password field
                document.getElementById('edit_password').value = '';
            });
        }
    </script>
</body>
</html>