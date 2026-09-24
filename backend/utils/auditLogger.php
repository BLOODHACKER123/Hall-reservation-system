<?php

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function auditLogHasRoles(): bool
{
    global $pdo;
    static $hasRoles = null;
    if ($hasRoles === null) {
        try {
            $hasRoles = (bool)$pdo->query("SHOW COLUMNS FROM audit_logs LIKE 'user_role'")->fetch();
        } catch (PDOException $e) {
            $hasRoles = false;
        }
    }
    return $hasRoles;
}

/**
 * Logs any event to audit_logs for any user role or guest
 */
function logAudit(string $action, ?int $userId = null, ?string $role = null): bool
{
    global $pdo;

    if (!isset($pdo)) {
        error_log("[AuditLogger Error] Database PDO connection not found.");
        return false;
    }

    // Resolve user ID & Role from parameters or active session
    $resolvedUserId = $userId ?: ($_SESSION['user_id'] ?? null);
    $resolvedRole   = $role ?: ($_SESSION['user_type'] ?? ($resolvedUserId ? 'User' : 'Guest'));

    // Capture real client IP address
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '127.0.0.1';

    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    try {
        if (auditLogHasRoles()) {
            // New structure: user_id + dynamic user_role
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, user_role, action, ip_address)
                VALUES (?, ?, ?, ?)
            ");
            return $stmt->execute([
                $resolvedUserId,
                $resolvedRole,
                $action,
                $ip
            ]);
        } else {
            // Legacy fallback if user_role column hasn't been added
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (admin_id, action, ip_address)
                VALUES (?, ?, ?)
            ");
            return $stmt->execute([
                $resolvedUserId ?? 0,
                $action,
                $ip
            ]);
        }
    } catch (PDOException $e) {
        error_log("Audit Log Error: " . $e->getMessage());
        return false;
    }
}