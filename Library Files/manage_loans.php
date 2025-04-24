<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Loading manage_loans.php...";

require_once 'db_config.php';

// Load penalty system if it exists
if (file_exists('penalty_system.php')) {
    require_once 'penalty_system.php';
} else {
    // Fallback functions if penalty_system.php doesn't exist
    function getMaxAllowedLoans($conn, $user_id) {
        return 5; // Default max loans
    }
}

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php?error=access_denied');
}

// Process loan actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update loan status
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $loan_id = (int)$_POST['loan_id'];
        $status = sanitizeInput($_POST['status']);
        $book_id = (int)$_POST['book_id'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            $success = false;
            
            // If updating to 'returned', set return date
            if ($status === 'returned') {
                $return_date = date('Y-m-d H:i:s');
                $sql = "UPDATE loans SET status = ?, return_date = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $status, $return_date, $loan_id);
                $success = $stmt->execute();
                $stmt->close();
                
                // Increase available quantity for the book
                $sql = "UPDATE books SET available_quantity = available_quantity + 1 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $book_id);
                $stmt->execute();
                $stmt->close();
            } 
            // If marking as borrowed
            elseif ($status === 'borrowed') {
                $sql = "UPDATE loans SET status = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $status, $loan_id);
                $success = $stmt->execute();
                $stmt->close();
                
                // Decrease available quantity for the book
                $sql = "UPDATE books SET available_quantity = available_quantity - 1 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $book_id);
                $stmt->execute();
                $stmt->close();
            }
            // Update status only
            else {
                $sql = "UPDATE loans SET status = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $status, $loan_id);
                $success = $stmt->execute();
                $stmt->close();
            }
            
            // Commit if successful
            if ($success) {
                $conn->commit();
                $_SESSION['success'] = "Loan status updated successfully!";
            } else {
                throw new Exception("Failed to update loan status.");
            }
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $_SESSION['error'] = "Error updating loan status: " . $e->getMessage();
        }
        
        // Redirect to avoid form resubmission
        header("Location: manage_loans.php");
        exit();
    }
    
    // Edit a loan
    if (isset($_POST['action']) && $_POST['action'] === 'edit_loan') {
        $loan_id = (int)$_POST['loan_id'];
        $user_id = (int)$_POST['user_id'];
        $book_id = (int)$_POST['book_id']; 
        $due_date = sanitizeInput($_POST['due_date']);
        $status = sanitizeInput($_POST['status']);
        $old_book_id = (int)$_POST['old_book_id'];
        $old_status = sanitizeInput($_POST['old_status']);
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update loan record
            $sql = "UPDATE loans SET user_id = ?, book_id = ?, due_date = ?, status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iissi", $user_id, $book_id, $due_date, $status, $loan_id);
            $success = $stmt->execute();
            $stmt->close();
            
            // Handle book quantity updates if book or status changed
            if ($success) {
                // If book has changed
                if ($book_id != $old_book_id) {
                    // Return the old book to inventory
                    if ($old_status == 'borrowed') {
                        $sql = "UPDATE books SET available_quantity = available_quantity + 1 WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $old_book_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                    
                    // Remove the new book from inventory if status is borrowed
                    if ($status == 'borrowed') {
                        $sql = "UPDATE books SET available_quantity = available_quantity - 1 WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $book_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                // If book is the same but status changed
                else if ($status != $old_status) {
                    if ($status == 'borrowed' && $old_status != 'borrowed') {
                        // Book is now borrowed but wasn't before
                        $sql = "UPDATE books SET available_quantity = available_quantity - 1 WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $book_id);
                        $stmt->execute();
                        $stmt->close();
                    } 
                    else if ($status != 'borrowed' && $old_status == 'borrowed') {
                        // Book is no longer borrowed but was before
                        $sql = "UPDATE books SET available_quantity = available_quantity + 1 WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $book_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                
                $conn->commit();
                $_SESSION['success'] = "Loan updated successfully!";
            } else {
                throw new Exception("Failed to update loan.");
            }
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $_SESSION['error'] = "Error updating loan: " . $e->getMessage();
        }
        
        // Redirect to avoid form resubmission
        header("Location: manage_loans.php");
        exit();
    }
    
    // Create a new loan
    if (isset($_POST['action']) && $_POST['action'] === 'add_loan') {
        $user_id = (int)$_POST['user_id'];
        $book_id = (int)$_POST['book_id'];
        $due_date = date('Y-m-d H:i:s', strtotime($_POST['due_date']));
        
        // Check if book is available
        $sql = "SELECT available_quantity FROM books WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $available = $stmt->get_result()->fetch_assoc()['available_quantity'];
        $stmt->close();
        
        // Check user's penalty status and borrowing limit
        $max_allowed = function_exists('getMaxAllowedLoans') ? getMaxAllowedLoans($conn, $user_id) : 5;
        
        // Get current active loans count
        $sql = "SELECT COUNT(*) as count FROM loans WHERE user_id = ? AND status = 'borrowed'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $active_loans = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        if ($active_loans >= $max_allowed) {
            $_SESSION['error'] = "User has reached their borrowing limit ($max_allowed books allowed).";
        } elseif ($available <= 0) {
            $_SESSION['error'] = "This book is not available for loan.";
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Create loan record
                $sql = "INSERT INTO loans (user_id, book_id, due_date) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iis", $user_id, $book_id, $due_date);
                $stmt->execute();
                $stmt->close();
                
                // Update book available quantity
                $sql = "UPDATE books SET available_quantity = available_quantity - 1 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $book_id);
                $stmt->execute();
                $stmt->close();
                
                // Commit transaction
                $conn->commit();
                $_SESSION['success'] = "Loan created successfully!";
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $_SESSION['error'] = "Error creating loan: " . $e->getMessage();
            }
        }
        
        // Redirect to avoid form resubmission
        header("Location: manage_loans.php");
        exit();
    }
    
    // Delete a loan
    if (isset($_POST['action']) && $_POST['action'] === 'delete_loan') {
        $loan_id = (int)$_POST['loan_id'];
        $book_id = (int)$_POST['book_id'];
        $status = sanitizeInput($_POST['status']);
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Delete the loan
            $sql = "DELETE FROM loans WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $loan_id);
            $stmt->execute();
            $stmt->close();
            
            // If status is 'borrowed', update book available quantity
            if ($status === 'borrowed') {
                $sql = "UPDATE books SET available_quantity = available_quantity + 1 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $book_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            $_SESSION['success'] = "Loan deleted successfully!";
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $_SESSION['error'] = "Error deleting loan: " . $e->getMessage();
        }
        
        // Redirect to avoid form resubmission
        header("Location: manage_loans.php");
        exit();
    }
}

