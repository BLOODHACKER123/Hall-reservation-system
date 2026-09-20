<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'fetch';

try {
    if ($action === 'fetch') {
        // Fetch the 10 most recent notifications
        $stmt = $pdo->prepare("
            SELECT notification_id, title, message, type, read_status, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count unread
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
        $count_stmt->execute([$user_id]);
        $unread_count = (int)$count_stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        exit;
    }

    if ($action === 'mark_read') {
        // Mark all as read
        $upd_stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE user_id = ?");
        $upd_stmt->execute([$user_id]);

        echo json_encode(['success' => true, 'unread_count' => 0]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}