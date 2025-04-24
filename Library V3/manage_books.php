<?php
// Enable error reporting for debugging
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

// Process book actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add a new book
        if (isset($_POST['action']) && $_POST['action'] === 'add_book') {
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $author = isset($_POST['author']) ? trim($_POST['author']) : '';
            $isbn = isset($_POST['isbn']) ? trim($_POST['isbn']) : '';
            $genre = isset($_POST['genre']) ? trim($_POST['genre']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            $published_year = isset($_POST['published_year']) ? (int)$_POST['published_year'] : null;
            $publisher = isset($_POST['publisher']) ? trim($_POST['publisher']) : '';
            
            // Validate inputs
            if (empty($title) || empty($author)) {
                throw new Exception("Title and author are required.");
            }
            
            // All books start with available quantity = quantity
            $available_quantity = $quantity;
            
            // Insert the new book
            $stmt = $conn->prepare("INSERT INTO books (title, author, isbn, genre, description, quantity, available_quantity, published_year, publisher) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssiiis", $title, $author, $isbn, $genre, $description, $quantity, $available_quantity, $published_year, $publisher);
            
            if (!$stmt->execute()) {
                throw new Exception("Error adding book: " . $stmt->error);
            }
            
            $_SESSION['success'] = "Book added successfully!";
            header("Location: manage_books.php");
            exit();
        }
        
        // Update an existing book
        if (isset($_POST['action']) && $_POST['action'] === 'update_book') {
            $book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $author = isset($_POST['author']) ? trim($_POST['author']) : '';
            $isbn = isset($_POST['isbn']) ? trim($_POST['isbn']) : '';
            $genre = isset($_POST['genre']) ? trim($_POST['genre']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            $published_year = isset($_POST['published_year']) ? (int)$_POST['published_year'] : null;
            $publisher = isset($_POST['publisher']) ? trim($_POST['publisher']) : '';
            
            // Validate inputs
            if (empty($title) || empty($author) || $book_id <= 0) {
                throw new Exception("Title, author, and book ID are required.");
            }
            
            // Get current book data
            $stmt = $conn->prepare("SELECT quantity, available_quantity FROM books WHERE id = ?");
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Book not found.");
            }
            
            $current_book = $result->fetch_assoc();
            $stmt->close();
            
            // Calculate how many books are currently loaned out
            $loaned_out = $current_book['quantity'] - $current_book['available_quantity'];
            
            // Ensure we don't set available_quantity lower than possible
            $available_quantity = max($quantity - $loaned_out, 0);
            
            // Update the book
            $stmt = $conn->prepare("UPDATE books SET title = ?, author = ?, isbn = ?, genre = ?, description = ?, quantity = ?, available_quantity = ?, published_year = ?, publisher = ? WHERE id = ?");
            $stmt->bind_param("sssssiiisi", $title, $author, $isbn, $genre, $description, $quantity, $available_quantity, $published_year, $publisher, $book_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating book: " . $stmt->error);
            }
            
            $_SESSION['success'] = "Book updated successfully!";
            header("Location: manage_books.php");
            exit();
        }
        
        // Delete a book
        if (isset($_POST['action']) && $_POST['action'] === 'delete_book') {
            $book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
            
            // Validate inputs
            if ($book_id <= 0) {
                throw new Exception("Invalid book ID.");
            }
            
            // Check if book has active loans
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM loans WHERE book_id = ? AND status = 'borrowed'");
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $active_loans = $stmt->get_result()->fetch_assoc()['count'];
            $stmt->close();
            
            if ($active_loans > 0) {
                throw new Exception("Cannot delete book: it has active loans. Please wait until all copies are returned.");
            }
            
            // Delete the book
            $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
            $stmt->bind_param("i", $book_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error deleting book: " . $stmt->error);
            }
            
            $_SESSION['success'] = "Book deleted successfully!";
            header("Location: manage_books.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: manage_books.php");
        exit();
    }
}

