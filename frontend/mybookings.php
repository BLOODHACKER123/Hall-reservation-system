<?php
session_start();

// 1. SAFELY LOAD DATABASE & AUDIT LOGGER
$db_path = __DIR__ . '/../backend/config/database.php';
if (!file_exists($db_path)) {
    die("<h2 style='color:red;'>Database configuration file missing.</h2>");
}
require_once $db_path;
require_once __DIR__ . '/../backend/utils/auditLogger.php';

// 2. SESSION LOGIC (Lock down to Customers only)
if (!isset($_SESSION['user_id'])) {
    header("Location: loginchoice.php");
    exit;
}

if ($_SESSION['user_type'] !== 'Customer') {
    header("Location: index.php");
    exit;
}

$customer_id = (int)$_SESSION['user_id'];
$customer_name = $_SESSION['user_name'] ?? 'Customer';

$success_msg = '';
$error_msg = '';

// 3. HANDLE CANCELLATION REQUEST
if (isset($_GET['action']) && $_GET['action'] == 'cancel' && isset($_GET['id'])) {
    $res_id = intval($_GET['id']);
    
    $check_stmt = $pdo->prepare("
        SELECT r.status, h.name AS venue_name, r.hall_id 
        FROM reservations r 
        JOIN halls h ON r.hall_id = h.hall_id 
        WHERE r.reservation_id = ? AND r.customer_id = ?
    ");
    $check_stmt->execute([$res_id, $customer_id]);
    $booking = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($booking && strtolower($booking['status']) === 'pending') {
        $cancel_stmt = $pdo->prepare("UPDATE reservations SET status = 'Cancelled' WHERE reservation_id = ?");
        $cancel_stmt->execute([$res_id]);

        // AUDIT LOG: Customer cancelled pending booking
        logAudit(
            "Customer {$customer_name} (#{$customer_id}) cancelled Order #{$res_id} for venue '{$booking['venue_name']}' (Hall ID: #{$booking['hall_id']})",
            $customer_id,
            'Customer'
        );

        $success_msg = "Booking #$res_id has been successfully cancelled.";
    } else {
        $current_status = $booking['status'] ?? 'Unknown/Not found';
        // AUDIT LOG: Failed or invalid cancellation attempt
        logAudit(
            "Customer {$customer_name} (#{$customer_id}) failed to cancel Order #{$res_id}. Current status: {$current_status}",
            $customer_id,
            'Customer'
        );

        $error_msg = "This booking cannot be cancelled because it is already processed.";
    }
}

// 4. FETCH CUSTOMER BOOKINGS (Includes checking for existing reviews)
try {
    $stmt = $pdo->prepare("
        SELECT r.*, h.name as venue_name, h.district as location, rev.review_id 
        FROM reservations r 
        JOIN halls h ON r.hall_id = h.hall_id 
        LEFT JOIN reviews rev ON r.reservation_id = rev.reservation_id
        WHERE r.customer_id = ? 
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$customer_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    logAudit(
        "Database error loading bookings for Customer #{$customer_id}: " . $e->getMessage(),
        $customer_id,
        'Customer'
    );
    $error_msg = "Failed to load bookings: " . $e->getMessage();
    $bookings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Bookings | VenueVista</title>
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="common.css">
  <script src="navigation.js" defer></script>
  
  <style>
      :root {
        --booking-ink: #172e44;
        --booking-muted: #92766e;
        --booking-rose: #986e68;
        --booking-bg: #faf8f5;
        --booking-field: #f2f0ed;
        --booking-line: #eee7e2;
        --booking-danger: #8a3f38;
        --booking-danger-hover: #6e302a;
      }

      body {
        background-color: var(--booking-bg);
      }

      .dashboard-container { 
        max-width: 1000px; 
        margin: 50px auto 70px; 
        padding: 0 20px; 
        min-height: 55vh; 
      }

      .dashboard-header { 
        border-bottom: 1px solid var(--booking-line); 
        padding-bottom: 22px; 
        margin-bottom: 35px; 
        display: flex; 
        flex-wrap: wrap; 
        gap: 16px; 
        justify-content: space-between; 
        align-items: center; 
      }

      .dashboard-header h1 { 
        margin: 0 0 6px; 
        color: var(--booking-ink); 
        font: 400 2.2rem/1.1 Georgia, 'Playfair Display', serif; 
      }

      .dashboard-header p {
        margin: 0;
        color: var(--booking-muted);
        font: 400 15px/1.4 Arial, sans-serif;
      }
      
      .booking-card { 
        background: #ffffff; 
        border: 1px solid var(--booking-line); 
        border-radius: 20px; 
        padding: 30px; 
        margin-bottom: 24px; 
        box-shadow: 0 10px 30px rgba(45, 41, 38, .04); 
        display: flex; 
        flex-direction: column; 
        gap: 18px; 
      }

      .booking-header { 
        display: flex; 
        flex-wrap: wrap; 
        gap: 12px; 
        justify-content: space-between; 
        align-items: center; 
        border-bottom: 1px dashed var(--booking-line); 
        padding-bottom: 16px; 
      }

      .booking-header h3 { 
        margin: 0; 
        font: 500 1.35rem/1.2 Georgia, serif; 
        color: var(--booking-ink); 
      }
      
      .status-badge { 
        padding: 6px 14px; 
        border-radius: 999px; 
        font-size: 0.78rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        letter-spacing: 0.6px; 
        font-family: Arial, sans-serif;
      }
      .status-pending { 
        background: #fff4e5; 
        color: #b45309; 
      }
      .status-confirmed { 
        background: #ebf5ee; 
        color: #2b6e41; 
      }
      .status-cancelled { 
        background: #fdf2f1; 
        color: var(--booking-danger); 
      }
      .status-completed { 
        background: var(--booking-field); 
        color: #604d49; 
      }

      .booking-details { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); 
        gap: 16px; 
        font-size: 0.92rem; 
        color: #685752; 
        line-height: 1.5;
      }
      .booking-details p { 
        margin: 4px 0; 
      }

      .booking-details strong { 
        color: var(--booking-ink); 
      }

      .booking-actions { 
        margin-top: 10px; 
        display: flex; 
        flex-wrap: wrap; 
        gap: 12px; 
        align-items: center; 
      }

      .btn { 
        padding: 9px 20px; 
        border-radius: 999px; 
        text-decoration: none; 
        font: 600 0.875rem Arial, sans-serif; 
        cursor: pointer; 
        border: 1px solid transparent; 
        transition: all 0.2s ease; 
        display: inline-flex; 
        align-items: center;
        justify-content: center;
        font-family: inherit;
      }

      .btn-primary { 
        background: var(--booking-rose); 
        color: #ffffff; 
      }
      .btn-primary:hover { 
        background: #865b56; 
      }
      .btn-review { 
        background: #d97706; 
        color: #ffffff; 
      }
      .btn-review:hover { 
        background: #b45309; 
      }
      .reviewed-tag { 
        font-size: 0.85rem; 
        color: #2b6e41; 
        font-weight: 700; 
        padding: 7px 14px; 
        background: #ebf5ee; 
        border-radius: 999px; 
      }
      .btn-outline {
        border-color: var(--booking-line);
        background: #ffffff;
        color: #634d49;
      }
      .btn-outline:hover {
        background: var(--booking-field);
        border-color: #d8cec7;
      }
      .btn-danger { 
        background: transparent; 
        color: var(--booking-danger); 
        border-color: #e6c8c4; 
      }
      .btn-danger:hover { 
        background: var(--booking-danger); 
        border-color: var(--booking-danger);
        color: #ffffff; 
      }

      .empty-state { 
        text-align: center; 
        padding: 60px 20px; 
        background: #ffffff; 
        border-radius: 24px; 
        border: 1px dashed var(--booking-line); 
      }
      .empty-state h2 { 
        color: var(--booking-ink); 
        font: 400 1.8rem Georgia, serif;
        margin-bottom: 10px; 
      }
      .empty-state p {
        color: var(--booking-muted);
        font: 400 15px Arial, sans-serif;
      }

      .alert-box {
        padding: 16px 20px;
        border-radius: 16px;
        margin-bottom: 24px;
        font: 500 0.9rem Arial, sans-serif;
      }
      .alert-success {
        background: #ebf5ee;
        color: #2b6e41;
        border: 1px solid #cce8d4;
      }
      .alert-error {
        background: #fdf2f1;
        color: var(--booking-danger);
        border: 1px solid #f6cfcb;
      }

      /* ========================================================
         THEMED CONFIRMATION MODAL POPUP
         ======================================================== */
      #confirm-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(45, 41, 38, 0.55);
        backdrop-filter: blur(3px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 20px;
      }

      #confirm-modal-box {
        background: #ffffff;
        padding: 36px 30px;
        border-radius: 24px;
        border: 1px solid var(--booking-line);
        max-width: 440px;
        width: 100%;
        box-shadow: 0 16px 38px rgba(45, 41, 38, 0.12);
        text-align: center;
      }

      #confirm-modal-title {
        margin: 0 0 10px;
        color: var(--booking-ink);
        font: 400 1.6rem/1.2 Georgia, serif;
      }

      #confirm-modal-desc {
        margin: 0 0 26px;
        color: var(--booking-muted);
        font: 400 0.92rem/1.5 Arial, sans-serif;
      }

      .modal-actions {
        display: flex;
        justify-content: center;
        gap: 12px;
      }

      .modal-btn {
        min-height: 42px;
        padding: 0 24px;
        border-radius: 999px;
        font: 600 0.875rem Arial, sans-serif;
        cursor: pointer;
        border: 1px solid transparent;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
      }

      .modal-btn-cancel {
        background: var(--booking-field);
        border-color: var(--booking-line);
        color: #604d49;
      }

      .modal-btn-cancel:hover {
        background: #e4dfda;
      }

      .modal-btn-confirm {
        background: var(--booking-danger);
        color: #ffffff;
      }

      .modal-btn-confirm:hover {
        background: var(--booking-danger-hover);
      }

      @media (max-width: 600px) {
        .dashboard-container { 
          margin: 28px auto 50px; 
          padding: 0 16px; 
        }
        .booking-card { 
          padding: 22px; 
          border-radius: 18px;
        }
      }
  </style>
