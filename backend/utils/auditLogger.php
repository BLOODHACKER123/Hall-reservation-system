<?php

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Logs an admin action into the audit_logs table.
 *
 * @param string $action Description of what the admin did
 */
function logAudit(string $action): bool
{
    global $pdo;

    // Only authenticated admins can create audit logs
    if (
        !isset($_SESSION['user_id']) ||
        !isset($_SESSION['user_type']) ||
        $_SESSION['user_type'] !== 'Admin'
    ) {
        return false;
    }

    $adminId = (int) $_SESSION['user_id'];

    // Capture client IP address
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '127.0.0.1';

    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (admin_id, action, ip_address)
            VALUES (?, ?, ?)
        ");

        return $stmt->execute([
            $adminId,
            $action,
            $ip
        ]);

    } catch (PDOException $e) {
        error_log("Audit Log Error: " . $e->getMessage());
        return false;
    }
}