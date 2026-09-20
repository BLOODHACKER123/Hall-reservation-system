<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/notify.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    // 1. SEND MESSAGE
    if ($action === 'send') {
        $receiver_id = intval($_POST['receiver_id'] ?? 0);
        $hall_id = intval($_POST['hall_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if ($receiver_id <= 0 || $hall_id <= 0 || empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Missing message parameters']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, hall_id, content, sent_at, read_status)
            VALUES (?, ?, ?, ?, NOW(), 0)
        ");
        $stmt->execute([$current_user_id, $receiver_id, $hall_id, $content]);

        // Send a notification to the recipient
        $sender_name = $_SESSION['user_name'] ?? 'Someone';
        createNotification($pdo, $receiver_id, "New Message", "$sender_name sent you a message.", "message");

        echo json_encode(['success' => true, 'sent_at' => date('M j, g:i a')]);
        exit;
    }

    // 2. FETCH ACTIVE CONVERSATION THREAD
    if ($action === 'fetch_thread') {
        $partner_id = intval($_GET['partner_id'] ?? 0);
        $hall_id = intval($_GET['hall_id'] ?? 0);

        if ($partner_id <= 0 || $hall_id <= 0) {
            echo json_encode(['success' => false, 'messages' => []]);
            exit;
        }

        // Mark incoming messages as read
        $mark = $pdo->prepare("
            UPDATE messages 
            SET read_status = 1 
            WHERE sender_id = ? AND receiver_id = ? AND hall_id = ? AND read_status = 0
        ");
        $mark->execute([$partner_id, $current_user_id, $hall_id]);

        // Fetch thread messages
        $stmt = $pdo->prepare("
            SELECT m.*, u.first_name, u.last_name
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.hall_id = ?
              AND (
                  (m.sender_id = ? AND m.receiver_id = ?) 
                  OR 
                  (m.sender_id = ? AND m.receiver_id = ?)
              )
            ORDER BY m.sent_at ASC
        ");
        $stmt->execute([$hall_id, $current_user_id, $partner_id, $partner_id, $current_user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}