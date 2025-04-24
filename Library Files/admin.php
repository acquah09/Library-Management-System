<?php
// error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    // Redirect to login page
    header("Location: index.php?error=access_denied");
    exit();
}

// Try/catch block to catch any SQL errors
try {
    $stats = array();

    // Total users
    $sql = "SELECT COUNT(*) as total FROM users";
    $result = $conn->query($sql);
    if ($result) {
        $stats['total_users'] = $result->fetch_assoc()['total'];
    } else {
        $stats['total_users'] = 0;
        echo "<!-- Error counting users: " . $conn->error . " -->";
    }

    // Total books
    $sql = "SELECT COUNT(*) as total FROM books";
    $result = $conn->query($sql);
    if ($result) {
        $stats['total_books'] = $result->fetch_assoc()['total'];
    } else {
        $stats['total_books'] = 0;
        echo "<!-- Error counting books: " . $conn->error . " -->";
    }

    // Total loans
    $sql = "SELECT COUNT(*) as total FROM loans";
    $result = $conn->query($sql);
    if ($result) {
        $stats['total_loans'] = $result->fetch_assoc()['total'];
    } else {
        $stats['total_loans'] = 0;
        echo "<!-- Error counting loans: " . $conn->error . " -->";
    }

    // Active loans
    $sql = "SELECT COUNT(*) as total FROM loans WHERE status = 'borrowed'";
    $result = $conn->query($sql);
    if ($result) {
        $stats['active_loans'] = $result->fetch_assoc()['total'];
    } else {
        $stats['active_loans'] = 0;
        echo "<!-- Error counting active loans: " . $conn->error . " -->";
    }

    // Recent loans
    $sql = "SELECT l.*, u.username, b.title 
            FROM loans l 
            JOIN users u ON l.user_id = u.id 
            JOIN books b ON l.book_id = b.id 
            ORDER BY l.borrow_date DESC LIMIT 5";
    $recent_loans = $conn->query($sql);
    if (!$recent_loans) {
        echo "<!-- Error getting recent loans: " . $conn->error . " -->";
        $recent_loans = new stdClass();
        $recent_loans->num_rows = 0;
    }
} catch (Exception $e) {
    echo "<!-- Database error: " . $e->getMessage() . " -->";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/bootstrap.css">
    <title>Admin Dashboard - Honest Preparatory Library</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: auto;
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
        }
        
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            flex: 1;
            min-width: 200px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 15px;
            text-align: center;
        }
        
        .stat-card h3 {
            color: #004aad;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        
        .recent-container {
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
            float: right;
            margin-top: 10px;
        }

        .dashboard-container {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 25px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            max-width: 1200px;
            flex: 1 0 auto; 
        }
        
        .image-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        
        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 1;
        }
        footer {
            background-color: #004aad;
            color: white;
            text-align: center;
            padding: 10px 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Logo Section -->
    <div class="logo-container">
        <img class="logo" src="images/logo.png" alt="Logo">
        <a href="logout.php" class="btn btn-danger logout-button">Logout</a>
    </div>
        <!-- Background Image -->
        <div class="image-container">
        <img src="./images/0408_N16_htwebcrp.avif" alt="Library" />
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-container">
        <h1>Admin Dashboard</h1>
        <p>Welcome, <?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Admin'; ?>!</p>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link active" href="admin.php">Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_books.php">Manage Books</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_users.php">Manage Users</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="manage_loans.php">Manage Loans</a>
            </li>
        </ul>

        
        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <h3>Total Users</h3>
                <p><?php echo isset($stats['total_users']) ? $stats['total_users'] : 0; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Books</h3>
                <p><?php echo isset($stats['total_books']) ? $stats['total_books'] : 0; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Loans</h3>
                <p><?php echo isset($stats['total_loans']) ? $stats['total_loans'] : 0; ?></p>
            </div>
            <div class="stat-card">
                <h3>Active Loans</h3>
                <p><?php echo isset($stats['active_loans']) ? $stats['active_loans'] : 0; ?></p>
            </div>
        </div>
        
        <!-- Recent Loans -->
        <div class="recent-container">
            <h2>Recent Loans</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Book</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (isset($recent_loans) && $recent_loans->num_rows > 0):
                        while ($loan = $recent_loans->fetch_assoc()): 
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($loan['username']); ?></td>
                            <td><?php echo htmlspecialchars($loan['title']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($loan['borrow_date'])); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($loan['due_date'])); ?></td>
                            <td>
                                <?php 
                                if($loan['status'] == 'borrowed') {
                                    echo '<span class="badge bg-primary">Borrowed</span>';
                                } elseif($loan['status'] == 'returned') {
                                    echo '<span class="badge bg-success">Returned</span>';
                                } else {
                                    echo '<span class="badge bg-danger">Overdue</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                        <tr>
                            <td colspan="5" class="text-center">No recent loans</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <footer>
    <p>
    Connect with me: 
    <a href="https://www.linkedin.com/in/emmanuel-acquah-5b4577146/" 
       target="_blank" 
       class="btn btn-linkedin" 
       style="background-color: #0077B5; color: white;">
       <i class="fab fa-linkedin-in"></i> LinkedIn
    </a>
</p>
        <p>&copy; <?php echo date('Y'); ?> Honest Preparatory Library</p>
    </footer>

    <script src="assets/bootstrap.js"></script>
</body>
</html>