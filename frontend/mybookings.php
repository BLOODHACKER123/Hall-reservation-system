<?php
session_start();

// 1. SAFELY LOAD DATABASE
$db_path = __DIR__ . '/../backend/config/database.php';
if (!file_exists($db_path)) {
    die("<h2 style='color:red;'>Database configuration file missing.</h2>");
}
require_once $db_path;

// 2. SESSION LOGIC (Lock down to Customers only)
if (!isset($_SESSION['user_id'])) {
    header("Location: loginchoice.php");
    exit;
}

if ($_SESSION['user_type'] !== 'Customer') {
    // If Admin or Vendor tries to access, send to homepage
    header("Location: index.php");
    exit;
}

$customer_id = $_SESSION['user_id'];
$customer_name = $_SESSION['user_name'] ?? 'Customer';

$success_msg = '';
$error_msg = '';

// 3. HANDLE CANCELLATION REQUEST
if (isset($_GET['action']) && $_GET['action'] == 'cancel' && isset($_GET['id'])) {
    $res_id = intval($_GET['id']);
    
    // Verify this booking belongs to this customer and is still pending
    $check_stmt = $pdo->prepare("SELECT status FROM reservations WHERE reservation_id = ? AND customer_id = ?");
    $check_stmt->execute([$res_id, $customer_id]);
    $booking = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($booking && strtolower($booking['status']) === 'pending') {
        $cancel_stmt = $pdo->prepare("UPDATE reservations SET status = 'Cancelled' WHERE reservation_id = ?");
        $cancel_stmt->execute([$res_id]);
        $success_msg = "Booking #$res_id has been successfully cancelled.";
    } else {
        $error_msg = "This booking cannot be cancelled because it is already processed.";
    }
}

// 4. FETCH CUSTOMER BOOKINGS
try {
    $stmt = $pdo->prepare("
        SELECT r.*, h.name as venue_name, h.district as location 
        FROM reservations r 
        JOIN halls h ON r.hall_id = h.hall_id 
        WHERE r.customer_id = ? 
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$customer_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
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
      .dashboard-container { max-width: 1000px; margin: 60px auto; padding: 0 20px; min-height: 50vh; }
      .dashboard-header { border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
      .dashboard-header h1 { margin: 0; color: #523530; font-family: 'Playfair Display', serif; }
      
      .booking-card { background: #fff; border: 1px solid #eaeaea; border-radius: 8px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 15px; }
      .booking-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #ccc; padding-bottom: 15px; }
      .booking-header h3 { margin: 0; font-size: 1.2rem; color: #333; }
      
      .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
      .status-pending { background: #fff3cd; color: #856404; }
      .status-confirmed { background: #d4edda; color: #155724; }
      .status-cancelled { background: #f8d7da; color: #721c24; }
      .status-completed { background: #e2e3e5; color: #383d41; }

      .booking-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 0.95rem; color: #555; }
      .booking-details p { margin: 5px 0; }
      .booking-details strong { color: #333; }

      .booking-actions { margin-top: 10px; display: flex; gap: 10px; }
      .btn { padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 0.9rem; font-weight: 500; cursor: pointer; border: 1px solid transparent; transition: 0.2s; }
      .btn-primary { background: #523530; color: #fff; }
      .btn-primary:hover { background: #3d2723; }
      .btn-danger { background: transparent; color: #c62828; border-color: #c62828; }
      .btn-danger:hover { background: #c62828; color: #fff; }

      .empty-state { text-align: center; padding: 60px 20px; background: #f9f9f9; border-radius: 8px; border: 1px dashed #ccc; }
      .empty-state h2 { color: #523530; margin-bottom: 10px; }
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
                  <a href="ownerdashboard.php">Owners Dashboard</a>
              <?php elseif ($_SESSION['user_type'] === 'Admin'): ?>
                  <a href="admin.php">Admin Dashboard</a>
              <?php elseif ($_SESSION['user_type'] === 'Customer'): ?>
                  <a href="mybookings.php" class="active">My Bookings</a>
              <?php endif; ?>
          <?php endif; ?>
        </nav>
        
        <div id="nav-buttons">
          <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Vendor'): ?>
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

  <main class="dashboard-container">
    <div class="dashboard-header">
        <div>
            <h1>My Bookings</h1>
            <p>Welcome back, <?= htmlspecialchars($customer_name) ?>.</p>
        </div>
        <a href="search.php" class="btn btn-primary">Book Another Venue</a>
    </div>

    <?php if ($success_msg): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
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
                    <a href="search.php?hall_id=<?= $booking['hall_id'] ?>" class="btn" style="border-color: #ccc; color: #333;">View Venue</a>
                    
                    <?php if ($status === 'pending'): ?>
                        <a href="mybookings.php?action=cancel&id=<?= $booking['reservation_id'] ?>" 
                           class="btn btn-danger" 
                           onclick="return confirm('Are you sure you want to cancel this reservation?');">
                           Cancel Booking
                        </a>
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
 
</body>
</html>