</head>
<body>

  <!-- Themed Custom Confirmation Modal -->
  <div id="confirm-modal-overlay">
    <div id="confirm-modal-box">
      <h3 id="confirm-modal-title">Cancel Reservation</h3>
      <p id="confirm-modal-desc">Are you sure you want to cancel this booking? This action cannot be reversed.</p>
      <div class="modal-actions">
        <button type="button" class="modal-btn modal-btn-cancel" onclick="closeConfirmModal()">Keep Booking</button>
        <a href="#" id="confirm-modal-proceed" class="modal-btn modal-btn-confirm">Yes, Cancel</a>
      </div>
    </div>
  </div>

  <!-- DYNAMIC NAVIGATION BAR -->
  <section id="navigation-section">
    <div id="container">
      <div id="nav-bar">
        <a id="logo" href="index.php">VenueVista</a>
        <div id="nav-buttons">
            <?php include __DIR__ . '/navbar_user_menu.php'; ?>
        </div>
      </div>
    </div>
  </section>

  <main class="dashboard-container">
    <div class="dashboard-header">
        <div>
            <h1>My Bookings</h1>
            <p>Welcome back, <?= htmlspecialchars($customer_name) ?>.</p>
        </div>
        <a href="search.php" class="btn btn-primary">Book Another Venue</a>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert-box alert-success">
            <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert-box alert-error">
            <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($bookings)): ?>
        <div class="empty-state">
            <h2>No Bookings Found</h2>
            <p>You haven't made any reservations yet. Ready to plan your next event?</p>
            <br>
            <a href="search.php" class="btn btn-primary">Explore Venues</a>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $booking): ?>
            <?php 
                $status_class = 'status-pending';
                $status = strtolower($booking['status']);
                if ($status === 'confirmed') $status_class = 'status-confirmed';
                if ($status === 'cancelled') $status_class = 'status-cancelled';
                if ($status === 'completed') $status_class = 'status-completed';

                // Check if the event datetime has already passed
                $is_event_passed = strtotime($booking['end_datetime']) < time();
                $has_reviewed = !empty($booking['review_id']);
            ?>
            <div class="booking-card">
                <div class="booking-header">
                    <h3><?= htmlspecialchars($booking['venue_name']) ?></h3>
                    <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($booking['status']) ?></span>
                </div>
                
                <div class="booking-details">
                    <div>
                        <p><strong>Order ID:</strong> #<?= htmlspecialchars($booking['reservation_id']) ?></p>
                        <p><strong>Location:</strong> <?= htmlspecialchars($booking['location']) ?></p>
                        <p><strong>Booked On:</strong> <?= date('M j, Y', strtotime($booking['created_at'])) ?></p>
                    </div>
                    <div>
                        <p><strong>Start Date:</strong> <?= date('F j, Y, g:i a', strtotime($booking['start_datetime'])) ?></p>
                        <p><strong>End Date:</strong> <?= date('F j, Y, g:i a', strtotime($booking['end_datetime'])) ?></p>
                        <p><strong>Guests:</strong> <?= htmlspecialchars($booking['guest_count']) ?> people</p>
                    </div>
                    <div>
                        <p><strong>Rate:</strong> $<?= number_format($booking['locked_price_per_hour'], 2) ?>/day</p>
                        <p><strong>Total Amount:</strong> $<?= number_format($booking['total_booking_amount'], 2) ?></p>
                    </div>
                </div>

                <div class="booking-actions">
                    <a href="search.php?hall_id=<?= $booking['hall_id'] ?>" class="btn btn-outline">View Venue</a>
                    
                    <!-- REVIEW BUTTON LOGIC -->
                    <?php if ($status !== 'cancelled' && $is_event_passed): ?>
                        <?php if (!$has_reviewed): ?>
                            <a href="search.php?hall_id=<?= $booking['hall_id'] ?>" class="btn btn-review">★ Write a Review</a>
                        <?php else: ?>
                            <span class="reviewed-tag">✓ Reviewed</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- THEMED MODAL CANCELLATION TRIGGER -->
                    <?php if ($status === 'pending'): ?>
                        <button type="button" 
                                class="btn btn-danger" 
                                onclick="openConfirmModal('Cancel Reservation', 'Are you sure you want to cancel booking #<?= $booking['reservation_id'] ?> for <?= htmlspecialchars(addslashes($booking['venue_name'])) ?>?', 'mybookings.php?action=cancel&id=<?= $booking['reservation_id'] ?>')">
                           Cancel Booking
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

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

  <!-- Custom Popup Modal Script -->
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

    // Dismiss modal if user clicks outside of the dialog box
    modalOverlay.addEventListener('click', function(e) {
      if (e.target === modalOverlay) {
        closeConfirmModal();
      }
    });
  </script>
</body>
</html>