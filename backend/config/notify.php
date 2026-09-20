<?php
// Function to create a notification record
function createNotification(PDO $pdo, int $user_id, string $title, string $message, string $type = 'info'): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, read_status, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        return $stmt->execute([$user_id, $title, $message, $type]);
    } catch (Throwable $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}