// Get all books for display
try {
    $sql = "SELECT * FROM books ORDER BY title";
    $books_result = $conn->query($sql);
    
    if (!$books_result) {
        throw new Exception("Error fetching books: " . $conn->error);
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    $books_result = null;
}

// Get unique genres for filter
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/bootstrap.css">
    <title>Manage Books - Honest Preparatory Library</title>
    <style>
        body {
            padding: 20px;
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
        
        .logout-button {
            float: right;
            margin-top: 10px;
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
        .book-actions {
            display: flex;
            gap: 5px;
        }
        .container {
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
    <div class="container">
        <h1>Manage Books</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</p>
        
        <!-- Navigation Links -->
        <div class="mb-4">
            <a href="admin.php" class="btn btn-secondary">Dashboard</a>
            <a href="manage_books.php" class="btn btn-primary">Manage Books</a>
            <a href="manage_users.php" class="btn btn-secondary">Manage Users</a>
            <a href="manage_loans.php" class="btn btn-secondary">Manage Loans</a>
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
        
        <!-- Add Book Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h2>Add New Book</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="manage_books.php">
                    <input type="hidden" name="action" value="add_book">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="author" class="form-label">Author</label>
                            <input type="text" class="form-control" id="author" name="author" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="isbn" class="form-label">ISBN</label>
                            <input type="text" class="form-control" id="isbn" name="isbn">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="genre" class="form-label">Genre</label>
                            <input type="text" class="form-control" id="genre" name="genre">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="published_year" class="form-label">Published Year</label>
                            <input type="number" class="form-control" id="published_year" name="published_year" min="1000" max="<?php echo date('Y'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="publisher" class="form-label">Publisher</label>
                            <input type="text" class="form-control" id="publisher" name="publisher">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Book</button>
                </form>
            </div>
        </div>
        
        <!-- Books List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Books List</h2>
            </div>
            <div class="card-body">
                <!-- Search and Filter -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" id="searchBooks" class="form-control" placeholder="Search books...">
                    </div>
                    <div class="col-md-3">
                        <select id="filterGenre" class="form-select">
                            <option value="">All Genres</option>
                            <?php if ($genres_result && $genres_result->num_rows > 0): ?>
                                <?php while ($genre = $genres_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($genre['genre']); ?>">
                                        <?php echo htmlspecialchars($genre['genre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button id="resetFilters" class="btn btn-secondary w-100">Reset Filters</button>
                    </div>
                </div>
                
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Genre</th>
                            <th>Published</th>
                            <th>Available / Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($books_result && $books_result->num_rows > 0): ?>
                            <?php while ($book = $books_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><?php echo htmlspecialchars($book['genre']); ?></td>
                                    <td><?php echo $book['published_year'] ? htmlspecialchars($book['published_year']) : '-'; ?></td>
                                    <td><?php echo $book['available_quantity'] . ' / ' . $book['quantity']; ?></td>
                                    <td class="book-actions">
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editBookModal"
                                                data-book-id="<?php echo $book['id']; ?>"
                                                data-book-title="<?php echo htmlspecialchars($book['title']); ?>"
                                                data-book-author="<?php echo htmlspecialchars($book['author']); ?>"
                                                data-book-isbn="<?php echo htmlspecialchars($book['isbn']); ?>"
                                                data-book-genre="<?php echo htmlspecialchars($book['genre']); ?>"
                                                data-book-description="<?php echo htmlspecialchars($book['description']); ?>"
                                                data-book-quantity="<?php echo $book['quantity']; ?>"
                                                data-book-published-year="<?php echo $book['published_year']; ?>"
                                                data-book-publisher="<?php echo htmlspecialchars($book['publisher']); ?>">
                                            Edit
                                        </button>
                                        
                                        <form method="POST" action="manage_books.php" onsubmit="return confirm('Are you sure you want to delete this book?');">
                                            <input type="hidden" name="action" value="delete_book">
                                            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No books found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Edit Book Modal -->
    <div class="modal fade" id="editBookModal" tabindex="-1" aria-labelledby="editBookModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBookModalLabel">Edit Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editBookForm" method="POST" action="manage_books.php">
                        <input type="hidden" name="action" value="update_book">
                        <input type="hidden" id="edit_book_id" name="book_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="edit_title" name="title" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_author" class="form-label">Author</label>
                                <input type="text" class="form-control" id="edit_author" name="author" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_isbn" class="form-label">ISBN</label>
                                <input type="text" class="form-control" id="edit_isbn" name="isbn">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_genre" class="form-label">Genre</label>
                                <input type="text" class="form-control" id="edit_genre" name="genre">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="edit_quantity" name="quantity" min="1" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_published_year" class="form-label">Published Year</label>
                                <input type="number" class="form-control" id="edit_published_year" name="published_year" min="1000" max="<?php echo date('Y'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_publisher" class="form-label">Publisher</label>
                                <input type="text" class="form-control" id="edit_publisher" name="publisher">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editBookForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate edit modal with book data
        const editModal = document.getElementById('editBookModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                
                // Extract book data from button attributes
                const bookId = button.getAttribute('data-book-id');
                const bookTitle = button.getAttribute('data-book-title');
                const bookAuthor = button.getAttribute('data-book-author');
                const bookIsbn = button.getAttribute('data-book-isbn');
                const bookGenre = button.getAttribute('data-book-genre');
                const bookDescription = button.getAttribute('data-book-description');
                const bookQuantity = button.getAttribute('data-book-quantity');
                const bookPublishedYear = button.getAttribute('data-book-published-year');
                const bookPublisher = button.getAttribute('data-book-publisher');
                
                // Populate form fields
                document.getElementById('edit_book_id').value = bookId;
                document.getElementById('edit_title').value = bookTitle;
                document.getElementById('edit_author').value = bookAuthor;
                document.getElementById('edit_isbn').value = bookIsbn;
                document.getElementById('edit_genre').value = bookGenre;
                document.getElementById('edit_description').value = bookDescription;
                document.getElementById('edit_quantity').value = bookQuantity;
                document.getElementById('edit_published_year').value = bookPublishedYear;
                document.getElementById('edit_publisher').value = bookPublisher;
            });
        }
        
        // Search and filter functionality
        $(document).ready(function() {
            // Search books
            $("#searchBooks").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("table tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });
            
            // Filter by genre
            $("#filterGenre").on("change", function() {
                var value = $(this).val().toLowerCase();
                if (value === "") {
                    $("table tbody tr").show();
                } else {
                    $("table tbody tr").filter(function() {
                        var genre = $(this).find("td:nth-child(3)").text().toLowerCase();
                        $(this).toggle(genre === value);
                    });
                }
            });
            
            // Reset filters
            $("#resetFilters").on("click", function() {
                $("#searchBooks").val("");
                $("#filterGenre").val("");
                $("table tbody tr").show();
            });
        });
    </script>
</body>
</html>