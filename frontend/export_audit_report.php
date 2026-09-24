<?php
session_start();
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/utils/auditLogger.php';

// Strict Admin Gatekeeper
if (empty($_SESSION['user_id']) || empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    http_response_code(403);
    die("Access Denied: Admins only.");
}

$role_filter = trim($_GET['role'] ?? '');
$search      = trim($_GET['search'] ?? '');
$date_from   = trim($_GET['date_from'] ?? '');
$date_to     = trim($_GET['date_to'] ?? '');

$where  = [];
$params = [];

if ($role_filter !== '') {
    $where[]  = "user_role = ?";
    $params[] = $role_filter;
}
if ($search !== '') {
    $where[]  = "action LIKE ?";
    $params[] = "%$search%";
}
if ($date_from !== '') {
    $where[]  = "timestamp >= ?";
    $params[] = $date_from . " 00:00:00";
}
if ($date_to !== '') {
    $where[]  = "timestamp <= ?";
    $params[] = $date_to . " 23:59:59";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$auditTable = auditLogHasRoles() ? 'audit_logs'
    : "(SELECT *, admin_id AS user_id, 'Admin' AS user_role FROM audit_logs) AS audit_entries";
$stmt = $pdo->prepare("SELECT * FROM $auditTable $whereSql ORDER BY log_id DESC");
$stmt->execute($params);

$filename = "audit_report_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Log ID', 'User ID', 'User Role', 'Action', 'IP Address', 'Timestamp'], ',', '"', '');

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['log_id'],
        $row['user_id'] ?? 'Guest',
        $row['user_role'] ?? 'Guest',
        $row['action'],
        $row['ip_address'],
        $row['timestamp']
    ], ',', '"', '');
}

fclose($output);
exit;
