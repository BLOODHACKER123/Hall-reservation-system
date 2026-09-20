<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Logs an event into the audit_logs table.
 *
 * @param string $action       Description of what happened
 * @param int|null $userId     Optional user ID (defaults to $_SESSION['user_id'])
 * @param string|null $role    Optional role (defaults to $_SESSION['user_type'] or 'Guest')
 */
function logAudit(string $action, ?int $userId = null, ?string $role = null): bool {
    global $pdo;

    $resolvedUserId = $userId ?? ($_SESSION['user_id'] ?? null);
    $resolvedRole   = $role ?? ($_SESSION['user_type'] ?? ($resolvedUserId ? 'User' : 'Guest'));

    // Capture real client IP address
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, user_role, action, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$resolvedUserId, $resolvedRole, $action, $ip]);
    } catch (PDOException $e) {
        error_log("Audit Log Error: " . $e->getMessage());
        return false;
    }
}