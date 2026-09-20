<?php
session_start();

$db_path = __DIR__ . '/../backend/config/database.php';
if (!file_exists($db_path)) {
    die("<h3 style='color:red;'>Database configuration file missing at: $db_path</h3>");
}
require_once $db_path;

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: loginchoice.php");
    exit;
}

// Kick out anyone who is not a Vendor
if ($_SESSION['user_type'] !== 'Vendor') {
    header("Location: index.php");
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$owner_data = null;
$venues = [];
$bookings = [];
$error_msg = '';
$success_msg = '';

try {
    // Authenticate Vendor
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, v.business_name, v.business_address, v.verification_status FROM users u JOIN vendors v ON u.user_id = v.user_id WHERE u.user_id = ? AND u.user_type = 'Vendor'");
        $stmt->execute([$_SESSION['user_id']]);
        $owner_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($owner_data) {
        $vendor_id = $owner_data['user_id'];

        // Handle Profile Update Submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $phone = trim($_POST['phone']);
            $business_name = trim($_POST['business_name']);
            $business_address = trim($_POST['business_address']);

            try {
                $pdo->beginTransaction();
                
                // Update base user details
                $update_user = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?");
                $update_user->execute([$first_name, $last_name, $phone, $vendor_id]);
                
                // Update vendor-specific details
                $update_vendor = $pdo->prepare("UPDATE vendors SET business_name = ?, business_address = ? WHERE user_id = ?");
                $update_vendor->execute([$business_name, $business_address, $vendor_id]);
                
                $pdo->commit();
                $success_msg = "Business profile updated successfully!";
                
                // Refresh $owner_data instantly
                $stmt = $pdo->prepare("SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, v.business_name, v.business_address, v.verification_status FROM users u JOIN vendors v ON u.user_id = v.user_id WHERE u.user_id = ?");
                $stmt->execute([$vendor_id]);
                $owner_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error_msg = "Failed to update profile: " . $e->getMessage();
            }
        }

        // Handle Take Offline Quick Action
        if (isset($_GET['action']) && isset($_GET['hall_id']) && $_GET['action'] === 'toggle_offline') {
            $hall_id = intval($_GET['hall_id']);
            $toggle_stmt = $pdo->prepare("UPDATE halls SET is_active = 0 WHERE hall_id = ? AND vendor_id = ?");
            $toggle_stmt->execute([$hall_id, $vendor_id]);
            $success_msg = "Venue taken offline successfully.";
            header("Location: ownerdashboard.php#venues");
            exit;
        }

        // Handle Request to Publish Online Action
        if (isset($_GET['action']) && isset($_GET['hall_id']) &&$_GET['action'] === 'request_online') {
            $hall_id = intval($_GET['hall_id']);
            // Keeps is_active = 0 until admin reviews and approves it
            $req_stmt =$pdo->prepare("UPDATE halls SET is_active = 0 WHERE hall_id = ? AND vendor_id = ?");
            $req_stmt->execute([$hall_id, $vendor_id]);$success_msg = "Approval request sent to Admin! Your venue will go live as soon as it is approved.";
            header("Location: ownerdashboard.php#venues");
            exit;
        }

        // Handle Booking Approvals / Rejections
        if (isset($_GET['action']) && isset($_GET['reservation_id'])) {
            $res_id = intval($_GET['reservation_id']);
            if ($_GET['action'] === 'approve_booking') {
                $stmt =$pdo->prepare("UPDATE reservations SET status = 'Confirmed' WHERE reservation_id = ? AND hall_id IN (SELECT hall_id FROM halls WHERE vendor_id = ?)");
                $stmt->execute([$res_id,$vendor_id]);
                $success_msg = "Booking #$res_id confirmed successfully.";
            } elseif ($_GET['action'] === 'reject_booking') {
                $stmt =$pdo->prepare("UPDATE reservations SET status = 'Cancelled' WHERE reservation_id = ? AND hall_id IN (SELECT hall_id FROM halls WHERE vendor_id = ?)");
                $stmt->execute([$res_id,$vendor_id]);
                $success_msg = "Booking #$res_id has been declined.";
            }
            header("Location: ownerdashboard.php#bookings");
            exit;
        }

        // Fetch Data
        $stmt_venues =$pdo->prepare("SELECT * FROM halls WHERE vendor_id = ? ORDER BY created_at DESC");
        $stmt_venues->execute([$vendor_id]);
        $venues =$stmt_venues->fetchAll(PDO::FETCH_ASSOC);

        $stmt_bookings =$pdo->prepare("SELECT r.*, h.name as hall_name, u.first_name as customer_fname, u.last_name as customer_lname, u.email as customer_email, u.phone as customer_phone FROM reservations r JOIN halls h ON r.hall_id = h.hall_id JOIN users u ON r.customer_id = u.user_id WHERE h.vendor_id = ? ORDER BY r.start_datetime DESC");
        $stmt_bookings->execute([$vendor_id]);
        $bookings =$stmt_bookings->fetchAll(PDO::FETCH_ASSOC);

        // Financials
        $total_earnings = 0;
        $pending_earnings = 0;
        $total_bookings = count($bookings);
        foreach ($bookings as$b) {
            if (strtolower($b['status']) === 'confirmed' || strtolower($b['status']) === 'completed') {
                $total_earnings +=$b['total_booking_amount'];
            } else {
                $pending_earnings +=$b['total_booking_amount'];
            }
        }
    } else {
        $error_msg = "No vendor accounts found.";
    }
} catch (Throwable $e) {$error_msg = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Owner Dashboard | VenueVista</title>
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="stylesheet" href="common.css">
  <script src="navigation.js" defer></script>
  <link rel="stylesheet" href="admin.css">
  <style>
      .owner-content { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
      .owner-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
      .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
      .status-active { background: #d4edda; color: #155724; }
      .status-pending { background: #fff3cd; color: #856404; }
      .status-offline { background: #f8d7da; color: #721c24; }
      .action-btn { padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #333; font-size: 13px; background: #fff; cursor: pointer; transition: 0.2s; display: inline-block; }
      .action-btn:hover { background: #f0f0f0; }
      .btn-primary { background: #523530; color: white; border-color: #523530; }
      .btn-primary:hover { background: #3d2723; color: white; }
      .btn-success { background: #2e7d32; color: white; border-color: #2e7d32; }
      .btn-success:hover { background: #1b5e20; color: white; }
      .form-group { margin-bottom: 15px; }
      .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
      .form-group input { width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; }
      .form-group input:disabled { background-color: #e9ecef; cursor: not-allowed; }

      /* In-Page Confirmation Modal */
      .custom-modal-overlay {
          display: none;
          position: fixed;
          top: 0; left: 0; right: 0; bottom: 0;
          background: rgba(0, 0, 0, 0.5);
          z-index: 9999;
          justify-content: center;
          align-items: center;
          animation: modalFadeIn 0.2s ease-out;
      }
      .custom-modal-box {
          background: #fff;
          padding: 25px 30px;
          border-radius: 8px;
          max-width: 450px;
          width: 90%;
          box-shadow: 0 10px 30px rgba(0,0,0,0.2);
          text-align: left;
      }
      .custom-modal-box h3 { margin-top: 0; color: #523530; }
      .custom-modal-box p { color: #555; font-size: 0.95rem; line-height: 1.5; margin-bottom: 25px; }
      .custom-modal-actions { display: flex; justify-content: flex-end; gap: 12px; }
      @keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }
  </style>
</head>
<body>
  
    <!-- DYNAMIC NAVIGATION BAR -->
    <section id="navigation-section">
      <div id="container">
        <div id="nav-bar">
          <a id="logo" href="index.php">VenueVista</a>
          <nav id="nav-links">
            <a href="search.php">Browse Venues</a>
            <a href="list.php">List a Venue</a>
            
            <?php if (isset($_SESSION['user_type'])): ?>
                <?php if ($_SESSION['user_type'] === 'Vendor'): ?>
                    <a href="ownerdashboard.php" class="active">Owners Dashboard</a>
                <?php elseif ($_SESSION['user_type'] === 'Admin'): ?>
                    <a href="admin.php">Admin Dashboard</a>
                <?php elseif ($_SESSION['user_type'] === 'Customer'): ?>
                    <a href="mybookings.php">My Bookings</a>
                <?php endif; ?>
            <?php endif; ?>
          </nav>
          
          <div id="nav-buttons">
            <?php if (!isset($_SESSION['user_type']) ||$_SESSION['user_type'] !== 'Admin'): ?>
                <a id="list-venue-button" href="list.php">List a Venue</a>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['user_id'])): ?>
                <a id="login-button" href="../backend/config/logout.php">Logout</a>
            <?php else: ?>
                <a id="login-button" href="loginchoice.php">Login</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <main class="owner-content">
        <?php if ($error_msg): ?><div style="background: #fce8e6; border: 1px solid #c5221f; color: #c5221f; padding: 15px; border-radius: 6px; margin-bottom: 20px;"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>
        <?php if ($success_msg): ?><div style="background: #d4edda; border: 1px solid #155724; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px;"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>

        <?php if ($owner_data): ?>
        <div class="owner-header">
            <div>
                <h1>Owner Dashboard</h1>
                <p>Welcome back, <strong><?= htmlspecialchars($owner_data['first_name'] . ' ' .$owner_data['last_name']) ?></strong></p>
            </div>
            <a href="list.php" class="action-btn btn-primary">+ Add New Venue</a>
        </div>

        <nav class="admin-tabs" aria-label="Owner dashboard sections">
            <a class="tab-link active" href="#overview" data-target="panel-overview">Overview</a>
            <a class="tab-link" href="#venues" data-target="panel-venues">My Venues</a>
            <a class="tab-link" href="#bookings" data-target="panel-bookings">Bookings</a>
            <a class="tab-link" href="#financials" data-target="panel-financials">Financials</a>
            <a class="tab-link" href="#settings" data-target="panel-settings">Settings</a>
        </nav>

        <section class="admin-panel" id="panel-overview" style="display: block;">
            <div class="admin-stats">
                <article class="stat-card"><span class="stat-label">▥ &nbsp; MY VENUES</span><strong><?= count($venues) ?></strong></article>
                <article class="stat-card"><span class="stat-label">□ &nbsp; TOTAL BOOKINGS</span><strong><?= $total_bookings ?></strong></article>
                <article class="stat-card"><span class="stat-label">↗ &nbsp; TOTAL EARNINGS</span><strong>$<?= number_format($total_earnings, 2) ?></strong></article>
                <article class="stat-card"><span class="stat-label">⊙ &nbsp; PENDING PAYOUTS</span><strong>$<?= number_format($pending_earnings, 2) ?></strong></article>
            </div>
        </section>

        <!-- VENUES PANEL -->
        <section class="admin-panel" id="panel-venues" style="display: none;">
            <h2>Manage Your Venues</h2>
            <p style="margin-bottom: 20px; font-size: 14px; color: #555;">Note: Any updates or requests to publish will require Admin approval before appearing publicly.</p>
            <?php if (empty($venues)): ?><p>You haven't listed any venues yet.</p><?php else: ?>
                <?php foreach ($venues as$venue): ?>
                    <article style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <h3 style="margin: 0 0 10px 0;"><?= htmlspecialchars($venue['name']) ?></h3>
                                <p style="margin: 0 0 10px 0; color: #555;">Type: <?= htmlspecialchars($venue['venue_type']) ?> | Capacity: <?= htmlspecialchars($venue['capacity']) ?> guests</p>
                                <?php if ($venue['is_active']): ?>
                                    <span class="status-badge status-active">Online (Visible to Public)</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">Offline / Pending Admin Review</span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <?php if ($venue['is_active']): ?>
                                    <button type="button" 
                                            class="action-btn trigger-confirm" 
                                            data-title="Take Venue Offline?" 
                                            data-desc="Are you sure you want to take '<?= htmlspecialchars($venue['name'], ENT_QUOTES) ?>' offline? It will be hidden from search results." 
                                            data-href="ownerdashboard.php?action=toggle_offline&hall_id=<?= $venue['hall_id'] ?>">
                                        Take Offline
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="action-btn" disabled style="background-color: #e9ecef; color: #6c757d; border-color: #ced4da; cursor: not-allowed;">
                                        Offline
                                    </button>
                                <?php endif; ?>

                                <a href="edit.php?hall_id=<?= $venue['hall_id'] ?>" class="action-btn btn-primary">Edit Details</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- BOOKINGS PANEL -->
        <section class="admin-panel" id="panel-bookings" style="display: none;">
            <h2>Reservation Requests</h2>
            <?php if (empty($bookings)): ?><p>No bookings found for your venues yet.</p><?php else: ?>
                <?php foreach ($bookings as$booking): ?>
                    <article style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between;">
                            <h3 style="margin: 0 0 10px 0;">Order #<?= $booking['reservation_id'] ?> - <?= htmlspecialchars($booking['hall_name']) ?></h3>
                            <span class="status-badge <?= strtolower($booking['status']) === 'confirmed' ? 'status-active' : 'status-pending' ?>"><?= htmlspecialchars(strtoupper($booking['status'])) ?></span>
                        </div>
                        
                        <details open style="margin-top: 15px;">
                            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">View Full Order Details</summary>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: #fff; padding: 15px; border: 1px solid #eee; border-radius: 6px;">
                                
                                <div>
                                    <p style="margin: 0 0 5px 0; font-size: 14px; color: #666;">CUSTOMER DETAILS</p>
                                    <p style="margin: 0 0 5px 0;"><strong>Name:</strong> <?= htmlspecialchars($booking['customer_fname'] . ' ' .$booking['customer_lname']) ?></p>
                                    <p style="margin: 0 0 5px 0;"><strong>Phone:</strong> <?= htmlspecialchars($booking['customer_phone']) ?></p>
                                    <p style="margin: 0 0 5px 0;"><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($booking['customer_email']) ?>"><?= htmlspecialchars($booking['customer_email']) ?></a></p>
                                </div>

                                <div>
                                    <p style="margin: 0 0 5px 0; font-size: 14px; color: #666;">EVENT DETAILS</p>
                                    <p style="margin: 0 0 5px 0;"><strong>Start:</strong> <?= date('F j, Y, g:i a', strtotime($booking['start_datetime'])) ?></p>
                                    <p style="margin: 0 0 5px 0;"><strong>End:</strong> <?= date('F j, Y, g:i a', strtotime($booking['end_datetime'])) ?></p>
                                    <p style="margin: 0 0 5px 0;"><strong>Guests:</strong> <?= htmlspecialchars($booking['guest_count']) ?> people</p>
                                </div>

                                <div>
                                    <p style="margin: 0 0 5px 0; font-size: 14px; color: #666;">FINANCIALS</p>
                                    <p style="margin: 0 0 5px 0;"><strong>Locked Rate:</strong> $<?= number_format($booking['locked_price_per_hour'], 2) ?> / day</p>
                                    <p style="margin: 0 0 5px 0;"><strong>Total Amount:</strong> $<?= number_format($booking['total_booking_amount'], 2) ?></p>
                                    <p style="margin: 0 0 5px 0;"><strong>Booked On:</strong> <?= date('F j, Y', strtotime($booking['created_at'])) ?></p>
                                </div>
                                
                                <div>
                                    <p style="margin: 0 0 5px 0; font-size: 14px; color: #666;">SPECIAL REQUESTS</p>
                                    <p style="margin: 0; color: #c5221f; font-weight: bold;"><?= htmlspecialchars($booking['special_requests'] ?: 'None specified.') ?></p>
                                </div>
                            </div>
                        </details>
                        
                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <?php if (strtolower($booking['status']) === 'pending'): ?>
                                <a href="ownerdashboard.php?action=approve_booking&reservation_id=<?= $booking['reservation_id'] ?>" class="action-btn btn-primary">Approve Booking</a>
                                
                                <button type="button" 
                                        class="action-btn trigger-confirm" 
                                        data-title="Decline Reservation?" 
                                        data-desc="Are you sure you want to decline Order #<?= $booking['reservation_id'] ?>? This booking will be marked as cancelled." 
                                        data-href="ownerdashboard.php?action=reject_booking&reservation_id=<?= $booking['reservation_id'] ?>">
                                    Decline
                                </button>
                            <?php endif; ?>
                            
                            <?php 
                                $subject = urlencode("Regarding your booking at " . $booking['hall_name']);
                                $body = urlencode("Hello " . $booking['customer_fname'] . ",\n\nI am reaching out regarding your upcoming reservation on " . date('F j, Y', strtotime($booking['start_datetime'])) . ".");
                            ?>
                            <a href="mailto:<?= htmlspecialchars($booking['customer_email']) ?>?subject=<?= $subject ?>&body=<?= $body ?>" class="action-btn" style="border-color: #007bff; color: #007bff;">Contact Customer</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- FINANCIALS PANEL -->
        <section class="admin-panel" id="panel-financials" style="display: none;">
            <h2>Earnings History</h2>
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid #ddd; background: #f9f9f9;">
                        <th style="padding: 12px;">Order ID</th>
                        <th style="padding: 12px;">Venue</th>
                        <th style="padding: 12px;">Date</th>
                        <th style="padding: 12px;">Status</th>
                        <th style="padding: 12px;">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as$booking): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;">#<?= $booking['reservation_id'] ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($booking['hall_name']) ?></td>
                            <td style="padding: 12px;"><?= date('M j, Y', strtotime($booking['created_at'])) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($booking['status']) ?></td>
                            <td style="padding: 12px; font-weight: bold;">$<?= number_format($booking['total_booking_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <!-- SETTINGS / PROFILE PANEL -->
        <section class="admin-panel" id="panel-settings" style="display: none;">
            <h2>Business Profile</h2>
            <form action="ownerdashboard.php#settings" method="POST" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
                <input type="hidden" name="update_profile" value="1">
                
                <div class="form-group">
                    <label>Email Address <span style="font-size: 12px; color: #666; font-weight: normal;">(Used for Login - Cannot be changed here)</span></label>
                    <input type="email" value="<?= htmlspecialchars($owner_data['email']) ?>" disabled>
                </div>
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($owner_data['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($owner_data['last_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($owner_data['phone']) ?>" required>
                </div>
                <hr style="margin: 25px 0; border: 0; border-top: 1px dashed #ccc;">
                <div class="form-group">
                    <label>Business Name</label>
                    <input type="text" name="business_name" value="<?= htmlspecialchars($owner_data['business_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Business Address</label>
                    <input type="text" name="business_address" value="<?= htmlspecialchars($owner_data['business_address'] ?? '') ?>">
                </div>
                <button type="submit" class="action-btn btn-primary" style="margin-top: 10px;">Save Changes</button>
            </form>
        </section>
        <?php endif; ?>
    </main>

    <!-- IN-PAGE CONFIRMATION MODAL -->
    <div class="custom-modal-overlay" id="confirmModal">
        <div class="custom-modal-box">
            <h3 id="modalTitle">Confirm Action</h3>
            <p id="modalDesc">Are you sure you want to perform this action?</p>
            <div class="custom-modal-actions">
                <button type="button" class="action-btn" id="modalCancelBtn">Cancel</button>
                <a href="#" class="action-btn btn-primary" id="modalConfirmBtn">Yes, Proceed</a>
            </div>
        </div>
    </div>

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

    <script>
      document.addEventListener('DOMContentLoaded', () => {
          // Tab Switching Logic
          const tabLinks = document.querySelectorAll('.tab-link');
          const panels = document.querySelectorAll('.admin-panel');
          function switchTab(targetId, activeTabElement) {
              panels.forEach(panel => { panel.style.display = 'none'; });
              tabLinks.forEach(tab => { tab.classList.remove('active'); });
              const targetPanel = document.getElementById(targetId);
              if (targetPanel) { targetPanel.style.display = 'block'; }
              if (activeTabElement) { activeTabElement.classList.add('active'); }
          }
          tabLinks.forEach(link => {
              link.addEventListener('click', (e) => {
                  e.preventDefault();
                  switchTab(link.getAttribute('data-target'), link);
                  history.replaceState(null, null, link.getAttribute('href'));
              });
          });
          if (window.location.hash) {
              const activeTab = document.querySelector(`.tab-link[href="${window.location.hash}"]`);
              if (activeTab) switchTab(activeTab.getAttribute('data-target'), activeTab);
          }

          // In-Page Custom Modal Handling
          const modal = document.getElementById('confirmModal');
          const modalTitle = document.getElementById('modalTitle');
          const modalDesc = document.getElementById('modalDesc');
          const modalConfirmBtn = document.getElementById('modalConfirmBtn');
          const modalCancelBtn = document.getElementById('modalCancelBtn');

          document.querySelectorAll('.trigger-confirm').forEach(btn => {
              btn.addEventListener('click', (e) => {
                  e.preventDefault();
                  modalTitle.textContent = btn.getAttribute('data-title') || 'Confirm Action';
                  modalDesc.textContent = btn.getAttribute('data-desc') || 'Are you sure you want to proceed?';
                  modalConfirmBtn.setAttribute('href', btn.getAttribute('data-href'));
                  modal.style.display = 'flex';
              });
          });

          modalCancelBtn.addEventListener('click', () => {
              modal.style.display = 'none';
          });

          window.addEventListener('click', (e) => {
              if (e.target === modal) {
                  modal.style.display = 'none';
              }
          });
      });
    </script>
</body>
</html>