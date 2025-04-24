<?php
// Enable detailed error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}
if (file_exists('penalty_system.php')) {
    require_once 'penalty_system.php';
} else {

    function calculateUserPenalties($conn, $user_id) {
        return [
            'penalty_count' => 0,
            'max_allowed_loans' => 5,
            'penalty_expiry' => null,
            'active_penalties' => [],
            'total_days_overdue' => 0
        ];
    }
    
    function getMaxAllowedLoans($conn, $user_id) {
        return 5; // Default max loans
    }
    
    function userHasPenalties($conn, $user_id) {
        return false;
    }
    
    function getPenaltyDetailsHtml($conn, $user_id) {
        return '<p>You have no active penalties.</p>';
    }
}

function getPaginatedBooks($conn, $search_query = "", $search_field = "all", $genre_filter = "", $page = 1, $books_per_page = 12) {
 
    $params = array();
    $types = "";
    $offset = ($page - 1) * $books_per_page;
    
 
    $count_query = "SELECT COUNT(*) as total FROM books WHERE available_quantity > 0";
    
    $book_query = "SELECT * FROM books WHERE available_quantity > 0";
    
    // Add search conditions
    if (!empty($search_query)) {
        if ($search_field == 'title' || $search_field == 'all') {
            $count_query .= " AND title LIKE ?";
            $book_query .= " AND title LIKE ?";
            $params[] = "%$search_query%";
            $types .= "s";
        } else if ($search_field == 'author') {
            $count_query .= " AND author LIKE ?";
            $book_query .= " AND author LIKE ?";
            $params[] = "%$search_query%";
            $types .= "s";
        } else if ($search_field == 'isbn') {
            $count_query .= " AND isbn LIKE ?";
            $book_query .= " AND isbn LIKE ?";
            $params[] = "%$search_query%";
            $types .= "s";
        }
    }
    if (!empty($genre_filter)) {
        $count_query .= " AND genre = ?";
        $book_query .= " AND genre = ?";
        $params[] = $genre_filter;
        $types .= "s";
    }
    $book_query .= " ORDER BY title";
    $total_books = 0;
    if (!empty($params)) {
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $total_books = $row['total'];
        }
        $stmt->close();
    } else {
        $result = $conn->query($count_query);
        if ($result && $row = $result->fetch_assoc()) {
            $total_books = $row['total'];
        }
    }
    if ($books_per_page <= 0) {
        $books_per_page = 1; 
    }
    $total_pages = ($total_books > 0) ? ceil($total_books / $books_per_page) : 1;
    $book_query .= " LIMIT ? OFFSET ?";
    $books_result = null;
    if (!empty($params)) {
        $params[] = $books_per_page;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($book_query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $books_result = $stmt->get_result();
        $stmt->close();
    } else {
        $stmt = $conn->prepare($book_query);
        $stmt->bind_param("ii", $books_per_page, $offset);
        $stmt->execute();
        $books_result = $stmt->get_result();
        $stmt->close();
    }
    return [
        'books' => $books_result,
        'total_books' => $total_books,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'books_per_page' => $books_per_page
    ];
}

function getPaginationHTML($current_page, $total_pages, $url_params = "") {

    if ($total_pages <= 1) {
        return "";
    }
    if (!empty($url_params)) {
        $url_params = "?" . $url_params . "&";
    } else {
        $url_params = "?";
    }
    
    $html = '<nav aria-label="Book pagination"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($current_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url_params . 'page=' . ($current_page - 1) . '">&laquo; Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">&laquo; Previous</a></li>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    // Always show first page
    if ($start_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url_params . 'page=1">1</a></li>';
        if ($start_page > 2) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
    }
    
    // Page numbers
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url_params . 'page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Always show last page
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $url_params . 'page=' . $total_pages . '">' . $total_pages . '</a></li>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url_params . 'page=' . ($current_page + 1) . '">Next &raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">Next &raquo;</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('index.php?error=not_logged_in');
}

// Check if user is admin
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    if (file_exists('admin.php')) {
        redirect('admin.php');
    } else {
        echo "<p>Admin page not found. <a href='index.php'>Return to login</a></p>";
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$search_query = "";
$search_field = "all";
$genre_filter = "";
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$books_per_page = 12; // Maximum 12 books per page

if ($current_page < 1) {
    $current_page = 1;
}

$penalty_info = calculateUserPenalties($conn, $user_id);
$max_loans = $penalty_info['max_allowed_loans']; // This is dynamic based on penalties

// Process search inputs
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
    $search_field = isset($_GET['field']) ? $_GET['field'] : 'all';
    $genre_filter = isset($_GET['genre']) ? $_GET['genre'] : '';
}

