<?php
function getAvailableBookCount($conn, $book_id) {
    $sql = "SELECT available_quantity FROM books WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['available_quantity'];
    }
    
    return 0;
}

function getPopularBooks($conn, $limit = 5) {
    $sql = "SELECT b.*, COUNT(l.id) as loan_count 
            FROM books b 
            JOIN loans l ON b.id = l.book_id 
            GROUP BY b.id 
            ORDER BY loan_count DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $popular_books = [];
    while ($book = $result->fetch_assoc()) {
        $popular_books[] = $book;
    }
    
    return $popular_books;
}


function getRecentBooks($conn, $limit = 5) {
    $sql = "SELECT * FROM books ORDER BY id DESC LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $recent_books = [];
    while ($book = $result->fetch_assoc()) {
        $recent_books[] = $book;
    }
    
    return $recent_books;
}

function getBooksByGenre($conn, $genre, $limit = 10) {
    $sql = "SELECT * FROM books WHERE genre = ? ORDER BY title LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $genre, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $genre_books = [];
    while ($book = $result->fetch_assoc()) {
        $genre_books[] = $book;
    }
    
    return $genre_books;
}

function searchBooks($conn, $search_term, $limit = 20) {
    $search_term = "%{$search_term}%";
    
    $sql = "SELECT * FROM books 
            WHERE title LIKE ? 
            OR author LIKE ? 
            OR isbn LIKE ? 
            OR genre LIKE ? 
            OR description LIKE ? 
            ORDER BY title 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $search_term, $search_term, $search_term, $search_term, $search_term, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $search_results = [];
    while ($book = $result->fetch_assoc()) {
        $search_results[] = $book;
    }
    
    return $search_results;
}


function getBookDetails($conn, $book_id) {
    $sql = "SELECT * FROM books WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return false;
}

function getLowInventoryBooks($conn, $threshold = 2) {
    $sql = "SELECT * FROM books WHERE available_quantity <= ? ORDER BY available_quantity";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $threshold);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $low_inventory = [];
    while ($book = $result->fetch_assoc()) {
        $low_inventory[] = $book;
    }
    
    return $low_inventory;
}
?>