// Get all loans for display
$sql = "SELECT l.*, u.username, u.name as user_name, b.title, b.author 
        FROM loans l 
        JOIN users u ON l.user_id = u.id 
        JOIN books b ON l.book_id = b.id 
        ORDER BY l.borrow_date DESC";
$loans_result = $conn->query($sql);

// Get users for the dropdown
$sql = "SELECT id, username, name FROM users ORDER BY name";
$users_result = $conn->query($sql);

// Get books with available copies for the dropdown (for new loans)
$sql = "SELECT id, title, author, available_quantity FROM books WHERE available_quantity > 0 ORDER BY title";
$available_books = $conn->query($sql);

// Get all books for edit dropdown (regardless of availability)
$sql = "SELECT id, title, author, available_quantity FROM books ORDER BY title";
$all_books = $conn->query($sql);

// Function to get user specific info with penalties
function getUserLoanInfo($conn, $user_id) {
    // Get penalty info
    $penalties = function_exists('calculateUserPenalties') ? calculateUserPenalties($conn, $user_id) : [
        'penalty_count' => 0,
        'max_allowed_loans' => 5,
        'total_days_overdue' => 0
    ];
    
    // Get current loan count
    $sql = "SELECT COUNT(*) as count FROM loans WHERE user_id = ? AND status = 'borrowed'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $loan_count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    return [
        'penalties' => $penalties,
        'current_loans' => $loan_count,
        'max_allowed' => isset($penalties['max_allowed_loans']) ? $penalties['max_allowed_loans'] : 5
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/bootstrap.css">
    <title>Manage Loans - Honest Preparatory Library</title>
    <style>
        html {
            position: relative;
            min-height: 100%;
        }
        
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin-bottom: 60px; /* Footer height */
        }
        
        .logo-container {
            background-color: #ffffff;
            padding: 10px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 2;
        }
        
        .logo {
            width: 150px;
        }
        
        .dashboard-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
            flex: 1 0 auto;
        }
        
        .loan-form-container, .loans-list-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .nav-tabs {
            margin-bottom: 20px;
        }
        
        .logout-button {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: #ff4d4d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .logout-button:hover {
            background-color: #cc0000;
        }
        
        .loan-actions {
            display: flex;
            gap: 5px;
        }
        
        .overdue {
            color: #dc3545;
            font-weight: bold;
        }
        
        .user-info {
            font-size: 12px;
            padding: 5px 8px;
            border-radius: 4px;
            margin-left: 5px;
        }
        
        .has-penalty {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .loan-limit {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .at-limit {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            height: 60px;
            background-color: #004aad;
            color: white;
            text-align: center;
            line-height: 60px;
        }
    </style>
</head>
<body>
    <!-- Logo Section -->
    <div class="logo-container">
        <img class="logo" src="./images/logo.png" alt="Logo">
        <button class="logout-button" onclick="window.location.href='logout.php'">Logout</button>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-container">
        <h1>Manage Loans</h1>
        <button type="button" class="btn btn-primary" onclick="alert('Button clicked!')">Test JavaScript</button>
        <p>Welcome, <?php echo $_SESSION['name']; ?>!</p>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link" href="admin.php">Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_books.php">Manage Books</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_users.php">Manage Users</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="manage_loans.php">Manage Loans</a>
            </li>
        </ul>
        
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
        
        <!-- Add Loan Form -->
        <div class="loan-form-container">
            <h2>Create New Loan</h2>
            <form method="POST" action="manage_loans.php" id="addLoanForm">
                <input type="hidden" name="action" value="add_loan">
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="user_id" class="form-label">User</label>
                        <select class="form-select" id="user_id" name="user_id" required>
                            <option value="">Select User</option>
                            <?php while ($users_result && $user = $users_result->fetch_assoc()): 
                                $user_info = getUserLoanInfo($conn, $user['id']);
                                $can_borrow = $user_info['current_loans'] < $user_info['max_allowed'];
                                $has_penalties = isset($user_info['penalties']['penalty_count']) && $user_info['penalties']['penalty_count'] > 0;
                            ?>
                                <option value="<?php echo $user['id']; ?>" 
                                        data-loans="<?php echo $user_info['current_loans']; ?>"
                                        data-max="<?php echo $user_info['max_allowed']; ?>"
                                        data-penalties="<?php echo $has_penalties ? '1' : '0'; ?>"
                                        <?php echo !$can_borrow ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name'] . ' (' . $user['username'] . ')'); ?>
                                    <?php if ($has_penalties): ?> - Has penalties<?php endif; ?>
                                    <?php if (!$can_borrow): ?> - Max loans reached<?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div id="userLoanInfo" class="mt-2"></div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="book_id" class="form-label">Book</label>
                        <select class="form-select" id="book_id" name="book_id" required>
                            <option value="">Select Book</option>
                            <?php while ($available_books && $book = $available_books->fetch_assoc()): ?>
                                <option value="<?php echo $book['id']; ?>">
                                    <?php echo htmlspecialchars($book['title'] . ' by ' . $book['author'] . ' (' . $book['available_quantity'] . ' available)'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="due_date" class="form-label">Due Date</label>
                        <input type="date" class="form-control" id="due_date" name="due_date" 
                               value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Create Loan</button>
            </form>
        </div>
        
        <!-- Loans List -->
        <div class="loans-list-container">
            <h2>Loans List</h2>
            
            <!-- Search and Filter -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <input type="text" id="searchLoans" class="form-control" placeholder="Search loans...">
                </div>
                <div class="col-md-3">
                    <select id="statusFilter" class="form-select">
                        <option value="">All Status</option>
                        <option value="borrowed">Borrowed</option>
                        <option value="returned">Returned</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button id="resetFilters" class="btn btn-secondary">Reset Filters</button>
                </div>
            </div>
            
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Book</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($loans_result && $loan = $loans_result->fetch_assoc()): ?>
                    <?php 
                        $is_overdue = $loan['status'] === 'borrowed' && strtotime($loan['due_date']) < time();
                        $status_display = $loan['status'];
                        if ($is_overdue) {
                            $status_display = 'overdue';
                        }
                    ?>
                    <tr data-status="<?php echo $status_display; ?>">
                        <td><?php echo htmlspecialchars($loan['user_name']); ?></td>
                        <td><?php echo htmlspecialchars($loan['title']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($loan['borrow_date'])); ?></td>
                        <td class="<?php echo $is_overdue ? 'overdue' : ''; ?>">
                            <?php echo date('Y-m-d', strtotime($loan['due_date'])); ?>
                            <?php if ($is_overdue): ?>
                                <br><small class="overdue">
                                    (<?php echo floor((time() - strtotime($loan['due_date'])) / (60*60*24)); ?> days overdue)
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $loan['return_date'] ? date('Y-m-d', strtotime($loan['return_date'])) : '-'; ?>
                        </td>
                        <td>
                            <?php 
                            if ($status_display === 'borrowed') {
                                echo '<span class="badge bg-primary">Borrowed</span>';
                            } elseif ($status_display === 'returned') {
                                echo '<span class="badge bg-success">Returned</span>';
                            } else {
                                echo '<span class="badge bg-danger">Overdue</span>';
                            }
                            ?>
                        </td>
                        <td class="loan-actions">
                            <button type="button" class="btn btn-sm btn-info edit-loan-btn" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editLoanModal"
                                    data-loan-id="<?php echo $loan['id']; ?>"
                                    data-user-id="<?php echo $loan['user_id']; ?>"
                                    data-book-id="<?php echo $loan['book_id']; ?>"
                                    data-due-date="<?php echo date('Y-m-d', strtotime($loan['due_date'])); ?>"
                                    data-status="<?php echo $loan['status']; ?>"
                                    data-user-name="<?php echo htmlspecialchars($loan['user_name']); ?>"
                                    data-book-title="<?php echo htmlspecialchars($loan['title']); ?>">
                                Edit
                            </button>
                            
                            <?php if ($loan['status'] === 'borrowed'): ?>
                                <form method="POST" action="manage_loans.php">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                    <input type="hidden" name="book_id" value="<?php echo $loan['book_id']; ?>">
                                    <input type="hidden" name="status" value="returned">
                                    <button type="submit" class="btn btn-sm btn-success">Mark Returned</button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" action="manage_loans.php" onsubmit="return confirm('Are you sure you want to delete this loan?');">
                                <input type="hidden" name="action" value="delete_loan">
                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                <input type="hidden" name="book_id" value="<?php echo $loan['book_id']; ?>">
                                <input type="hidden" name="status" value="<?php echo $loan['status']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <?php if (!$loans_result || $loans_result->num_rows == 0): ?>
                    <tr>
                        <td colspan="7" class="text-center">No loans found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Loan Modal -->
    <div class="modal fade" id="editLoanModal" tabindex="-1" aria-labelledby="editLoanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLoanModalLabel">Edit Loan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editLoanForm" method="POST" action="manage_loans.php">
                        <input type="hidden" name="action" value="edit_loan">
                        <input type="hidden" id="edit_loan_id" name="loan_id">
                        <input type="hidden" id="old_book_id" name="old_book_id">
                        <input type="hidden" id="old_status" name="old_status">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_user_id" class="form-label">User</label>
                                <select class="form-select" id="edit_user_id" name="user_id" required>
                                    <?php 
                                    // Reset the internal pointer of the result set
                                    if ($users_result) {
                                        $users_result->data_seek(0);
                                        while ($user = $users_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['name'] . ' (' . $user['username'] . ')'); ?>
                                        </option>
                                    <?php 
                                        endwhile;
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_book_id" class="form-label">Book</label>
                                <select class="form-select" id="edit_book_id" name="book_id" required>
                                    <?php if ($all_books): $all_books->data_seek(0); ?>
                                        <?php while ($book = $all_books->fetch_assoc()): ?>
                                            <option value="<?php echo $book['id']; ?>">
                                                <?php echo htmlspecialchars($book['title'] . ' by ' . $book['author']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="edit_due_date" name="due_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="borrowed">Borrowed</option>
                                    <option value="returned">Returned</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editLoanForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Honest Preparatory Library</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            var editLoanModal = document.getElementById('editLoanModal');
if (editLoanModal) {
    var bootstrapModal = new bootstrap.Modal(editLoanModal);
}

// Then modify your edit button click handler
$('.edit-loan-btn').on('click', function() {
    console.log('Edit button clicked');
    
    // Get data...
    
    // Try to show modal explicitly
    if (typeof bootstrapModal !== 'undefined') {
        bootstrapModal.show();
    }
});
            // Search functionality
            $("#searchLoans").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("table tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });
            
            // Status filter change
            $("#statusFilter").on("change", function() {
                var value = $(this).val().toLowerCase();
                if (value === "") {
                    $("table tbody tr").show();
                } else {
                    $("table tbody tr").filter(function() {
                        var status = $(this).data('status');
                        $(this).toggle(status === value);
                    });
                }
            });
            
            // Reset filters
            $("#resetFilters").on("click", function() {
                $("#searchLoans").val("");
                $("#statusFilter").val("");
                $("table tbody tr").show();
            });
            
            // Display user loan info when a user is selected
            $("#user_id").on("change", function() {
                var option = $(this).find("option:selected");
                if (option.val()) {
                    var loans = option.data('loans');
                    var max = option.data('max');
                    var penalties = option.data('penalties');
                    
                    var infoDiv = $("#userLoanInfo");
                    infoDiv.empty();
                    
                    var loanInfo = $("<div>").text("Current loans: " + loans + " / " + max);
                    
                    if (loans >= max) {
                        loanInfo.addClass("at-limit");
                    } else {
                        loanInfo.addClass("loan-limit");
                    }
                    
                    infoDiv.append(loanInfo);
                    
                    if (penalties > 0) {
                        var penaltyInfo = $("<div>").text("Penalties: " + penalties).addClass("has-penalty");
                        infoDiv.append(penaltyInfo);
                    }
                } else {
                    $("#userLoanInfo").empty();
                }
            });
            
            // Edit loan modal functionality
            $('.edit-loan-btn').on('click', function() {
                // Get data from button attributes
                var loanId = $(this).data('loan-id');
                var userId = $(this).data('user-id');
                var bookId = $(this).data('book-id');
                var dueDate = $(this).data('due-date');
                var status = $(this).data('status');
                
                // Set values in the edit form
                $('#edit_loan_id').val(loanId);
                $('#edit_user_id').val(userId);
                $('#edit_book_id').val(bookId);
                $('#edit_due_date').val(dueDate);
                $('#edit_status').val(status);
                
                // Store original values for comparison
                $('#old_book_id').val(bookId);
                $('#old_status').val(status);
            });
        });
    </script>
</body>
</html>