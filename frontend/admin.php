<?php
session_start();
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/utils/auditLogger.php';

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: loginchoice.php");
    exit;
}

// Kick out anyone who is not an Admin
if ($_SESSION['user_type'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

// Force errors to display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$admin_id = (int)$_SESSION['user_id'];
$admin_name =$_SESSION['user_name'] ?? 'Admin';

// Read flash message from session
$flash_msg =$_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);

// -------------------------------------------------------------
// 1. Handle Create New Admin
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $password   =$_POST['password'] ?? '';

    if (!empty($first_name) && !empty($last_name) && !empty($email) && !empty($phone) && !empty($password)) {
        try {
            $pdo->beginTransaction();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt =$pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, user_type, is_active) VALUES (?, ?, ?, ?, ?, 'Admin', 1)");
            $stmt->execute([$first_name, $last_name,$email, $phone,$hashed_password]);
            $new_admin_id =$pdo->lastInsertId();
            
            $stmt2 =$pdo->prepare("INSERT INTO admins (user_id, admin_level, role) VALUES (?, 1, 'System Admin')");
            $stmt2->execute([$new_admin_id]);$pdo->commit();

            logAudit("Admin {$admin_name} created new admin: {$email} (User ID: #{$new_admin_id})", $admin_id, 'Admin');$_SESSION['flash_msg'] = ['type' => 'success', 'text' => "New admin {$email} created successfully!"];

        } catch (Throwable $e) {
            if ($pdo->inTransaction())$pdo->rollBack();
            logAudit("Admin {$admin_name} failed to create admin {$email}: " . $e->getMessage(),$admin_id, 'Admin');
            $_SESSION['flash_msg'] = ['type' => 'error', 'text' => "Error: " . $e->getMessage()];
        }
    } else {
        $_SESSION['flash_msg'] = ['type' => 'error', 'text' => "Please fill in all required fields."];
    }
    header("Location: admin.php#system-admins");
    exit;
}

// -------------------------------------------------------------
// 2. Handle Approve / Reject Pending Venues
// -------------------------------------------------------------
if (isset($_GET['action']) && isset($_GET['id'])) {
    $hall_id = intval($_GET['id']);

    $venue_stmt =$pdo->prepare("SELECT name FROM halls WHERE hall_id = ?");
    $venue_stmt->execute([$hall_id]);
    $venue =$venue_stmt->fetch(PDO::FETCH_ASSOC);

    if ($venue) {
        if ($_GET['action'] === 'approve') {
            $stmt =$pdo->prepare("UPDATE halls SET is_active = 1 WHERE hall_id = ?");
            $stmt->execute([$hall_id]);

            logAudit("Admin approved venue: '{$venue['name']}' (Hall ID: #{$hall_id})", $admin_id, 'Admin');
            $_SESSION['flash_msg'] = ['type' => 'success', 'text' => "Venue '{$venue['name']}' approved."];

        } elseif ($_GET['action'] === 'reject') {
            $stmt =$pdo->prepare("DELETE FROM halls WHERE hall_id = ?");
            $stmt->execute([$hall_id]);

            logAudit("Admin rejected and deleted venue: '{$venue['name']}' (Hall ID: #{$hall_id})", $admin_id, 'Admin');
            $_SESSION['flash_msg'] = ['type' => 'success', 'text' => "Venue '{$venue['name']}' rejected & removed."];
        }
    }

    header("Location: admin.php#pending-venues");
    exit;
}

// -------------------------------------------------------------
// 3. Handle Enable / Disable Venues
// -------------------------------------------------------------
if (isset($_GET['toggle_venue']) && isset($_GET['status'])) {
    $hall_id = intval($_GET['toggle_venue']);
    $new_status = intval($_GET['status']);

    $venue_stmt =$pdo->prepare("SELECT name FROM halls WHERE hall_id = ?");
    $venue_stmt->execute([$hall_id]);
    $venue =$venue_stmt->fetch(PDO::FETCH_ASSOC);

    if ($venue) {
        $stmt =$pdo->prepare("UPDATE halls SET is_active = ? WHERE hall_id = ?");
        $stmt->execute([$new_status,$hall_id]);

        $action_label =$new_status === 1 ? "enabled" : "disabled";
        logAudit("Admin {$action_label} venue: '{$venue['name']}' (Hall ID: #{$hall_id})", $admin_id, 'Admin');$_SESSION['flash_msg'] = ['type' => 'success', 'text' => "Venue '{$venue['name']}' successfully {$action_label}."];
    }

    header("Location: admin.php#all-venues");
    exit;
}

