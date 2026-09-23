<?php

session_start();

require_once __DIR__ . '/../utils/auditLogger.php';

// Log only admin logout before destroying the session
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Admin') {
    logAudit("Admin logged out");
}

// Destroy session
session_unset();
session_destroy();

header("Location: ../../frontend/index.php");
exit;

?>