// Get paginated books
try {
    $pagination_result = getPaginatedBooks(
        $conn, 
        $search_query, 
        $search_field, 
        $genre_filter, 
        $current_page, 
        $books_per_page
    );
    
    $available_books = $pagination_result['books'];
    $total_pages = $pagination_result['total_pages'];
    
    // Adjust current page if out of range
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
        redirect("user.php?page=$current_page" . 
                 ($search_query ? "&search=$search_query" : "") . 
                 ($search_field != 'all' ? "&field=$search_field" : "") . 
                 ($genre_filter ? "&genre=$genre_filter" : ""));
    }
} catch (Exception $e) {
    // Log the error
    error_log("Error loading books: " . $e->getMessage());
    $available_books = null;
    $total_pages = 0;
}

// Get user's borrowed books
try {
    $sql = "SELECT l.*, b.title, b.author, b.genre 
            FROM loans l 
            JOIN books b ON l.book_id = b.id 
            WHERE l.user_id = ? AND l.status = 'borrowed' 
            ORDER BY l.due_date";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $borrowed_books = $stmt->get_result();
    $stmt->close();
    
    // user's active loans
    $active_loans_count = $borrowed_books ? $borrowed_books->num_rows : 0;
} catch (Exception $e) {
    // Log the error
    error_log("Error loading borrowed books: " . $e->getMessage());
    $borrowed_books = null;
    $active_loans_count = 0;
}