// -------------------------------------------------------------
// 4. Handle Suspend / Activate Users (Vendors and Customers)
// -------------------------------------------------------------
if (isset($_GET['toggle_user']) && isset($_GET['status'])) {
    $target_uid = intval($_GET['toggle_user']);
    $new_status = intval($_GET['status']);
    $tab =$_GET['tab'] ?? 'vendors';

    $user_stmt =$pdo->prepare("SELECT first_name, last_name, email, user_type FROM users WHERE user_id = ?");
    $user_stmt->execute([$target_uid]);
    $target_user =$user_stmt->fetch(PDO::FETCH_ASSOC);

    if ($target_user) {
        $stmt =$pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
        $stmt->execute([$new_status,$target_uid]);

        $action_label =$new_status === 1 ? "activated" : "suspended";
        logAudit("Admin {$action_label} {$target_user['user_type']}: {$target_user['first_name']} {$target_user['last_name']} (User ID: #{$target_uid}, Email: {$target_user['email']})", $admin_id, 'Admin');$_SESSION['flash_msg'] = ['type' => 'success', 'text' => "{$target_user['user_type']} account {$action_label} successfully."];
    }

    header("Location: admin.php#{$tab}");
    exit;
}

// -------------------------------------------------------------
// 5. Handle Cancel / Disable Bookings
// -------------------------------------------------------------
if (isset($_GET['cancel_booking_id'])) {
    $booking_id = intval($_GET['cancel_booking_id']);

    $b_stmt =$pdo->prepare("SELECT r.*, h.name as hall_name FROM reservations r JOIN halls h ON r.hall_id = h.hall_id WHERE r.reservation_id = ?");
    $b_stmt->execute([$booking_id]);
    $booking =$b_stmt->fetch(PDO::FETCH_ASSOC);

    if ($booking &&$booking['status'] !== 'Cancelled') {
        $stmt =$pdo->prepare("UPDATE reservations SET status = 'Cancelled' WHERE reservation_id = ?");
        $stmt->execute([$booking_id]);

        logAudit("Admin cancelled Order #{$booking_id} for venue '{$booking['hall_name']}'", $admin_id, 'Admin');
        $_SESSION['flash_msg'] = ['type' => 'success', 'text' => "Order #{$booking_id} has been cancelled and disabled."];
    }

    header("Location: admin.php#all-bookings");
    exit;
}

// Fetch Platform Stats
try {
    $total_venues         =$pdo->query("SELECT COUNT(*) FROM halls")->fetchColumn();
    $approved_venues      =$pdo->query("SELECT COUNT(*) FROM halls WHERE is_active = 1")->fetchColumn();
    $pending_venues_count =$pdo->query("SELECT COUNT(*) FROM halls WHERE is_active = 0")->fetchColumn();
    $total_bookings       =$pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
    $platform_revenue     =$pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'Success'")->fetchColumn() ?: 0;
    $total_vendors        =$pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
    $total_customers      =$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
} catch (PDOException $e) {$total_venues = $approved_venues =$pending_venues_count = $total_bookings =$platform_revenue = $total_vendors =$total_customers = 0;
}

