<?php
session_start();
require_once __DIR__ . '/../backend/config/database.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$is_logged_in = isset($_SESSION['user_id']);
$is_customer = $is_logged_in && (($_SESSION['user_type'] ?? '') === 'Customer');
$customer_id = $is_customer ? (int)$_SESSION['user_id'] : 0;

$review_success = '';
$review_error = '';

// Load Customer Wishlist Information
$user_wishlist = [];
$wishlist_count = 0;
if ($is_customer) {
    try {
        $w_stmt = $pdo->prepare("SELECT hall_id FROM wishlists WHERE customer_id = ?");
        $w_stmt->execute([$customer_id]);
        $user_wishlist = $w_stmt->fetchAll(PDO::FETCH_COLUMN);
        $wishlist_count = count($user_wishlist);
    } catch (Throwable $e) {}
}

// -------------------------------------------------------------
// 1. HANDLE REVIEW SUBMISSION
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$is_customer) {
        $review_error = "Only logged-in customers can leave reviews.";
    } else {
        $post_hall_id = intval($_POST['hall_id'] ?? 0);
        $post_reservation_id = intval($_POST['reservation_id'] ?? 0);
        $rating = intval($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $review_error = "Please provide a valid rating between 1 and 5 stars.";
        } else {
            try {
                $check_res = $pdo->prepare("
                    SELECT reservation_id 
                    FROM reservations 
                    WHERE reservation_id = ? 
                      AND customer_id = ? 
                      AND hall_id = ? 
                      AND end_datetime < NOW()
                      AND status != 'Cancelled'
                ");
                $check_res->execute([$post_reservation_id, $customer_id, $post_hall_id]);
                $valid_res = $check_res->fetch(PDO::FETCH_ASSOC);

                if (!$valid_res) {
                    $review_error = "You can only review venues for completed events that have already taken place.";
                } else {
                    $check_dup = $pdo->prepare("SELECT review_id FROM reviews WHERE reservation_id = ?");
                    $check_dup->execute([$post_reservation_id]);
                    if ($check_dup->fetch()) {
                        $review_error = "You have already submitted a review for this reservation.";
                    } else {
                        $ins_rev = $pdo->prepare("
                            INSERT INTO reviews (customer_id, reservation_id, rating, comment, created_at) 
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $ins_rev->execute([$customer_id, $post_reservation_id, $rating, $comment]);
                        $review_success = "Thank you! Your review has been published.";
                    }
                }
            } catch (Throwable $e) {
                $review_error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------
// 2. CHECK IF VIEWING A SINGLE VENUE VIA hall_id
// -------------------------------------------------------------
$viewing_details = isset($_GET['hall_id']) && !empty($_GET['hall_id']);
$venue_detail = null;
$packages = [];
$images = [];
$reviews = [];
$avg_rating = 0;
$total_reviews = 0;
$eligible_reservation = null;

if ($viewing_details) {
    $hall_id = intval($_GET['hall_id']);
    try {
        $stmt = $pdo->prepare("
            SELECT h.*, v.business_name, u.first_name, u.last_name 
            FROM halls h
            JOIN vendors v ON h.vendor_id = v.user_id
            JOIN users u ON v.user_id = u.user_id
            WHERE h.hall_id = ? AND h.is_active = 1
        ");
        $stmt->execute([$hall_id]);
        $venue_detail = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($venue_detail) {
            $pkg_stmt = $pdo->prepare("SELECT * FROM hall_packages WHERE hall_id = ? ORDER BY price ASC");
            $pkg_stmt->execute([$hall_id]);
            $packages = $pkg_stmt->fetchAll(PDO::FETCH_ASSOC);

            $img_stmt = $pdo->prepare("SELECT image_url FROM hall_images WHERE hall_id = ? ORDER BY is_primary DESC");
            $img_stmt->execute([$hall_id]);
            $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

            $rev_stmt = $pdo->prepare("
                SELECT r.*, u.first_name, u.last_name 
                FROM reviews r 
                JOIN reservations res ON r.reservation_id = res.reservation_id
                JOIN users u ON r.customer_id = u.user_id
                WHERE res.hall_id = ?
                ORDER BY r.created_at DESC
            ");
            $rev_stmt->execute([$hall_id]);
            $reviews = $rev_stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_reviews = count($reviews);
            if ($total_reviews > 0) {
                $sum_ratings = array_sum(array_column($reviews, 'rating'));
                $avg_rating = round($sum_ratings / $total_reviews, 1);
            }

            if ($is_customer) {
                $check_eligibility = $pdo->prepare("
                    SELECT res.reservation_id, res.end_datetime
                    FROM reservations res
                    LEFT JOIN reviews rev ON res.reservation_id = rev.reservation_id
                    WHERE res.customer_id = ?
                      AND res.hall_id = ?
                      AND res.end_datetime < NOW()
                      AND res.status != 'Cancelled'
                      AND rev.review_id IS NULL
                    ORDER BY res.end_datetime DESC
                    LIMIT 1
                ");
                $check_eligibility->execute([$customer_id, $hall_id]);
                $eligible_reservation = $check_eligibility->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (Throwable $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
}

// -------------------------------------------------------------
// 3. SEARCH & ASYNC CARDS HANDLER
// -------------------------------------------------------------
$search_location = trim($_GET['location'] ?? '');
$search_type = trim($_GET['venueType'] ?? '');
$search_date = trim($_GET['date'] ?? $_GET['Date'] ?? ''); 
$search_guests = trim($_GET['guests'] ?? '');
$search_sort = trim($_GET['sort'] ?? 'newest');
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

$venues = [];
if (!$viewing_details) {
    $query = "
        SELECT h.*, 
               MAX(hi.image_url) AS image_url,
               COALESCE(ROUND(AVG(rv.rating), 1), 0) AS avg_rating,
               COUNT(DISTINCT rv.review_id) AS review_count
        FROM halls h
        LEFT JOIN hall_images hi ON h.hall_id = hi.hall_id AND hi.is_primary = 1
        LEFT JOIN reservations res ON h.hall_id = res.hall_id
        LEFT JOIN reviews rv ON res.reservation_id = rv.reservation_id
        WHERE h.is_active = 1
    ";
    $params = [];

    if (!empty($search_location)) {
        $query .= " AND (h.district LIKE :loc1 OR h.address LIKE :loc2 OR h.name LIKE :loc3)";
        $locValue = '%' . $search_location . '%';
        $params[':loc1'] = $locValue;
        $params[':loc2'] = $locValue;
        $params[':loc3'] = $locValue;
    }

    if (!empty($search_type)) {
        $query .= " AND h.venue_type = :type";
        $params[':type'] = $search_type;
    }

    if (!empty($search_guests)) {
        if ($search_guests === '1-50') { $query .= " AND h.capacity <= 50"; }
        elseif ($search_guests === '51-150') { $query .= " AND h.capacity BETWEEN 51 AND 150"; }
        elseif ($search_guests === '151-300') { $query .= " AND h.capacity BETWEEN 151 AND 300"; }
        elseif ($search_guests === '301+') { $query .= " AND h.capacity >= 301"; }
    }

    $query .= " GROUP BY h.hall_id";

    if ($search_sort === 'price-low') {
        $query .= " ORDER BY h.base_price_per_hour ASC";
    } elseif ($search_sort === 'price-high') {
        $query .= " ORDER BY h.base_price_per_hour DESC";
    } else {
        $query .= " ORDER BY h.created_at DESC"; 
    }

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }

    // Output pure card markup for AJAX requests
    if ($is_ajax) {
        if (!empty($venues)) {
            foreach ($venues as $venue) {
                $imgSrc = !empty($venue['image_url']) ? $venue['image_url'] : 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=1920&auto=format&fit=crop&q=80';
                $in_wishlist = in_array((int)$venue['hall_id'], $user_wishlist);
                ?>
                <div class="venue-card"> 
                    <div class="venue-card-image" style="position: relative;">
                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($venue['name'] ?? 'Venue') ?>" />
                        
                        <button class="wishlist-btn <?= $in_wishlist ? 'active' : '' ?>" 
                                data-hall-id="<?= (int)$venue['hall_id'] ?>" 
                                title="<?= $in_wishlist ? 'Remove from wishlist' : 'Save to wishlist' ?>">
                            <?= $in_wishlist ? '♥' : '♡' ?>
                        </button>

                        <div class="hall-type-tag"><?= htmlspecialchars($venue['venue_type'] ?? 'Venue') ?></div>
                        <div class="featured-tag">Featured</div>
                        <div class="rating-tag">★<?= $venue['avg_rating'] > 0 ? $venue['avg_rating'] : 'New' ?></div>
                    </div>

                    <div class="location-details">
                        <p><?= htmlspecialchars($venue['name'] ?? 'Unnamed Venue') ?></p>
                        <p class="cost">$<?= number_format($venue['base_price_per_hour'] ?? 0, 2) ?>/day</p>
                        <br />
                        <p><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= htmlspecialchars($venue['district'] ?? 'Unknown Location') ?></p>
                    </div>
                    <div class="hall-details">
                        <p><i class="fa-solid fa-user-group" aria-hidden="true"></i> Up to <?= htmlspecialchars($venue['capacity'] ?? 'N/A') ?> guests</p>
                        <p><i class="fa-solid fa-comment-dots" aria-hidden="true"></i> <?= (int)$venue['review_count'] ?> <?= ((int)$venue['review_count'] === 1) ? 'review' : 'reviews' ?></p>
                        <a href="search.php?hall_id=<?= urlencode($venue['hall_id']) ?>">View Details → </a>
                    </div>
                </div>
                <?php
            }
        } else {
            echo '<p style="grid-column: 1/-1; padding: 40px; text-align: center; color: #523530;">No venues found matching your criteria. Try adjusting your search filters.</p>';
        }
        exit;
    }
}

// Categories for Dropdown
$categories = [];
try {
    $cat_stmt = $pdo->query("SELECT DISTINCT venue_type as name FROM halls WHERE venue_type IS NOT NULL AND venue_type != '' ORDER BY venue_type ASC");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $viewing_details && $venue_detail ? htmlspecialchars($venue_detail['name']) . ' | Details' : 'Browse Venues' ?> | VenueVista</title>
  <link rel="icon" type="image/x-icon" href="images/venuevista-logo.png" />
  <link rel="stylesheet" href="common.css">
  <script src="navigation.js" defer></script>
  <link rel="stylesheet" href="search.css">
  <style>
      .venue-header-img { width: 100%; height: 40vh; object-fit: cover; display: block; }
      .detail-wrapper { max-width: 1200px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 2fr 1fr; gap: 40px; }
      @media (max-width: 900px) { .detail-wrapper { grid-template-columns: 1fr; } }
      .detail-info h1 { margin-top: 0; font-family: 'Playfair Display', serif; color: #523530; font-size: 2.3rem; margin-bottom: 10px; }
      .meta-tags { display: flex; gap: 15px; color: #666; font-size: 0.95rem; margin-bottom: 25px; flex-wrap: wrap; }
      .section-block { margin-bottom: 30px; }
      .section-block h2 { font-size: 1.4rem; color: #333; border-bottom: 1px solid #eee; padding-bottom: 8px; margin-bottom: 12px; }
      .packages-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; }
      .package-card { border: 1px solid #eaeaea; border-radius: 8px; padding: 15px; background: #fafafa; }
      .booking-widget { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); position: sticky; top: 100px; }
      .price-header { font-size: 1.6rem; font-weight: bold; color: #333; margin-bottom: 20px; }
      .btn-book { width: 100%; background: #523530; color: #fff; border: none; padding: 12px; font-size: 1rem; font-weight: bold; border-radius: 6px; cursor: pointer; text-align: center; display: block; text-decoration: none; box-sizing: border-box; }
      .btn-book:hover { background: #3d2723; }
      .back-link { display: inline-block; margin-bottom: 20px; color: #523530; font-weight: bold; text-decoration: none; }

      /* Navbar Wishlist Link and Live Badge */
      .nav-wishlist-link { position: relative; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; }
      .wishlist-badge { background: #e53935; color: #fff; font-size: 0.75rem; font-weight: bold; border-radius: 10px; padding: 2px 6px; min-width: 16px; text-align: center; line-height: 1; }

      /* Wishlist Heart Icon Styling */
      .wishlist-btn {
          position: absolute;
          top: 12px;
          right: 12px;
          background: rgba(255, 255, 255, 0.9);
          border: none;
          width: 36px;
          height: 36px;
          border-radius: 50%;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 1.3rem;
          color: #888;
          transition: transform 0.15s ease, color 0.15s ease;
          z-index: 5;
      }
      .wishlist-btn:hover { background: #fff; transform: scale(1.1); }
      .wishlist-btn.active { color: #e53935; }

      /* Async Loading state for smooth transitions */
      #featured-venue-cards { transition: opacity 0.2s ease; }
      #featured-venue-cards.loading { opacity: 0.35; pointer-events: none; }

      /* Review Component Styles */
      .review-card { background: #fafafa; border: 1px solid #eee; border-radius: 8px; padding: 16px; margin-bottom: 15px; }
      .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
      .review-stars { color: #f59e0b; font-size: 1rem; }
      .review-date { font-size: 0.85rem; color: #888; }
      .review-author { font-weight: bold; color: #333; }
      .review-comment { color: #555; font-size: 0.95rem; line-height: 1.5; margin: 0; }
      .review-form-box { background: #fff; border: 1px solid #52353033; border-radius: 8px; padding: 20px; margin-top: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
      .review-form-box h3 { margin-top: 0; color: #523530; }
      .star-rating-select { display: flex; gap: 8px; margin-bottom: 12px; flex-direction: row-reverse; justify-content: flex-end; }
      .star-rating-select input { display: none; }
      .star-rating-select label { font-size: 1.6rem; color: #ccc; cursor: pointer; }
      .star-rating-select input:checked ~ label,
      .star-rating-select label:hover,
      .star-rating-select label:hover ~ label { color: #f59e0b; }
      .review-textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-family: inherit; margin-bottom: 12px; }
      .btn-review-submit { background: #523530; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
      .btn-review-submit:hover { background: #3d2723; }
  </style>
</head>
<body>
  
    <!-- NAVIGATION BAR -->
    <section id="navigation-section">
      <div id="container">
        <div id="nav-bar">
          <a id="logo" href="index.php">VenueVista</a>
          <nav id="nav-links">
            <a href="search.php" class="active">Browse Venues</a>
            <?php if (isset($_SESSION['user_type'])): ?>
                <?php if ($_SESSION['user_type'] === 'Vendor'): ?>
                    <a href="ownerdashboard.php">Owners Dashboard</a>
                <?php elseif ($_SESSION['user_type'] === 'Admin'): ?>
                    <a href="admin.php">Admin Dashboard</a>
                <?php elseif ($_SESSION['user_type'] === 'Customer'): ?>
                    <!-- Wishlist Navbar Link with Live Counter Badge -->
                    <a href="wishlist.php" class="nav-wishlist-link">
                        Wishlist 
                        <span class="wishlist-badge" id="nav-wishlist-count"><?= $wishlist_count ?></span>
                    </a>
                    <a href="mybookings.php">My Bookings</a>
                <?php endif; ?>
            <?php endif; ?>
          </nav>
          <div id="nav-buttons">
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'Vendor'): ?>
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

    <?php if ($viewing_details && $venue_detail): ?>
        <!-- ================= SINGLE VENUE DETAILS VIEW ================= -->
        <?php 
            $main_image = !empty($images) ? $images[0] : 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=1920&auto=format&fit=crop&q=80'; 
            $in_wishlist = in_array((int)$venue_detail['hall_id'], $user_wishlist);
        ?>
        <div style="position: relative;">
            <img src="<?= htmlspecialchars($main_image) ?>" alt="<?= htmlspecialchars($venue_detail['name']) ?>" class="venue-header-img">
            <button class="wishlist-btn <?= $in_wishlist ? 'active' : '' ?>" 
                    data-hall-id="<?= (int)$venue_detail['hall_id'] ?>" 
                    title="<?= $in_wishlist ? 'Remove from wishlist' : 'Save to wishlist' ?>"
                    style="top: 20px; right: 30px; width: 44px; height: 44px; font-size: 1.6rem;">
                <?= $in_wishlist ? '♥' : '♡' ?>
            </button>
        </div>

        <main class="detail-wrapper">
            <div class="detail-info">
                <a href="search.php" class="back-link">← Back to Search Results</a>

                <?php if (!empty($review_success)): ?>
                    <div style="background: #e6f4ea; color: #137333; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                        <?= htmlspecialchars($review_success) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($review_error)): ?>
                    <div style="background: #fce8e6; color: #c5221f; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                        <?= htmlspecialchars($review_error) ?>
                    </div>
                <?php endif; ?>

                <h1><?= htmlspecialchars($venue_detail['name']) ?></h1>
                <div class="meta-tags">
                    <span>📍 <?= htmlspecialchars($venue_detail['address'] . ', ' . $venue_detail['district']) ?></span>
                    <span>👥 Up to <?= htmlspecialchars($venue_detail['capacity']) ?> guests</span>
                    <span>🏢 <?= htmlspecialchars($venue_detail['venue_type']) ?></span>
                    <span>★ <?= $avg_rating > 0 ? $avg_rating : 'New' ?> (<?= $total_reviews ?> reviews)</span>
                </div>

                <div class="section-block">
                    <h2>About this space</h2>
                    <p><?= nl2br(htmlspecialchars($venue_detail['description'])) ?></p>
                </div>

                <?php if (!empty($packages)): ?>
                <div class="section-block">
                    <h2>Available Packages</h2>
                    <div class="packages-grid">
                        <?php foreach ($packages as $pkg): ?>
                        <div class="package-card">
                            <h3><?= htmlspecialchars($pkg['package_name']) ?></h3>
                            <div style="font-weight: bold; margin-bottom: 5px;">$<?= number_format($pkg['price'], 2) ?></div>
                            <p style="font-size: 0.85rem; color: #555;"><?= htmlspecialchars($pkg['description']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="section-block">
                    <h2>Policies</h2>
                    <p><strong>Security Deposit:</strong> $<?= number_format($venue_detail['security_deposit'], 2) ?></p>
                    <p><strong>Cancellation Policy:</strong> <?= htmlspecialchars($venue_detail['cancellation_policy'] ?: 'Standard policy applies.') ?></p>
                </div>

                <div class="section-block">
                    <h2>Guest Reviews (<?= $total_reviews ?>)</h2>
                    <?php if (empty($reviews)): ?>
                        <p style="color: #666;">No reviews yet. Be the first to share your experience after your event!</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $rev): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <span class="review-author"><?= htmlspecialchars($rev['first_name'] . ' ' . substr($rev['last_name'], 0, 1) . '.') ?></span>
                                    <span class="review-date"><?= date('M j, Y', strtotime($rev['created_at'])) ?></span>
                                </div>
                                <div class="review-stars">
                                    <?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?>
                                </div>
                                <?php if (!empty($rev['comment'])): ?>
                                    <p class="review-comment"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($eligible_reservation): ?>
                        <div class="review-form-box">
                            <h3>Share Your Event Experience</h3>
                            <p style="font-size: 0.9rem; color: #666; margin-top: -5px; margin-bottom: 15px;">
                                Your booking on <?= date('F j, Y', strtotime($eligible_reservation['end_datetime'])) ?> is complete.
                            </p>
                            <form action="search.php?hall_id=<?= $venue_detail['hall_id'] ?>" method="POST">
                                <input type="hidden" name="submit_review" value="1">
                                <input type="hidden" name="hall_id" value="<?= $venue_detail['hall_id'] ?>">
                                <input type="hidden" name="reservation_id" value="<?= $eligible_reservation['reservation_id'] ?>">

                                <label style="display:block; font-weight: bold; margin-bottom: 5px; font-size: 0.9rem;">Your Rating</label>
                                <div class="star-rating-select">
                                    <input type="radio" id="star5" name="rating" value="5" required><label for="star5">★</label>
                                    <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
                                    <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
                                    <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
                                    <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
                                </div>

                                <label for="review_comment" style="display:block; font-weight: bold; margin-bottom: 5px; font-size: 0.9rem;">Review Comment</label>
                                <textarea id="review_comment" name="comment" rows="4" class="review-textarea" placeholder="Describe the atmosphere, service, and layout..."></textarea>

                                <button type="submit" class="btn-review-submit">Post Review</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="booking-widget">
                    <div class="price-header">
                        $<?= number_format($venue_detail['base_price_per_hour'], 2) ?> <span style="font-size:0.9rem; font-weight:normal; color:#666;">/ day</span>
                    </div>

                    <?php if (!$is_logged_in): ?>
                        <a href="loginchoice.php" class="btn-book">Log in to Book</a>
                    <?php elseif (!$is_customer): ?>
                        <button type="button" class="btn-book" style="background: #ccc; cursor: not-allowed;" disabled>Only Customers can book</button>
                    <?php else: ?>
                        <a href="process_bookings.php?hall_id=<?= urlencode($venue_detail['hall_id']) ?>" class="btn-book">Proceed to Booking</a>
                    <?php endif; ?>
                </div>
            </div>
        </main>

    <?php else: ?>
        <!-- ================= STANDARD SEARCH & ZERO-REFRESH GRID VIEW ================= -->
        <section id="search-section">
          <div id="search-container">
            <form id="search-form" action="search.php" method="get">
              <label class="search-field search-location">
                <span class="field-icon" aria-hidden="true">⌕</span>
                <input type="text" id="location-input" name="location" placeholder="Location..." value="<?= htmlspecialchars($search_location) ?>" autocomplete="off" />
              </label>
              <label class="search-field">
                <select id="venue-type-select" name="venueType">
                   <option value="">All Types</option>
                   <?php foreach($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>" <?= ($search_type === $category) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
              </label>
              <label class="search-field search-date">
                <input type="date" id="date-input" name="date" value="<?= htmlspecialchars($search_date) ?>" />
              </label>
              <label class="search-field search-guests">
                <select id="guests-select" name="guests">
                  <option value="">Guests</option>
                  <option value="1-50" <?= ($search_guests === '1-50') ? 'selected' : '' ?>>1-50</option>
                  <option value="51-150" <?= ($search_guests === '51-150') ? 'selected' : '' ?>>51-150</option>
                  <option value="151-300" <?= ($search_guests === '151-300') ? 'selected' : '' ?>>151-300</option>
                  <option value="301+" <?= ($search_guests === '301+') ? 'selected' : '' ?>>301+</option>
                </select>
              </label>
              <label class="search-field search-sort">
                <select id="sort-select" name="sort">
                  <option value="newest" <?= ($search_sort === 'newest') ? 'selected' : '' ?>>Newest First</option>
                  <option value="price-low" <?= ($search_sort === 'price-low') ? 'selected' : '' ?>>Price: Low to High</option>
                  <option value="price-high" <?= ($search_sort === 'price-high') ? 'selected' : '' ?>>Price: High to Low</option>
                </select>
              </label>
              <button class="filter-button" type="submit">
                <span class="filter-icon" aria-hidden="true">☷</span> Search
              </button>
            </form>
          </div>
        </section>

       <section id="featured-venue">
          <div>
            <p id="search-subheading"><?= !empty($search_location) || !empty($search_type) || !empty($search_guests) ? 'Search Results' : 'Handpicked for You' ?></p>
            <h2 id="search-heading"><?= !empty($search_type) ? htmlspecialchars($search_type) . 's' : 'Available Venues' ?></h2>
            <a href="search.php" id="btn-clear-filters">View All / Clear Filters ✕</a>

            <div id="featured-venue-cards">
              <?php if (!empty($venues)): ?>
                <?php foreach ($venues as $venue): ?>
                  <?php 
                    $imgSrc = !empty($venue['image_url']) ? $venue['image_url'] : 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=1920&auto=format&fit=crop&q=80'; 
                    $in_wishlist = in_array((int)$venue['hall_id'], $user_wishlist);
                  ?>
                  <div class="venue-card"> 
                    <div class="venue-card-image" style="position: relative;">
                      <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($venue['name'] ?? 'Venue') ?>" />
                      
                      <!-- Wishlist Heart Button -->
                      <button class="wishlist-btn <?= $in_wishlist ? 'active' : '' ?>" 
                              data-hall-id="<?= (int)$venue['hall_id'] ?>" 
                              title="<?= $in_wishlist ? 'Remove from wishlist' : 'Save to wishlist' ?>">
                          <?= $in_wishlist ? '♥' : '♡' ?>
                      </button>

                      <div class="hall-type-tag"><?= htmlspecialchars($venue['venue_type'] ?? 'Venue') ?></div>
                      <div class="featured-tag">Featured</div>
                      <div class="rating-tag">★<?= $venue['avg_rating'] > 0 ? $venue['avg_rating'] : 'New' ?></div>
                    </div>

                    <div class="location-details">
                      <p><?= htmlspecialchars($venue['name'] ?? 'Unnamed Venue') ?></p>
                      <p class="cost">$<?= number_format($venue['base_price_per_hour'] ?? 0, 2) ?>/day</p>
                      <br />
                      <p><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= htmlspecialchars($venue['district'] ?? 'Unknown Location') ?></p>
                    </div>
                    <div class="hall-details">
                      <p><i class="fa-solid fa-user-group" aria-hidden="true"></i> Up to <?= htmlspecialchars($venue['capacity'] ?? 'N/A') ?> guests</p>
                      <p><i class="fa-solid fa-comment-dots" aria-hidden="true"></i> <?= (int)$venue['review_count'] ?> <?= ((int)$venue['review_count'] === 1) ? 'review' : 'reviews' ?></p>
                      <a href="search.php?hall_id=<?= urlencode($venue['hall_id']) ?>">View Details → </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p style="grid-column: 1/-1; padding: 40px; text-align: center; color: #523530;">No venues found matching your criteria. Try adjusting your search filters.</p>
              <?php endif; ?>
            </div>
          </div>
        </section>
    <?php endif; ?>

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

    <!-- ASYNCHRONOUS SEARCH & WISHLIST JAVASCRIPT ENGINE -->
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('search-form');
        const cardsContainer = document.getElementById('featured-venue-cards');
        const heading = document.getElementById('search-heading');
        const subHeading = document.getElementById('search-subheading');
        const clearBtn = document.getElementById('btn-clear-filters');
        const badgeCounter = document.getElementById('nav-wishlist-count');
        const isLoggedInCustomer = <?= json_encode($is_customer) ?>;

        // Fetch cards dynamically via fetch() without page refresh
        function fetchVenuesAsync() {
            if (!form || !cardsContainer) return;

            cardsContainer.classList.add('loading');

            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            params.set('ajax', '1');

            // Update browser URL bar cleanly without triggering reload
            const displayParams = new URLSearchParams(formData);
            const cleanUrl = window.location.pathname + '?' + displayParams.toString();
            history.replaceState(null, '', cleanUrl);

            fetch('search.php?' + params.toString())
                .then(res => res.text())
                .then(html => {
                    cardsContainer.innerHTML = html;
                    cardsContainer.classList.remove('loading');

                    const typeSelect = document.getElementById('venue-type-select');
                    if (typeSelect && heading) {
                        heading.textContent = typeSelect.value ? (typeSelect.value + 's') : 'Available Venues';
                    }
                    if (subHeading) {
                        const hasFilters = Array.from(formData.values()).some(val => val.trim() !== '');
                        subHeading.textContent = hasFilters ? 'Search Results' : 'Handpicked for You';
                    }

                    bindWishlistButtons(); // Bind event listeners to new cards
                })
                .catch(err => {
                    console.error('Async filter error:', err);
                    cardsContainer.classList.remove('loading');
                });
        }

        // 1. Instant response on all select dropdowns & date input
        const instantInputs = ['venue-type-select', 'date-input', 'guests-select', 'sort-select'];
        instantInputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', fetchVenuesAsync);
            }
        });

        // 2. Debounced typing on location input (300ms)
        const locInput = document.getElementById('location-input');
        if (locInput) {
            let debounceTimer;
            locInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(fetchVenuesAsync, 300);
            });
        }

        // 3. Form submit event override
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                fetchVenuesAsync();
            });
        }

        // 4. View All / Clear Filters click override
        if (clearBtn) {
            clearBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (form) form.reset();
                history.replaceState(null, '', window.location.pathname);
                fetchVenuesAsync();
            });
        }

        // 5. Asynchronous Wishlist Toggle Engine
        function bindWishlistButtons() {
            document.querySelectorAll('.wishlist-btn').forEach(btn => {
                btn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (!isLoggedInCustomer) {
                        window.location.href = 'loginchoice.php';
                        return;
                    }

                    const hallId = this.getAttribute('data-hall-id');
                    const button = this;
                    const fd = new FormData();
                    fd.append('hall_id', hallId);

                    fetch('../backend/ajax/toggle_wishlist.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            if (data.action === 'added') {
                                button.classList.add('active');
                                button.innerHTML = '♥';
                                button.setAttribute('title', 'Remove from wishlist');
                            } else {
                                button.classList.remove('active');
                                button.innerHTML = '♡';
                                button.setAttribute('title', 'Save to wishlist');
                            }

                            // Update the live navbar badge counter
                            if (badgeCounter && typeof data.total_count !== 'undefined') {
                                badgeCounter.textContent = data.total_count;
                            }
                        } else {
                            alert(data.message || 'Unable to update wishlist.');
                        }
                    })
                    .catch(err => console.error('Wishlist request failed:', err));
                };
            });
        }

        bindWishlistButtons(); // Initial binding
      });
    </script>
</body>
</html>