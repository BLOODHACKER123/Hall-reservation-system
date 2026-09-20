<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

// Ensure the user is an authenticated Customer
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'Customer') {
    echo json_encode([
        'success' => false, 
        'message' => 'Please log in as a customer to save venues to your wishlist.'
    ]);
    exit;
}

$customer_id = (int)$_SESSION['user_id'];
$hall_id = isset($_POST['hall_id']) ? (int)$_POST['hall_id'] : 0;

if ($hall_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid venue identifier.']);
    exit;
}

try {
    // Check if the venue is already bookmarked
    $check_stmt = $pdo->prepare("SELECT 1 FROM wishlists WHERE customer_id = ? AND hall_id = ?");
    $check_stmt->execute([$customer_id, $hall_id]);
    $exists = $check_stmt->fetchColumn();

    if ($exists) {
        // Remove from wishlist
        $del_stmt = $pdo->prepare("DELETE FROM wishlists WHERE customer_id = ? AND hall_id = ?");
        $del_stmt->execute([$customer_id, $hall_id]);
        $action = 'removed';
    } else {
        // Add to wishlist
        $ins_stmt = $pdo->prepare("INSERT INTO wishlists (customer_id, hall_id, added_at) VALUES (?, ?, NOW())");
        $ins_stmt->execute([$customer_id, $hall_id]);
        $action = 'added';
    }

    // Get the updated wishlist count for this customer
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE customer_id = ?");
    $count_stmt->execute([$customer_id]);
    $total_count = (int)$count_stmt->fetchColumn();

    echo json_encode([
        'success' => true, 
        'action' => $action, 
        'total_count' => $total_count
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}