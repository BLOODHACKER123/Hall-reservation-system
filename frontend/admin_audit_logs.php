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

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;

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

// Count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM $auditTable $whereSql");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages   = max(1, ceil($totalRecords / $limit));
$page = min($page, (int)$totalPages);
$offset = ($page - 1) * $limit;

// Fetch paginated logs
$stmt = $pdo->prepare("
    SELECT * FROM $auditTable
    $whereSql 
    ORDER BY log_id DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch distinct roles for filtering
$rolesStmt = $pdo->query("SELECT DISTINCT user_role FROM $auditTable WHERE user_role IS NOT NULL ORDER BY user_role ASC");
$availableRoles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>System Audit Logs | VenueVista Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="common.css" />
  <style>

    body { 
      font-family: -apple-system,BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
      background: #f8fafc; 
      padding: 24px;
      color: #1e293b; 
    }

    .container { 
      max-width: 1300px; 
      margin: 0 auto; 
      background: #fff; 
      border-radius: 8px; 
      box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
      padding: 24px; 
    }

    .header { 
      display: flex; 
      flex-wrap: wrap;
      gap: 16px;
      justify-content: space-between; 
      align-items: center; 
      border-bottom: 1px solid #e2e8f0; 
      padding-bottom: 16px; 
      margin-bottom: 20px; 
    }

    .filter-bar { 
      display: flex; 
      gap: 12px; 
      flex-wrap: wrap; 
      margin-bottom: 20px; 
      align-items: flex-end; 
    }

    .filter-bar label { 
      display: flex; 
      flex: 1 1 180px;
      min-width: 0;
      flex-direction: column; 
      font-size: 13px; 
      font-weight: 600; 
      color: #64748b; 
    }

    .filter-bar input,
    .filter-bar select { 
      padding: 8px 12px; 
      border: 1px solid #cbd5e1; 
      border-radius: 6px; 
      margin-top: 4px; 
    }

    .btn { 
      padding: 8px 16px; 
      border-radius: 6px; 
      border: none; 
      cursor: pointer; 
      font-weight: 600; 
      text-decoration: none; 
      display: inline-flex; 
      align-items: center; 
      gap: 6px; 
      font-size: 13px; 
    }

    .btn-dark { 
      background: #0f172a; 
      color: #fff; 
    }

    .btn-green { 
      background: #16a34a; 
      color: #fff; 
    }

    table { 
      width: 100%; 
      border-collapse: collapse; 
      font-size: 13px; 
      margin-top: 10px; 
    }

    th, td { 
      text-align: left; 
      padding: 12px; 
      border-bottom: 1px solid #f1f5f9; 
    }

    th { 
      background: #f8fafc; 
      color: #475569; 
      text-transform: uppercase; 
      font-size: 11px; 
      letter-spacing: 0.05em; 
    }

    .badge { 
      padding: 4px 8px; 
      border-radius: 4px; 
      font-size: 11px; 
      font-weight: 600; 
    }

    .badge-Admin { 
      background: #fee2e2; 
      color: #991b1b; 
    }

    .badge-Vendor { 
      background: #fef3c7; 
      color: #92400e; 
    }

    .badge-Customer { 
      background: #dbeafe; 
      color: #1e40af; 
    }

    .badge-Guest { 
      background: #f1f5f9; 
      color: #475569; 
    }

    .pagination { 
      display: flex; 
      flex-wrap: wrap;
      gap: 16px;
      justify-content: space-between; 
      align-items: center; 
      margin-top: 20px; 
    }

    .header-actions { 
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    td { 
      overflow-wrap: anywhere;
     }
     
    @media (max-width: 600px) {
      body { padding: 12px; }
      .container { padding: 16px; }
      .filter-bar label { flex-basis: 100%; }
      .btn { min-height: 44px; }
    }

  </style>
</head>

<body>
  <div class="container">
    <div class="header">
      <div>
        <h2>System Activity & Audit Trail</h2>
        <small>Admins-only visibility into operational events, logins, and changes.</small>
      </div>
      <div class="header-actions">
        <a href="admin.php" class="btn" style="background:#e2e8f0; color:#334155;">
          <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
        <a href="export_audit_report.php?<?= http_build_query($_GET) ?>" class="btn btn-green">
          <i class="fa-solid fa-file-csv"></i> Download CSV Report
        </a>
      </div>
    </div>

    <!-- Filters -->
    <form method="get" class="filter-bar">
      <label>
        Role
        <select name="role">
          <option value="">All Roles</option>
          <?php foreach ($availableRoles as $r): ?>
            <option value="<?= htmlspecialchars($r) ?>" <?= $role_filter === $r ? 'selected' : '' ?>>
              <?= htmlspecialchars($r) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Search Action
        <input type="text" name="search" placeholder="e.g. login, booked, deleted" value="<?= htmlspecialchars($search) ?>">
      </label>

      <label>
        From Date
        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
      </label>

      <label>
        To Date
        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
      </label>

      <button type="submit" class="btn btn-dark"><i class="fa-solid fa-filter"></i> Apply Filter</button>
      <a href="admin_audit_logs.php" class="btn" style="background:#e2e8f0; color:#334155;">Reset</a>
    </form>

    <!-- Table -->
    <div class="table-scroll" role="region" aria-label="Audit records" tabindex="0">
    <table>
      <thead>
        <tr>
          <th>Log ID</th>
          <th>User</th>
          <th>Role</th>
          <th>Action Summary</th>
          <th>IP Address</th>
          <th>Timestamp</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($logs)): ?>
          <?php foreach ($logs as $log): ?>
            <?php 
              $role = $log['user_role'] ?? 'Guest';
              $badgeClass = 'badge-' . ($role ?: 'Guest');
            ?>
            <tr>
              <td>#<?= (int)$log['log_id'] ?></td>
              <td><?= $log['user_id'] ? 'User #' . (int)$log['user_id'] : '<em>Guest / Unregistered</em>' ?></td>
              <td><span class="badge <?= htmlspecialchars($badgeClass) ?>"><?= htmlspecialchars($role) ?></span></td>
              <td><?= nl2br(htmlspecialchars($log['action'])) ?></td>
              <td><code><?= htmlspecialchars($log['ip_address']) ?></code></td>
              <td><?= htmlspecialchars($log['timestamp']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" style="text-align:center; padding: 24px; color: #94a3b8;">No audit records found matching your query.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>

    <div class="pagination">
      <span>Showing <?= count($logs) ?> of <?= $totalRecords ?> records (Page <?= $page ?> of <?= $totalPages ?>)</span>
      <div>
        <?php if ($page > 1): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn" style="background:#e2e8f0;">&larr; Prev</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn" style="background:#e2e8f0;">Next &rarr;</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