// available genres for filter dropdown
try {
    $genres_sql = "SELECT DISTINCT genre FROM books WHERE genre != '' ORDER BY genre";
    $genres_result = $conn->query($genres_sql);
    
    if (!$genres_result) {
        throw new Exception("Error fetching genres: " . $conn->error);
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    $genres_result = null;
}

// Build URL parameters for pagination
$url_params = [];
if ($search_query) {
    $url_params[] = "search=" . urlencode($search_query);
}
if ($search_field != 'all') {
    $url_params[] = "field=" . urlencode($search_field);
}
if ($genre_filter) {
    $url_params[] = "genre=" . urlencode($genre_filter);
}
$pagination_url_params = implode("&", $url_params);

// Process book actions (borrow/return)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // borrow book
        if (isset($_POST['action']) && $_POST['action'] === 'borrow') {
            $book_id = (int)$_POST['book_id'];
            
            // Check if user has reached the maximum number of loans
            if ($active_loans_count >= $max_loans) {
                throw new Exception("You have reached the maximum number of books you can borrow ($max_loans).");
            }
            
            // Check if book is available
            $sql = "SELECT available_quantity FROM books WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $book = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($book['available_quantity'] > 0) {
                // Begin transaction
                $conn->begin_transaction();
                
                // Create a new loan record
                $due_date = date('Y-m-d H:i:s', strtotime('+14 days')); // Due in 2 weeks
                $sql = "INSERT INTO loans (user_id, book_id, due_date, status) VALUES (?, ?, ?, 'borrowed')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iis", $user_id, $book_id, $due_date);
                $stmt->execute();
                $stmt->close();
                
                // Update book available quantity
                $sql = "UPDATE books SET available_quantity = available_quantity - 1 WHERE id = ?";
                $update_stmt = $conn->prepare($sql);
                $update_stmt->bind_param("i", $book_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                $conn->commit();
                
                // Set success message
                $_SESSION['success'] = "Book borrowed successfully!";
                redirect('user.php');
            } else {
                throw new Exception("Sorry, this book is no longer available.");
            }
        }
        
        // Return a book
        if (isset($_POST['action']) && $_POST['action'] === 'return') {
            $loan_id = (int)$_POST['loan_id'];
            
            // Verify loan belongs to user
            $sql = "SELECT book_id, due_date FROM loans WHERE id = ? AND user_id = ? AND status = 'borrowed'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $loan_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $loan = $result->fetch_assoc();
                $book_id = $loan['book_id'];
                $due_date = $loan['due_date'];
                $conn->begin_transaction();
                
                // Update loan status
                $return_date = date('Y-m-d H:i:s');
                $sql = "UPDATE loans SET status = 'returned', return_date = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $return_date, $loan_id);
                $stmt->execute();
                $stmt->close();
                
                // Update book available quantity
                $sql = "UPDATE books SET available_quantity = available_quantity + 1 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $book_id);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                
                // Check if the book was returned late
                $is_late = strtotime($due_date) < strtotime($return_date);
                
                if ($is_late) {
                    $days_late = floor((strtotime($return_date) - strtotime($due_date)) / (60*60*24));
                    $_SESSION['success'] = "Book returned successfully, but it was $days_late days overdue. This may affect your borrowing limit.";
                } else {
                    $_SESSION['success'] = "Book returned successfully!";
                }
                
                redirect('user.php');
            } else {
                throw new Exception("Invalid loan or you don't have permission to return this book.");
            }
        }
    } catch (Exception $e) {
        // Roll back the transaction if an error occurred
        if (isset($conn) && $conn->ping()) {
            $conn->rollback();
        }
        
        // Set error message
        $_SESSION['error'] = $e->getMessage();
        redirect('user.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/bootstrap.css">
    <title>User Dashboard - Honest Preparatory Library</title>
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
        
        .book-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            background-color: white;
            height: 400px;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .book-title {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            height: 50px;
        }
        
        .book-info {
            flex-grow: 1;
            overflow: hidden;
        }
        
        .book-description {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 10px;
        }
        
        .book-actions {
            margin-top: auto;
            padding-top: 10px;
        }
        
        .due-date {
            font-weight: bold;
        }
        
        .overdue {
            color: #dc3545;
        }
        
        .logo-container {
            background-color: #ffffff;
            padding: 10px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 2;
            margin-bottom: 20px;
        }
        
        .logo {
            width: 150px;
        }
        
        .logout-button {
            float: right;
            margin-top: 10px;
        }
        
        .search-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .content-container {
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
        
        .penalty-info {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .pagination {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        footer {
            width: 100%;
            background-color: #004aad;
            color: white;
            text-align: center;
            padding: 10px 0;
            margin-top: auto; 
        }

    </style>
</head>
<body>
    <!-- Logo Section -->
    <div class="logo-container">
        <img class="logo" src="./images/logo.png" alt="Logo">
        <a href="logout.php" class="btn btn-danger logout-button">Logout</a>
    </div>

    <!-- Background Image -->
    <div class="image-container">
        <img src="./images/0408_N16_htwebcrp.avif" alt="Library" />
    </div>

    <div class="content-container">
        <div class="container">
            <h1>Library Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</p>
            
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
            
            <div class="alert alert-info">
                You currently have <?php echo $active_loans_count; ?> book(s) borrowed out of maximum <?php echo $max_loans; ?> allowed.
            </div>
            
            <!-- Penalty Information -->
            <?php if ($penalty_info['penalty_count'] > 0): ?>
                <?php echo getPenaltyDetailsHtml($conn, $user_id); ?>
            <?php endif; ?>
            
            <!-- Search Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Search Books</h2>
                </div>
                <div class="card-body">
                    <form method="GET" action="user.php" class="mb-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" placeholder="Search books..." value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="field" class="form-select">
                                    <option value="all" <?php echo $search_field == 'all' ? 'selected' : ''; ?>>All Fields</option>
                                    <option value="title" <?php echo $search_field == 'title' ? 'selected' : ''; ?>>Title</option>
                                    <option value="author" <?php echo $search_field == 'author' ? 'selected' : ''; ?>>Author</option>
                                    <option value="isbn" <?php echo $search_field == 'isbn' ? 'selected' : ''; ?>>ISBN</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="genre" class="form-select">
                                    <option value="">All Genres</option>
                                    <?php if ($genres_result && $genres_result->num_rows > 0): ?>
                                        <?php while ($genre = $genres_result->fetch_assoc()): ?>
                                            <option value="<?php echo htmlspecialchars($genre['genre']); ?>" <?php echo $genre_filter == $genre['genre'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($genre['genre']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Search</button>
                            </div>
                        </div>
                    </form>
                    
                    <?php if ($search_query || $genre_filter): ?>
                        <div class="mb-3">
                            <a href="user.php" class="btn btn-secondary">Clear Search</a>
                            <span class="ms-2">
                                Showing results for: 
                                <?php if ($search_query): ?>
                                    <strong>"<?php echo htmlspecialchars($search_query); ?>"</strong>
                                    in <strong><?php echo $search_field == 'all' ? 'all fields' : htmlspecialchars($search_field); ?></strong>
                                <?php endif; ?>
                                
                                <?php if ($genre_filter): ?>
                                    <?php echo $search_query ? ' and' : ''; ?> genre <strong>"<?php echo htmlspecialchars($genre_filter); ?>"</strong>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Available Books -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Available Books</h2>
                    <?php if (isset($pagination_result) && $pagination_result['total_books'] > 0): ?>
                        <small>Showing <?php echo min($books_per_page, $pagination_result['total_books'] - (($current_page - 1) * $books_per_page)); ?> 
                               of <?php echo $pagination_result['total_books']; ?> books</small>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <!-- Pagination - Top -->
                    <?php echo getPaginationHTML($current_page, $total_pages, $pagination_url_params); ?>
                    
                    <div class="row">
                        <?php if ($available_books && $available_books->num_rows > 0): ?>
                            <?php while ($book = $available_books->fetch_assoc()): ?>
                                <div class="col-md-4 col-lg-3">
                                    <div class="book-card">
                                        <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                                        <div class="book-info">
                                            <p><strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
                                            
                                            <?php if ($book['genre']): ?>
                                                <p><strong>Genre:</strong> <?php echo htmlspecialchars($book['genre']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if ($book['published_year']): ?>
                                                <p><strong>Published:</strong> <?php echo htmlspecialchars($book['published_year']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if ($book['description']): ?>
                                                <p class="book-description"><strong>Description:</strong> <?php echo htmlspecialchars($book['description']); ?></p>
                                            <?php endif; ?>
                                            
                                            <p><strong>Available:</strong> <?php echo $book['available_quantity']; ?> of <?php echo $book['quantity']; ?></p>
                                        </div>
                                        
                                        <div class="book-actions">
                                            <form method="POST" action="user.php">
                                                <input type="hidden" name="action" value="borrow">
                                                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                <button type="submit" class="btn btn-primary w-100" <?php echo ($active_loans_count >= $max_loans) ? 'disabled' : ''; ?>>
                                                    Borrow
                                                </button>
                                                <?php if ($active_loans_count >= $max_loans): ?>
                                                    <small class="text-danger d-block mt-1">You've reached your borrowing limit</small>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <p class="text-center">No books available matching your search criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination - Bottom -->
                    <?php echo getPaginationHTML($current_page, $total_pages, $pagination_url_params); ?>
                </div>
            </div>
            
            <!-- My Borrowed Books -->
            <div class="card">
                <div class="card-header">
                    <h2>My Borrowed Books</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($borrowed_books && $borrowed_books->num_rows > 0): ?>
                            <?php while ($loan = $borrowed_books->fetch_assoc()): ?>
                                <?php $is_overdue = strtotime($loan['due_date']) < time(); ?>
                                <div class="col-md-4 col-lg-3">
                                    <div class="book-card <?php echo $is_overdue ? 'border-danger' : ''; ?>">
                                        <div class="book-title"><?php echo htmlspecialchars($loan['title']); ?></div>
                                        <div class="book-info">
                                            <p><strong>Author:</strong> <?php echo htmlspecialchars($loan['author']); ?></p>
                                            
                                            <?php if ($loan['genre']): ?>
                                                <p><strong>Genre:</strong> <?php echo htmlspecialchars($loan['genre']); ?></p>
                                            <?php endif; ?>
                                            
                                            <p>
                                                <strong>Due Date:</strong> 
                                                <span class="due-date <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                                    <?php echo date('M d, Y', strtotime($loan['due_date'])); ?>
                                                    <?php if ($is_overdue): ?>
                                                        (<?php echo floor((time() - strtotime($loan['due_date'])) / (60*60*24)); ?> days overdue)
                                                        <br><small class="text-danger">Returning this book late will affect your borrowing limit</small>
                                                    <?php endif; ?>
                                                </span>
                                            </p>
                                        </div>
                                        
                                        <div class="book-actions">
                                            <form method="POST" action="user.php">
                                                <input type="hidden" name="action" value="return">
                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                <button type="submit" class="btn btn-success w-100">Return Book</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <p class="text-center">You haven't borrowed any books yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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
   
    <script>
        // Script to highlight overdue books
        document.addEventListener('DOMContentLoaded', function() {
            const overdueElements = document.querySelectorAll('.overdue');
            if (overdueElements.length > 0) {
                overdueElements.forEach(function(element) {
                    const card = element.closest('.book-card');
                    if (card) {
                        card.style.borderColor = '#dc3545';
                        card.style.borderWidth = '2px';
                    }
                });
            }
        });
    </script>
</body>
</html>