// Fetch Data Lists
try {
    $pending_stmt =$pdo->query("SELECT h.*, v.business_name, u.email FROM halls h JOIN vendors v ON h.vendor_id = v.user_id JOIN users u ON v.user_id = u.user_id WHERE h.is_active = 0 ORDER BY h.created_at DESC");
    $pending_list =$pending_stmt->fetchAll(PDO::FETCH_ASSOC);

    $approved_stmt =$pdo->query("SELECT h.*, v.business_name, u.email FROM halls h JOIN vendors v ON h.vendor_id = v.user_id JOIN users u ON v.user_id = u.user_id ORDER BY h.created_at DESC");
    $approved_list =$approved_stmt->fetchAll(PDO::FETCH_ASSOC);

    $bookings_stmt =$pdo->query("SELECT r.*, u.first_name, u.last_name, u.email, u.phone, h.name as hall_name FROM reservations r JOIN customers c ON r.customer_id = c.user_id JOIN users u ON c.user_id = u.user_id JOIN halls h ON r.hall_id = h.hall_id ORDER BY r.created_at DESC");
    $bookings_list =$bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

    $vendors_stmt =$pdo->query("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.created_at, u.is_active, 
               v.business_name, v.business_address, v.verification_status,
               (SELECT COUNT(*) FROM halls WHERE vendor_id = u.user_id) as venue_count
        FROM users u 
        JOIN vendors v ON u.user_id = v.user_id 
        ORDER BY u.created_at DESC
    ");
    $vendors_list =$vendors_stmt->fetchAll(PDO::FETCH_ASSOC);

    $customers_stmt =$pdo->query("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.created_at, u.is_active, 
               c.preference, c.booking_count, c.loyalty_points 
        FROM users u 
        JOIN customers c ON u.user_id = c.user_id 
        ORDER BY u.created_at DESC
    ");
    $customers_list =$customers_stmt->fetchAll(PDO::FETCH_ASSOC);

    $admins_stmt =$pdo->query("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.created_at, a.role 
        FROM users u 
        JOIN admins a ON u.user_id = a.user_id 
        WHERE u.user_type = 'Admin' 
        ORDER BY u.created_at ASC
    ");
    $admins_list =$admins_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $pending_list =$approved_list = $bookings_list =$vendors_list = $customers_list =$admins_list = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | VenueVista</title>
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="stylesheet" href="common.css">
  <script src="navigation.js" defer></script>
  <link rel="stylesheet" href="admin.css">
</head>

<body>

  <!-- Themed Toast Notification Container -->
  <div id="toast-container">
    <?php if ($flash_msg): ?>
      <div class="toast-box <?= htmlspecialchars($flash_msg['type']) ?>">
        <span><?= htmlspecialchars($flash_msg['text']) ?></span>
      </div>
    <?php endif; ?>
  </div>

  <!-- Themed Custom Confirmation Modal -->
  <div id="confirm-modal-overlay">
    <div id="confirm-modal-box">
      <h3 id="confirm-modal-title">Confirm Action</h3>
      <p id="confirm-modal-desc">Are you sure you want to proceed?</p>
      <div class="modal-actions">
        <button type="button" class="modal-btn modal-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
        <a href="#" id="confirm-modal-proceed" class="modal-btn modal-btn-confirm">Confirm</a>
      </div>
    </div>
  </div>

  <!-- DYNAMIC NAVIGATION BAR -->
  <section id="navigation-section">
    <div id="container">
      <div id="nav-bar">
        <a id="logo" href="index.php">VenueVista</a>
        <div id="nav-buttons">
            <button id="audit-logs-btn" type="button" onclick="window.location.href='admin_audit_logs.php';">
                Audit Logs
            </button>
            <?php include __DIR__ . '/navbar_user_menu.php'; ?>
        </div>
      </div>
    </div>
  </section>

  <main class="admin-content">
    <section class="admin-hero">
      <p class="admin-eyebrow">PLATFORM MANAGEMENT</p>
      <h1>Admin Dashboard</h1>
    </section>

    <!-- Navigation Tabs -->
    <nav class="admin-tabs" aria-label="Admin dashboard sections">
      <a class="tab-link active" href="#overview" data-target="panel-overview">Overview</a>
      <a class="tab-link" href="#pending-venues" data-target="panel-pending">Pending Venues</a>
      <a class="tab-link" href="#all-venues" data-target="panel-all">All Venues</a>
      <a class="tab-link" href="#vendors" data-target="panel-vendors">Vendors</a>
      <a class="tab-link" href="#customers" data-target="panel-customers">Customers</a>
      <a class="tab-link" href="#all-bookings" data-target="panel-bookings">Bookings</a>
      <a class="tab-link" href="#system-admins" data-target="panel-admins">System Admins</a>
    </nav>

    <!-- 1. Overview Panel -->
    <section class="admin-panel" id="panel-overview" style="display: block;">
      <div class="admin-stats">
        <article class="stat-card"><span class="stat-label">▥ &nbsp; TOTAL VENUES</span><strong><?= $total_venues ?></strong></article>
        <article class="stat-card"><span class="stat-label">⊙ &nbsp; PENDING REVIEW</span><strong><?= $pending_venues_count ?></strong></article>
        <article class="stat-card"><span class="stat-label">□ &nbsp; TOTAL BOOKINGS</span><strong><?= $total_bookings ?></strong></article>
        <article class="stat-card"><span class="stat-label">↗ &nbsp; REVENUE</span><strong>$<?= number_format($platform_revenue, 2) ?></strong></article>
        <article class="stat-card"><span class="stat-label">👤 &nbsp; VENDORS</span><strong><?= $total_vendors ?></strong></article>
        <article class="stat-card"><span class="stat-label">👥 &nbsp; CUSTOMERS</span><strong><?= $total_customers ?></strong></article>
      </div>
    </section>

    <!-- 2. Pending Venues Panel -->
    <section class="admin-panel" id="panel-pending" style="display: none;">
      <h2>Pending Approvals</h2>
      <?php if (!empty($pending_list)): ?>
          <?php foreach ($pending_list as$venue): ?>
            <article>
              <h3><?= htmlspecialchars($venue['name']) ?></h3>
              <p><strong>Type:</strong> <?= htmlspecialchars($venue['venue_type']) ?> | <strong>Location:</strong> <?= htmlspecialchars($venue['district']) ?></p>
              
              <details>
                  <summary><strong>View Full Details</strong></summary>
                  <ul>
                      <li><strong>Owner Email:</strong> <?= htmlspecialchars($venue['email']) ?></li>
                      <li><strong>Business Name:</strong> <?= htmlspecialchars($venue['business_name']) ?></li>
                      <li><strong>Full Address:</strong> <?= htmlspecialchars($venue['address']) ?></li>
                      <li><strong>Max Capacity:</strong> <?= htmlspecialchars($venue['capacity']) ?> guests</li>
                      <li><strong>Environment:</strong> <?= htmlspecialchars($venue['environment_type']) ?></li>
                      <li><strong>Base Price:</strong> $<?= htmlspecialchars($venue['base_price_per_hour']) ?> / day</li>
                      <li><strong>Weekend Price:</strong> $<?= htmlspecialchars($venue['weekend_price_per_hour']) ?> / day</li>
                      <li><strong>Security Deposit:</strong> $<?= htmlspecialchars($venue['security_deposit']) ?></li>
                      <li><strong>Description:</strong> <?= htmlspecialchars($venue['description']) ?></li>
                      <li><strong>Cancellation Policy:</strong> <?= htmlspecialchars($venue['cancellation_policy']) ?></li>
                  </ul>
              </details>
              
              <div>
                <a href="admin.php?action=approve&id=<?= $venue['hall_id'] ?>" class="filter-button">Approve</a>
                <button type="button" class="filter-button btn-danger" onclick="openConfirmModal('Reject Venue', 'Reject and permanently delete this listing?', 'admin.php?action=reject&id=<?= $venue['hall_id'] ?>')">Reject</button>
              </div>
            </article>
          <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <span aria-hidden="true">✓</span>
          <p>All caught up!</p>
          <h6>No pending venues to review.</h6>
        </div>
      <?php endif; ?>
    </section>

    <!-- 3. All Venues Panel -->
    <section class="admin-panel" id="panel-all" style="display: none;">
      <h2>All Venues</h2>
      <?php if (!empty($approved_list)): ?>
          <?php foreach ($approved_list as$venue): ?>
            <article>
              <h3><?= htmlspecialchars($venue['name']) ?></h3>
              <p>
                <strong>Type:</strong> <?= htmlspecialchars($venue['venue_type']) ?> | 
                <strong>Location:</strong> <?= htmlspecialchars($venue['district']) ?> | 
                <strong>Status:</strong> <span class="status-tag <?= $venue['is_active'] ? 'active' : 'disabled' ?>"><?= $venue['is_active'] ? 'Active' : 'Disabled' ?></span>
              </p>
              
              <details>
                  <summary><strong>View Full Details</strong></summary>
                  <ul>
                      <li><strong>Owner Email:</strong> <?= htmlspecialchars($venue['email']) ?></li>
                      <li><strong>Business Name:</strong> <?= htmlspecialchars($venue['business_name']) ?></li>
                      <li><strong>Full Address:</strong> <?= htmlspecialchars($venue['address']) ?></li>
                      <li><strong>Max Capacity:</strong> <?= htmlspecialchars($venue['capacity']) ?> guests</li>
                      <li><strong>Environment:</strong> <?= htmlspecialchars($venue['environment_type']) ?></li>
                      <li><strong>Base Price:</strong> $<?= htmlspecialchars($venue['base_price_per_hour']) ?> / day</li>
                      <li><strong>Weekend Price:</strong> $<?= htmlspecialchars($venue['weekend_price_per_hour']) ?> / day</li>
                      <li><strong>Security Deposit:</strong> $<?= htmlspecialchars($venue['security_deposit']) ?></li>
                      <li><strong>Description:</strong> <?= htmlspecialchars($venue['description']) ?></li>
                      <li><strong>Cancellation Policy:</strong> <?= htmlspecialchars($venue['cancellation_policy']) ?></li>
                  </ul>
              </details>
              <div>
                <?php if ($venue['is_active']): ?>
                  <button type="button" class="filter-button btn-danger" onclick="openConfirmModal('Disable Venue', 'Are you sure you want to disable this venue from public listings?', 'admin.php?toggle_venue=<?= $venue['hall_id'] ?>&status=0')">Disable Venue</button>
                <?php else: ?>
                  <a href="admin.php?toggle_venue=<?= $venue['hall_id'] ?>&status=1" class="filter-button">Enable Venue</a>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
      <?php else: ?>
        <p>No venues found.</p>
      <?php endif; ?>
    </section>

    <!-- 4. Vendors Panel -->
    <section class="admin-panel" id="panel-vendors" style="display: none;">
      <h2>Registered Vendors</h2>
      <?php if (!empty($vendors_list)): ?>
          <?php foreach ($vendors_list as$vendor): ?>
            <article>
              <h3><?= htmlspecialchars($vendor['first_name'] . ' ' .$vendor['last_name']) ?></h3>
              <p><strong>Business:</strong> <?= htmlspecialchars($vendor['business_name']) ?> | <strong>Total Venues:</strong> <?= htmlspecialchars($vendor['venue_count']) ?></p>
              
              <details>
                  <summary><strong>View Vendor Details</strong></summary>
                  <ul>
                      <li><strong>Email:</strong> <?= htmlspecialchars($vendor['email']) ?></li>
                      <li><strong>Phone:</strong> <?= htmlspecialchars($vendor['phone']) ?></li>
                      <li><strong>Business Address:</strong> <?= htmlspecialchars($vendor['business_address']) ?></li>
                      <li><strong>Verification Status:</strong> <?= htmlspecialchars($vendor['verification_status']) ?></li>
                      <li><strong>Account Status:</strong> <span class="status-tag <?= $vendor['is_active'] ? 'active' : 'suspended' ?>"><?= $vendor['is_active'] ? 'Active' : 'Suspended' ?></span></li>
                      <li><strong>Joined Platform:</strong> <?= date('F j, Y', strtotime($vendor['created_at'])) ?></li>
                  </ul>
              </details>
              <div>
                <?php if ($vendor['is_active']): ?>
                  <button type="button" class="filter-button btn-danger" onclick="openConfirmModal('Suspend Vendor', 'Suspend this vendor? They will not be able to log in or manage venues.', 'admin.php?toggle_user=<?= $vendor['user_id'] ?>&status=0&tab=vendors')">Suspend Vendor</button>
                <?php else: ?>
                  <a href="admin.php?toggle_user=<?= $vendor['user_id'] ?>&status=1&tab=vendors" class="filter-button">Activate Vendor</a>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
      <?php else: ?>
        <p>No vendors found.</p>
      <?php endif; ?>
    </section>

    <!-- 5. Customers Panel -->
    <section class="admin-panel" id="panel-customers" style="display: none;">
      <h2>Registered Customers</h2>
      <?php if (!empty($customers_list)): ?>
          <?php foreach ($customers_list as$customer): ?>
            <article>
              <h3><?= htmlspecialchars($customer['first_name'] . ' ' .$customer['last_name']) ?></h3>
              <p><strong>Total Bookings:</strong> <?= htmlspecialchars($customer['booking_count']) ?> | <strong>Loyalty Points:</strong> <?= htmlspecialchars($customer['loyalty_points']) ?></p>
              
              <details>
                  <summary><strong>View Customer Details</strong></summary>
                  <ul>
                      <li><strong>Email:</strong> <?= htmlspecialchars($customer['email']) ?></li>
                      <li><strong>Phone:</strong> <?= htmlspecialchars($customer['phone']) ?></li>
                      <li><strong>Preferences:</strong> <?= htmlspecialchars($customer['preference'] ?: 'None specified') ?></li>
                      <li><strong>Account Status:</strong> <span class="status-tag <?= $customer['is_active'] ? 'active' : 'suspended' ?>"><?= $customer['is_active'] ? 'Active' : 'Suspended' ?></span></li>
                      <li><strong>Joined Platform:</strong> <?= date('F j, Y', strtotime($customer['created_at'])) ?></li>
                  </ul>
              </details>
              <div>
                <?php if ($customer['is_active']): ?>
                  <button type="button" class="filter-button btn-danger" onclick="openConfirmModal('Suspend Customer', 'Suspend this customer account from making future bookings?', 'admin.php?toggle_user=<?= $customer['user_id'] ?>&status=0&tab=customers')">Suspend Customer</button>
                <?php else: ?>
                  <a href="admin.php?toggle_user=<?= $customer['user_id'] ?>&status=1&tab=customers" class="filter-button">Activate Customer</a>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
      <?php else: ?>
        <p>No customers found.</p>
      <?php endif; ?>
    </section>

    <!-- 6. All Bookings Panel -->
    <section class="admin-panel" id="panel-bookings" style="display: none;">
      <h2>All Bookings (Orders)</h2>
      <?php if (!empty($bookings_list)): ?>
          <?php foreach ($bookings_list as$booking): ?>
            <article>
              <h3>Order #<?= $booking['reservation_id'] ?> - <?= htmlspecialchars($booking['hall_name']) ?></h3>
              <p>
                <strong>Customer:</strong> <?= htmlspecialchars($booking['first_name'] . ' ' .$booking['last_name']) ?> | 
                <strong>Status:</strong> 
                <span class="status-tag <?= strtolower($booking['status']) ?>">
                  <?= htmlspecialchars($booking['status']) ?>
                </span>
              </p>
              
              <details>
                  <summary><strong>View Order Details</strong></summary>
                  <ul>
                      <li><strong>Customer Email:</strong> <?= htmlspecialchars($booking['email']) ?></li>
                      <li><strong>Customer Phone:</strong> <?= htmlspecialchars($booking['phone']) ?></li>
                      <li><strong>Event Start:</strong> <?= date('F j, Y, g:i a', strtotime($booking['start_datetime'])) ?></li>
                      <li><strong>Event End:</strong> <?= date('F j, Y, g:i a', strtotime($booking['end_datetime'])) ?></li>
                      <li><strong>Guest Count:</strong> <?= htmlspecialchars($booking['guest_count']) ?> guests</li>
                      <li><strong>Locked Price:</strong> $<?= number_format($booking['locked_price_per_hour'], 2) ?> / day</li>
                      <li><strong>Total Amount:</strong> $<?= number_format($booking['total_booking_amount'], 2) ?></li>
                      <li><strong>Special Requests:</strong> <?= htmlspecialchars($booking['special_requests'] ?: 'None') ?></li>
                      <li><strong>Booking Created On:</strong> <?= date('F j, Y', strtotime($booking['created_at'])) ?></li>
                  </ul>
              </details>
              <div>
                <?php if ($booking['status'] !== 'Cancelled'): ?>
                  <button type="button" class="filter-button btn-danger" onclick="openConfirmModal('Cancel Booking', 'Cancel and disable Order #<?= $booking['reservation_id'] ?>? This action is logged.', 'admin.php?cancel_booking_id=<?= $booking['reservation_id'] ?>')">Disable / Cancel Booking</button>
                <?php else: ?>
                  <span style="color: #76635b; font-size: 0.875rem; font-style: italic;">Booking disabled/cancelled</span>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
      <?php else: ?>
        <p>No bookings found on the platform yet.</p>
      <?php endif; ?>
    </section>

    <!-- 7. System Admins Panel -->
    <section class="admin-panel" id="panel-admins" style="display: none;">
      <h2>Manage System Admins</h2>
      
      <div style="margin-bottom: 40px;">
          <?php if (!empty($admins_list)): ?>
              <?php foreach ($admins_list as$admin): ?>
                <article>
                  <h3><?= htmlspecialchars($admin['first_name'] . ' ' .$admin['last_name']) ?></h3>
                  <p><strong>Email:</strong> <?= htmlspecialchars($admin['email']) ?> | <strong>Phone:</strong> <?= htmlspecialchars($admin['phone']) ?></p>
                  <p><strong>Role:</strong> <?= htmlspecialchars($admin['role']) ?> | <strong>Joined:</strong> <?= date('F j, Y', strtotime($admin['created_at'])) ?></p>
                </article>
              <?php endforeach; ?>
          <?php else: ?>
              <p>No other admins found.</p>
          <?php endif; ?>
      </div>

      <h2>Create New Admin Account</h2>
      <form class="admin-create-form" action="admin.php#system-admins" method="POST">
          <input type="hidden" name="create_admin" value="1">
          <input type="text" name="first_name" placeholder="First Name" required>
          <input type="text" name="last_name" placeholder="Last Name" required>
          <input type="email" name="email" placeholder="Email Address" required>
          <input type="text" name="phone" placeholder="Phone Number (e.g. +9477...)" required>
          <input type="password" name="password" placeholder="Secure Password" required minlength="8">
          <button type="submit" class="filter-button" style="width: 100%;">Create Admin</button>
      </form>
    </section>

  </main>

  <section id="footer-section">
    <div id="footer-body">
      <div id="footer-top">
        <div id="footer-details-block">
          <h1>VenueVista</h1>
          <p>Discover extraordinary spaces for life's most meaningful moments.</p>
        </div>
      </div>
      <div id="footer-bottom">
        <p>@ 2026 VenueVista. All rights reserved.</p>
      </div>
    </div>
  </section>   

  <!-- Modal & Tab Logic -->
  <script>
    const modalOverlay = document.getElementById('confirm-modal-overlay');
    const modalTitle   = document.getElementById('confirm-modal-title');
    const modalDesc    = document.getElementById('confirm-modal-desc');
    const modalProceed = document.getElementById('confirm-modal-proceed');

    function openConfirmModal(title, description, proceedUrl) {
      modalTitle.textContent = title;
      modalDesc.textContent = description;
      modalProceed.setAttribute('href', proceedUrl);
      modalOverlay.style.display = 'flex';
    }

    function closeConfirmModal() {
      modalOverlay.style.display = 'none';
    }

    // Auto-dismiss Toast after 4 seconds
    setTimeout(() => {
      const toast = document.querySelector('.toast-box');
      if (toast) {
        toast.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        setTimeout(() => toast.remove(), 500);
      }
    }, 4000);

    // Tab Switching Logic
    document.addEventListener('DOMContentLoaded', () => {
        const tabLinks = document.querySelectorAll('.tab-link');
        const panels = document.querySelectorAll('.admin-panel');

        function switchTab(targetId, activeTabElement) {
            panels.forEach(panel => panel.style.display = 'none');
            tabLinks.forEach(tab => tab.classList.remove('active'));

            const targetPanel = document.getElementById(targetId);
            if (targetPanel) targetPanel.style.display = 'block';
            if (activeTabElement) activeTabElement.classList.add('active');
        }

        tabLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = link.getAttribute('data-target');
                switchTab(targetId, link);
                history.replaceState(null, null, link.getAttribute('href'));
            });
        });

        const hash = window.location.hash;
        if (hash) {
            const activeTab = document.querySelector(`.tab-link[href="${hash}"]`);
            if (activeTab) {
                const targetId = activeTab.getAttribute('data-target');
                switchTab(targetId, activeTab);
            }
        }
    });
  </script>
</body>
</html>