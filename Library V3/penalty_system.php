<?php

function calculateUserPenalties($conn, $user_id) {
    // Initialize result array
    $result = [
        'penalty_count' => 0,
        'max_allowed_loans' => 5, // Default max loans
        'penalty_expiry' => null,
        'active_penalties' => [],
        'total_days_overdue' => 0
    ];
    
    // Get all overdue returns in the last 3 months
    $three_months_ago = date('Y-m-d H:i:s', strtotime('-3 months'));
    
    $sql = "SELECT l.*, b.title, DATEDIFF(l.return_date, l.due_date) as days_overdue 
            FROM loans l 
            JOIN books b ON l.book_id = b.id 
            WHERE l.user_id = ? 
              AND l.status = 'returned' 
              AND l.return_date > l.due_date
              AND l.return_date >= ?
            ORDER BY l.return_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $three_months_ago);
    $stmt->execute();
    $penalties = $stmt->get_result();
    $stmt->close();
    
    // Process each penalty
    while ($penalty = $penalties->fetch_assoc()) {
        $days_overdue = (int)$penalty['days_overdue'];
        
        // Only count as penalty if more than 1 day overdue
        if ($days_overdue > 1) {
            $result['total_days_overdue'] += $days_overdue;
            $result['penalty_count']++;
            $result['active_penalties'][] = [
                'book_title' => $penalty['title'],
                'days_overdue' => $days_overdue,
                'returned_date' => $penalty['return_date']
            ];
        }
    }
    
    // Calculate reduced loan allowance based on penalties
    // For each penalty, reduce max loans by 1, minimum of 1 loan allowed
    $max_allowed = 5 - $result['penalty_count'];
    $result['max_allowed_loans'] = max(1, $max_allowed);
    
    // Set penalty expiry date (penalties expire after 3 months)
    if ($result['penalty_count'] > 0) {
        // Get the most recent penalty to calculate expiry
        $sql = "SELECT MAX(return_date) as latest_penalty_date 
                FROM loans 
                WHERE user_id = ? 
                  AND status = 'returned' 
                  AND return_date > due_date";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $latest = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($latest && $latest['latest_penalty_date']) {
            $result['penalty_expiry'] = date('Y-m-d', strtotime($latest['latest_penalty_date'] . ' +3 months'));
        }
    }
    
    return $result;
}

function getMaxAllowedLoans($conn, $user_id) {
    $penalties = calculateUserPenalties($conn, $user_id);
    return $penalties['max_allowed_loans'];
}


function userHasPenalties($conn, $user_id) {
    $penalties = calculateUserPenalties($conn, $user_id);
    return $penalties['penalty_count'] > 0;
}


function getPenaltyDetailsHtml($conn, $user_id) {
    $penalties = calculateUserPenalties($conn, $user_id);
    
    if ($penalties['penalty_count'] == 0) {
        return '<p>You have no active penalties.</p>';
    }
    
    $html = '<div class="alert alert-warning">';
    $html .= '<h5>Penalty Information</h5>';
    $html .= '<p>You currently have ' . $penalties['penalty_count'] . ' active penalties, reducing your maximum allowed loans to ' . $penalties['max_allowed_loans'] . '.</p>';
    
    if ($penalties['penalty_expiry']) {
        $html .= '<p>These penalties will expire on: <strong>' . date('F j, Y', strtotime($penalties['penalty_expiry'])) . '</strong></p>';
    }
    
    $html .= '<p>Recent overdue returns:</p><ul>';
    foreach ($penalties['active_penalties'] as $penalty) {
        $html .= '<li><strong>' . htmlspecialchars($penalty['book_title']) . '</strong> - ' . $penalty['days_overdue'] . ' days overdue (returned on ' . date('M d, Y', strtotime($penalty['returned_date'])) . ')</li>';
    }
    $html .= '</ul>';
    
    $html .= '</div>';
    
    return $html;
}
?>