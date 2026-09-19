<?php
session_start();
require_once __DIR__ . '/../backend/config/database.php';

// Force errors to display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if a specific venue is requested via hall_id
$viewing_details = isset($_GET['hall_id']) && !empty($_GET['hall_id']);
$venue_detail = null;
$packages = [];
$images = [];

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
        }
    } catch (Throwable $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
}

// Load categories for search dropdown
$categories = [];
try {
    $cat_stmt = $pdo->query("SELECT DISTINCT venue_type as name FROM halls WHERE venue_type IS NOT NULL AND venue_type != '' ORDER BY venue_type ASC");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

$search_location = $_GET['location'] ?? '';
$search_type = $_GET['venueType'] ?? '';
$search_date = $_GET['date'] ?? $_GET['Date'] ?? ''; 
$search_guests = $_GET['guests'] ?? '';
$search_sort = $_GET['sort'] ?? 'newest';

$venues = [];
if (!$viewing_details) {
    $query = "
        SELECT h.*, hi.image_url 
        FROM halls h
        LEFT JOIN hall_images hi ON h.hall_id = hi.hall_id AND hi.is_primary = 1
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
        if ($search_guests == '1-50') { $query .= " AND h.capacity <= 50"; }
        elseif ($search_guests == '51-150') { $query .= " AND h.capacity BETWEEN 51 AND 150"; }
        elseif ($search_guests == '151-300') { $query .= " AND h.capacity BETWEEN 151 AND 300"; }
        elseif ($search_guests == '301+') { $query .= " AND h.capacity >= 301"; }
    }

    if ($search_sort == 'price-low') {
        $query .= " ORDER BY h.base_price_per_hour ASC";
    } elseif ($search_sort == 'price-high') {
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
}

$is_logged_in = isset($_SESSION['user_id']);
$is_customer = $is_logged_in && ($_SESSION['user_type'] === 'Customer');
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
      .form-group { margin-bottom: 15px; }
      .form-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 0.9rem; }
      .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
      .btn-book { width: 100%; background: #523530; color: #fff; border: none; padding: 12px; font-size: 1rem; font-weight: bold; border-radius: 6px; cursor: pointer; text-align: center; display: block; text-decoration: none; box-sizing: border-box; }
      .btn-book:hover { background: #3d2723; }
      .back-link { display: inline-block; margin-bottom: 20px; color: #523530; font-weight: bold; text-decoration: none; }
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
        <?php $main_image = !empty($images) ? $images[0] : 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=1920&auto=format&fit=crop&q=80'; ?>
        <img src="<?= htmlspecialchars($main_image) ?>" alt="<?= htmlspecialchars($venue_detail['name']) ?>" class="venue-header-img">

        <main class="detail-wrapper">
            <div class="detail-info">
                <a href="search.php" class="back-link">← Back to Search Results</a>

                <h1><?= htmlspecialchars($venue_detail['name']) ?></h1>
                <div class="meta-tags">
                    <span>📍 <?= htmlspecialchars($venue_detail['address'] . ', ' . $venue_detail['district']) ?></span>
                    <span>👥 Up to <?= htmlspecialchars($venue_detail['capacity']) ?> guests</span>
                    <span>🏢 <?= htmlspecialchars($venue_detail['venue_type']) ?></span>
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
        <!-- ================= STANDARD SEARCH & GRID VIEW ================= -->
        <section id="search-section">
          <div id="search-container">
            <form id="search-form" action="search.php" method="get">
              <label class="search-field search-location">
                <span class="field-icon" aria-hidden="true">⌕</span>
                <input type="text" name="location" placeholder="Location..." aria-label="Location" value="<?= htmlspecialchars($search_location) ?>" />
              </label>
              <label class="search-field">
                <select name="venueType" aria-label="Venue type">
                   <option value="">All Types</option>
                   <?php foreach($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>" <?= ($search_type === $category) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
              </label>
              <label class="search-field search-date">
                <input type="date" name="date" aria-label="Date" value="<?= htmlspecialchars($search_date) ?>" />
              </label>
              <label class="search-field search-guests">
                <select name="guests" aria-label="Guests">
                  <option value="">Guests</option>
                  <option value="1-50" <?= ($search_guests === '1-50') ? 'selected' : '' ?>>1-50</option>
                  <option value="51-150" <?= ($search_guests === '51-150') ? 'selected' : '' ?>>51-150</option>
                  <option value="151-300" <?= ($search_guests === '151-300') ? 'selected' : '' ?>>151-300</option>
                  <option value="301+" <?= ($search_guests === '301+') ? 'selected' : '' ?>>301+</option>
                </select>
              </label>
              <label class="search-field search-sort">
                <select name="sort" aria-label="Sort venues">
                  <option value="newest" <?= ($search_sort === 'newest') ? 'selected' : '' ?>>Newest First</option>
                  <option value="price-low" <?= ($search_sort === 'price-low') ? 'selected' : '' ?>>Price: Low to High</option>
                  <option value="price-high" <?= ($search_sort === 'price-high') ? 'selected' : '' ?>>Price: High to Low</option>
                </select>
              </label>
              <button class="filter-button" type="submit">
                <span class="filter-icon" aria-hidden="true">☷</span> Filters
              </button>
            </form>
          </div>
        </section>

       <section id="featured-venue">
          <div>
            <p><?= !empty($search_location) || !empty($search_type) ? 'Search Results' : 'Handpicked for You' ?></p>
            <h2><?= !empty($search_type) ? htmlspecialchars($search_type) . 's' : 'Available Venues' ?></h2>
            <a href="search.php">Clear Filters ✕</a>

            <div id="featured-venue-cards">
              <?php if (!empty($venues)): ?>
                <?php foreach ($venues as $venue): ?>
                  <div class="venue-card"> 
                    <div class="venue-card-image">
                      <?php $imgSrc = !empty($venue['image_url']) ? $venue['image_url'] : 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=1920&auto=format&fit=crop&q=80'; ?>
                      <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($venue['name'] ?? 'Venue') ?>" />
                      <div class="hall-type-tag"><?= htmlspecialchars($venue['venue_type'] ?? 'Venue') ?></div>
                      <div class="featured-tag">Featured</div>
                      <div class="rating-tag">★4.8</div>
                    </div>

                    <div class="location-details">
                      <p><?= htmlspecialchars($venue['name'] ?? 'Unnamed Venue') ?></p>
                      <p class="cost">$<?= number_format($venue['base_price_per_hour'] ?? 0, 2) ?>/day</p>
                      <br />
                      <p>
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i> 
                        <?= htmlspecialchars($venue['district'] ?? 'Unknown Location') ?>
                      </p>
                    </div>
                    <div class="hall-details">
                      <p>
                        <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                        Up to <?= htmlspecialchars($venue['capacity'] ?? 'N/A') ?> guests
                      </p>
                      <p>
                        <i class="fa-solid fa-comment-dots" aria-hidden="true"></i> 0 reviews
                      </p>
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
</body>
</html>